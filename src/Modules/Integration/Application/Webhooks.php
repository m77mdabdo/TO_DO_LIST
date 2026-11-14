<?php

declare(strict_types=1);

namespace App\Modules\Integration\Application;

use App\Core\Config;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\Events\CardChanged;
use App\Modules\Card\Contract\Events\CardCreated;
use App\Modules\Card\Contract\Events\CardMemberChanged;
use App\Modules\Card\Contract\Events\CardMoved;
use App\Modules\Checklist\Contract\Events\ChecklistChanged;
use App\Modules\Discussion\Contract\Events\CommentPosted;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Integration\Contract\Webhook;
use App\Modules\Integration\Contract\WebhookDelivery;
use App\Modules\Integration\Domain\UrlGuard;
use App\Modules\Integration\Domain\WebhookRepository;
use App\Modules\Integration\Domain\WebhookTransport;
use App\Modules\Label\Contract\Events\LabelAttached;
use App\Shared\Events\DomainEvent;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;

/**
 * Outgoing webhooks: board managers point a board at an https address, and
 * chosen events are queued there as signed JSON. bin/webhooks.php sends the
 * queue, retrying failures with growing pauses. Messages carry only what
 * the board's own people can see.
 */
final class Webhooks
{
    public const MAX_PER_BOARD = 5;
    /** Minutes to wait before each retry; after the last one we give up. */
    public const RETRY_MINUTES = [1, 5, 30, 120, 720];

    public function __construct(
        private WebhookRepository $webhooks,
        private UrlGuard $guard,
        private WebhookTransport $transport,
        private AccessGate $gate,
        private BoardDirectory $boards,
        private CardQueries $cards,
        private UserDirectory $users,
        private Clock $clock,
        private Config $config,
    ) {
    }

    public function canManage(int $userId, BoardSummary $board): bool
    {
        return $this->gate->can($userId, $board->id, Permission::ManageSettings);
    }

    /** @return list<Webhook> */
    public function forBoard(int $userId, BoardSummary $board): array
    {
        $this->gate->authorize($userId, $board->id, Permission::ManageSettings);

        return $this->webhooks->forBoard($board->id);
    }

    /** @return list<WebhookDelivery> */
    public function deliveries(Webhook $webhook): array
    {
        return $this->webhooks->deliveries($webhook->id);
    }

    /** @param list<string> $events */
    public function create(int $userId, BoardSummary $board, string $url, array $events): Webhook
    {
        $this->gate->authorize($userId, $board->id, Permission::ManageSettings);
        $url = trim($url);
        $events = array_values(array_intersect(array_keys(Webhook::EVENTS), $events));
        $errors = [];
        $check = $this->guard->check($url);
        if (!$check['ok']) {
            $errors['url'] = $check['error'];
        }
        if ($events === []) {
            $errors['events'] = 'Choose at least one thing to send.';
        }
        if (count($this->webhooks->forBoard($board->id)) >= self::MAX_PER_BOARD) {
            $errors['url'] = 'A board can have at most 5 webhooks.';
        }
        if ($errors !== []) {
            throw new ValidationException(new ValidationResult($errors));
        }

        return $this->webhooks->create($board->id, $url, bin2hex(random_bytes(32)), $events, $userId);
    }

    public function manageable(int $userId, string $publicId): Webhook
    {
        $webhook = $this->webhooks->findByPublicId($publicId);
        if ($webhook === null || !$this->gate->can($userId, $webhook->boardId, Permission::ViewBoard)) {
            throw AccessDenied::hidden();
        }
        $this->gate->authorize($userId, $webhook->boardId, Permission::ManageSettings);

        return $webhook;
    }

    public function setEnabled(int $userId, string $publicId, bool $enabled): Webhook
    {
        $webhook = $this->manageable($userId, $publicId);
        $this->webhooks->setEnabled($webhook->id, $enabled);

        return $webhook;
    }

    public function delete(int $userId, string $publicId): Webhook
    {
        $webhook = $this->manageable($userId, $publicId);
        $this->webhooks->delete($webhook->id);

        return $webhook;
    }

    /** Queues a delivery for every webhook of the board that wants this event. */
    public function handle(DomainEvent $event): void
    {
        $boardId = match (true) {
            $event instanceof CardCreated, $event instanceof CardMoved, $event instanceof CardChanged, $event instanceof CardMemberChanged,
            $event instanceof LabelAttached, $event instanceof ChecklistChanged, $event instanceof CommentPosted => $event->boardId,
            default => null,
        };
        if ($boardId === null) {
            return;
        }

        $hooks = array_filter($this->webhooks->forBoard($boardId), static fn (Webhook $w) => $w->wants($event::name()));
        if ($hooks === []) {
            return;
        }

        $payload = (string) json_encode($this->payload($event), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach ($hooks as $hook) {
            $this->webhooks->queue($hook->id, $event::name(), $payload);
        }
    }

    /** Sends what is due. Returns [sent, failed attempts]. */
    public function deliverDue(int $limit = 100): array
    {
        $sent = 0;
        $failed = 0;
        foreach ($this->webhooks->due($limit) as $delivery) {
            $webhook = $this->webhooks->find($delivery->webhookId);
            if ($webhook === null || !$webhook->enabled) {
                $this->webhooks->markFailed($delivery->id, null, 'The webhook is paused.', null);
                continue;
            }

            // Checked again at send time: a name can start pointing somewhere else.
            $check = $this->guard->check($webhook->url);
            $result = $check['ok']
                ? $this->transport->post($webhook->url, $check['ip'], [
                    'Content-Type'       => 'application/json',
                    'User-Agent'         => 'TodoBoard-Webhooks/1',
                    'X-Todo-Event'       => $delivery->event,
                    'X-Todo-Delivery'    => (string) $delivery->id,
                    'X-Todo-Signature'   => 'sha256=' . hash_hmac('sha256', $delivery->payload, $webhook->secret),
                ], $delivery->payload)
                : ['status' => null, 'error' => $check['error']];

            if ($result['status'] !== null && $result['status'] >= 200 && $result['status'] < 300) {
                $this->webhooks->markSent($delivery->id, $result['status']);
                $sent++;
                continue;
            }

            $wait = self::RETRY_MINUTES[$delivery->attempts] ?? null;
            $this->webhooks->markFailed(
                $delivery->id,
                $result['status'],
                $result['error'] !== '' ? $result['error'] : 'The server answered ' . (int) $result['status'] . '.',
                $wait !== null ? $this->clock->now()->modify("+$wait minutes")->format('Y-m-d H:i:s') : null,
            );
            $failed++;
        }

        return [$sent, $failed];
    }

    /** @return array<string, mixed> */
    private function payload(DomainEvent $event): array
    {
        /** @var CardCreated|CardMoved|CardChanged|CardMemberChanged|LabelAttached|ChecklistChanged|CommentPosted $event */
        $board = $this->boards->find($event->boardId);
        $card = $this->cards->find($event->cardId);
        $actor = $this->users->find($event->actorId);
        $base = rtrim((string) $this->config->get('url', ''), '/');

        $details = match (true) {
            $event instanceof CardMoved         => ['from_list' => $this->listName($event->fromListId, $event->boardId), 'to_list' => $this->listName($event->toListId, $event->boardId)],
            $event instanceof CardChanged       => ['change' => $event->change],
            $event instanceof CardMemberChanged => ['member' => $this->users->find($event->memberId)?->name, 'added' => $event->added],
            $event instanceof LabelAttached     => ['label' => $event->labelName],
            $event instanceof ChecklistChanged  => ['change' => $event->change, 'subject' => $event->subject],
            $event instanceof CommentPosted     => ['excerpt' => $event->excerpt],
            default                             => [],
        };

        return [
            'event'   => $event::name(),
            'at'      => $event->occurredAt()->format(DATE_ATOM),
            'board'   => $board !== null ? ['id' => $board->publicId, 'name' => $board->name, 'url' => $base . '/boards/' . $board->publicId] : null,
            'card'    => $card !== null ? ['id' => $card->publicId, 'title' => $card->title, 'list' => $this->listName($card->listId, $card->boardId), 'due' => $card->dueDate(), 'complete' => $card->isComplete(), 'url' => $base . '/cards/' . $card->publicId] : null,
            'actor'   => $actor !== null ? ['name' => $actor->name] : null,
            'details' => $details,
        ];
    }

    private function listName(int $listId, int $boardId): ?string
    {
        return $this->boards->listOnBoard($listId, $boardId)?->name;
    }
}
