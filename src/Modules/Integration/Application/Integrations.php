<?php

declare(strict_types=1);

namespace App\Modules\Integration\Application;

use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardCatalog;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\Events\CardChanged;
use App\Modules\Card\Contract\Events\CardCreated;
use App\Modules\Card\Contract\Events\CardMemberChanged;
use App\Modules\Card\Contract\Events\CardMoved;
use App\Modules\Discussion\Contract\Events\CommentPosted;
use App\Modules\Integration\Contract\IntegrationType;
use App\Modules\Integration\Domain\UrlGuard;
use App\Modules\Integration\Domain\WebhookTransport;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceSummary;
use App\Security\Secrets;
use App\Shared\Events\DomainEvent;
use App\Shared\Ids\Ulid;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use PDO;

/**
 * The integration center of a workspace: ready-made integrations (see
 * IntegrationTypes) that workspace owners and admins connect, pause,
 * re-point (rotate the secret) and disconnect. Every change is logged.
 * The secret is kept encrypted with APP_KEY and never shown again.
 *
 * Messages are queued per event and sent by bin/webhooks.php. At send time
 * the integration must still be active and the person who connected it
 * must still be able to open the board; otherwise the message is dropped
 * with a reason.
 */
final class Integrations
{
    public const MAX_PER_WORKSPACE = 10;
    public const RETRY_MINUTES = [1, 5, 30, 120, 720];

    public function __construct(
        private PDO $db,
        private IntegrationTypes $types,
        private Secrets $secrets,
        private Webhooks $webhooks,
        private UrlGuard $guard,
        private WebhookTransport $transport,
        private WorkspaceDirectory $workspaces,
        private BoardCatalog $catalog,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private Clock $clock,
    ) {
    }

    public function canManage(int $userId, WorkspaceSummary $workspace): bool
    {
        return $this->workspaces->roleOf($userId, $workspace->id)?->managesAllBoards() === true;
    }

    public function encryptionReady(): bool
    {
        return $this->secrets->available();
    }

    /** @return list<IntegrationType> */
    public function types(): array
    {
        return $this->types->all();
    }

    /** @return list<array<string, mixed>> */
    public function forWorkspace(int $workspaceId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM integrations WHERE workspace_id = ? AND status <> 'disconnected' ORDER BY id");
        $stmt->execute([$workspaceId]);

        return array_map(fn (array $row) => $this->row($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<BoardSummary> boards of the workspace the person can open */
    public function boardsFor(int $userId, WorkspaceSummary $workspace): array
    {
        return array_values(array_filter($this->catalog->boardsInWorkspace($userId, $workspace->id), static fn (BoardSummary $b) => !$b->archived));
    }

    /** @return list<array{action: string, details: string, actorId: ?int, createdAt: string, integration: string}> newest first */
    public function log(int $workspaceId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.action, l.details, l.actor_id, l.created_at, i.name FROM integration_log l JOIN integrations i ON i.id = l.integration_id
             WHERE i.workspace_id = ? ORDER BY l.id DESC LIMIT ' . max(1, $limit),
        );
        $stmt->execute([$workspaceId]);

        return array_map(static fn (array $r) => [
            'action'      => (string) $r['action'],
            'details'     => (string) $r['details'],
            'actorId'     => $r['actor_id'] !== null ? (int) $r['actor_id'] : null,
            'createdAt'   => (string) $r['created_at'],
            'integration' => (string) $r['name'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array{event: string, status: string, error: string, responseCode: ?int, createdAt: string}> */
    public function deliveries(int $integrationId, int $limit = 10): array
    {
        $stmt = $this->db->prepare('SELECT event, status, error, response_code, created_at FROM integration_deliveries WHERE integration_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit));
        $stmt->execute([$integrationId]);

        return array_map(static fn (array $r) => [
            'event'        => (string) $r['event'],
            'status'       => (string) $r['status'],
            'error'        => (string) $r['error'],
            'responseCode' => $r['response_code'] !== null ? (int) $r['response_code'] : null,
            'createdAt'    => (string) $r['created_at'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param list<string> $boardPublicIds
     * @param list<string> $events
     */
    public function connect(int $actorId, WorkspaceSummary $workspace, string $typeKey, string $name, string $secret, array $boardPublicIds, array $events): string
    {
        $this->assertManages($actorId, $workspace);
        $type = $this->types->find($typeKey) ?? $this->fail('type', 'Choose an integration.');
        if (!$this->secrets->available()) {
            $this->fail('secret', 'Set APP_KEY in .env first (make one with php bin/app-key.php), so the secret can be stored encrypted.');
        }
        $secret = trim($secret);
        $errors = [];
        if (($error = $type->checkSecret($secret)) !== null) {
            $errors['secret'] = $error;
        }
        $name = trim($name) !== '' ? mb_substr(trim($name), 0, 100) : $type->name();
        [$boardIds, $events, $more] = $this->choices($actorId, $workspace, $type, $boardPublicIds, $events);
        $errors += $more;
        if ($this->countActive($workspace->id) >= self::MAX_PER_WORKSPACE) {
            $errors['type'] = 'A workspace can have up to 10 integrations.';
        }
        if ($errors !== []) {
            throw new ValidationException(new ValidationResult($errors));
        }

        $publicId = Ulid::generate();
        $now = $this->now();
        $this->db->prepare(
            "INSERT INTO integrations (public_id, workspace_id, type, name, secret, secret_hint, board_ids, events, status, connected_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)",
        )->execute([$publicId, $workspace->id, $type->key(), $name, $this->secrets->encrypt($secret), $type->hint($secret), json_encode($boardIds), json_encode($events), $actorId, $now, $now]);
        $this->record((int) $this->db->lastInsertId(), $actorId, 'connected', count($boardIds) . ' boards, ' . count($events) . ' events');

        return $publicId;
    }

    /** The integration, for a person who manages its workspace. @return array<string, mixed> */
    public function manageable(int $actorId, string $publicId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM integrations WHERE public_id = ? AND status <> 'disconnected'");
        $stmt->execute([$publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || $this->workspaces->roleOf($actorId, (int) $row['workspace_id'])?->managesAllBoards() !== true) {
            throw AccessDenied::hidden();
        }

        return $this->row($row);
    }

    /**
     * @param array<string, mixed> $integration
     * @param list<string> $boardPublicIds
     * @param list<string> $events
     */
    public function update(int $actorId, array $integration, string $name, array $boardPublicIds, array $events, bool $active): void
    {
        $workspace = $this->workspace($actorId, $integration['workspaceId']);
        $type = $this->types->find($integration['type']) ?? $this->fail('type', 'This kind of integration is no longer available.');
        [$boardIds, $events, $errors] = $this->choices($actorId, $workspace, $type, $boardPublicIds, $events);
        if ($errors !== []) {
            throw new ValidationException(new ValidationResult($errors));
        }
        $name = trim($name) !== '' ? mb_substr(trim($name), 0, 100) : $integration['name'];
        $this->db->prepare('UPDATE integrations SET name = ?, board_ids = ?, events = ?, status = ?, updated_at = ? WHERE id = ?')
            ->execute([$name, json_encode($boardIds), json_encode($events), $active ? 'active' : 'paused', $this->now(), $integration['id']]);

        $changes = array_filter([
            $name !== $integration['name'] ? 'renamed' : null,
            $boardIds !== $integration['boardIds'] ? 'boards: ' . count($boardIds) : null,
            $events !== $integration['events'] ? 'events: ' . count($events) : null,
        ]);
        if ($changes !== []) {
            $this->record($integration['id'], $actorId, 'settings_changed', implode(', ', $changes));
        }
        if ($active !== ($integration['status'] === 'active')) {
            $this->record($integration['id'], $actorId, $active ? 'resumed' : 'paused');
        }
    }

    /** Replaces the secret (e.g. a new Slack webhook after the old one leaked). @param array<string, mixed> $integration */
    public function rotate(int $actorId, array $integration, string $secret): void
    {
        $type = $this->types->find($integration['type']) ?? $this->fail('type', 'This kind of integration is no longer available.');
        $secret = trim($secret);
        if (($error = $type->checkSecret($secret)) !== null) {
            $this->fail('secret', $error);
        }
        if (!$this->secrets->available()) {
            $this->fail('secret', 'Set APP_KEY in .env first (make one with php bin/app-key.php), so the secret can be stored encrypted.');
        }
        $this->db->prepare("UPDATE integrations SET secret = ?, secret_hint = ?, connected_by = ?, last_error = '', updated_at = ? WHERE id = ?")
            ->execute([$this->secrets->encrypt($secret), $type->hint($secret), $actorId, $this->now(), $integration['id']]);
        $this->record($integration['id'], $actorId, 'rotated', $integration['secretHint'] . ' → ' . $type->hint($secret));
    }

    /** Wipes the secret and drops what was waiting; the history stays. @param array<string, mixed> $integration */
    public function disconnect(int $actorId, array $integration): void
    {
        $this->db->prepare("UPDATE integrations SET status = 'disconnected', secret = NULL, updated_at = ? WHERE id = ?")->execute([$this->now(), $integration['id']]);
        $this->db->prepare("UPDATE integration_deliveries SET status = 'failed', error = 'Disconnected.' WHERE integration_id = ? AND status = 'pending'")->execute([$integration['id']]);
        $this->record($integration['id'], $actorId, 'disconnected');
    }

    /** Sends a test message now. Returns null when it worked, else what went wrong. @param array<string, mixed> $integration */
    public function test(int $actorId, array $integration): ?string
    {
        $workspace = $this->workspace($actorId, $integration['workspaceId']);
        $type = $this->types->find($integration['type']);
        $secret = $this->secrets->decrypt((string) $integration['secret']);
        if ($type === null || $secret === null) {
            $error = 'The saved secret can\'t be read (was APP_KEY changed?). Rotate it.';
        } else {
            $error = $this->send($type->testRequest($secret, $workspace->name))['error'];
        }
        $this->outcome($integration['id'], $error);
        $this->record($integration['id'], $actorId, 'tested', $error ?? 'ok');

        return $error;
    }

    /** Queues a message for every active integration that wants this event on this board. */
    public function handle(DomainEvent $event): void
    {
        $kind = match (true) {
            $event instanceof CardCreated       => 'card.created',
            $event instanceof CardMoved         => 'card.moved',
            $event instanceof CardChanged       => $event->change === 'completed' ? 'card.completed' : null,
            $event instanceof CardMemberChanged => 'card.member_changed',
            $event instanceof CommentPosted     => 'comment.posted',
            default                             => null,
        };
        if ($kind === null) {
            return;
        }
        /** @var CardCreated|CardMoved|CardChanged|CardMemberChanged|CommentPosted $event */
        $board = $this->boards->find($event->boardId);
        if ($board === null) {
            return;
        }
        $stmt = $this->db->prepare("SELECT id, board_ids, events FROM integrations WHERE workspace_id = ? AND status = 'active'");
        $stmt->execute([$board->workspaceId]);
        $payload = null;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (in_array($board->id, (array) json_decode((string) $row['board_ids'], true), true) && in_array($kind, (array) json_decode((string) $row['events'], true), true)) {
                $payload ??= (string) json_encode(['kind' => $kind] + $this->webhooks->payload($event), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $this->db->prepare("INSERT INTO integration_deliveries (integration_id, board_id, event, payload, status, next_attempt_at, created_at) VALUES (?, ?, ?, ?, 'pending', ?, ?)")
                    ->execute([(int) $row['id'], $board->id, $kind, $payload, $this->now(), $this->now()]);
            }
        }
    }

    /** Sends what is due. Returns [sent, failed attempts]. */
    public function deliverDue(int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*, i.type, i.secret, i.status AS integration_status, i.connected_by, i.workspace_id FROM integration_deliveries d JOIN integrations i ON i.id = d.integration_id
             WHERE d.status = 'pending' AND d.next_attempt_at <= ? ORDER BY d.id LIMIT " . max(1, $limit),
        );
        $stmt->execute([$this->now()]);
        $sent = $failed = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $id = (int) $d['id'];
            $connector = $d['connected_by'] !== null ? (int) $d['connected_by'] : null;
            $boardId = $d['board_id'] !== null ? (int) $d['board_id'] : 0;
            // The connector's access is checked again now: it may have changed since the event.
            $reason = match (true) {
                $d['integration_status'] !== 'active' => 'The integration is paused.',
                $connector === null || $this->workspaces->roleOf($connector, (int) $d['workspace_id']) === null
                    || !$this->gate->can($connector, $boardId, Permission::ViewBoard) => 'The person who connected it can no longer open this board. Reconnect or rotate it as someone who can.',
                default => null,
            };
            $type = $this->types->find((string) $d['type']);
            $secret = $this->secrets->decrypt((string) $d['secret']);
            if ($reason === null && ($type === null || $secret === null)) {
                $reason = 'The saved secret can\'t be read (was APP_KEY changed?). Rotate it.';
            }
            if ($reason !== null) {
                $this->db->prepare("UPDATE integration_deliveries SET status = 'failed', error = ?, attempts = attempts + 1 WHERE id = ?")->execute([$reason, $id]);
                $this->outcome((int) $d['integration_id'], $reason);
                $failed++;
                continue;
            }

            $payload = (array) json_decode((string) $d['payload'], true);
            /** @var IntegrationType $type */
            $result = $this->send($type->request((string) $secret, $payload));
            if ($result['error'] === null) {
                $this->db->prepare("UPDATE integration_deliveries SET status = 'sent', response_code = ?, attempts = attempts + 1, error = '', delivered_at = ? WHERE id = ?")->execute([$result['status'], $this->now(), $id]);
                $this->outcome((int) $d['integration_id'], null);
                $sent++;
                continue;
            }
            $wait = self::RETRY_MINUTES[(int) $d['attempts']] ?? null;
            $this->db->prepare('UPDATE integration_deliveries SET status = ?, response_code = ?, attempts = attempts + 1, error = ?, next_attempt_at = ? WHERE id = ?')->execute([
                $wait !== null ? 'pending' : 'failed',
                $result['status'],
                $result['error'],
                $wait !== null ? $this->clock->now()->modify("+$wait minutes")->format('Y-m-d H:i:s') : $d['next_attempt_at'],
                $id,
            ]);
            $this->outcome((int) $d['integration_id'], $result['error']);
            $failed++;
        }

        return [$sent, $failed];
    }

    /**
     * @param array{url: string, body: string} $request
     * @return array{status: ?int, error: ?string}
     */
    private function send(array $request): array
    {
        $check = $this->guard->check($request['url']);
        if (!$check['ok']) {
            return ['status' => null, 'error' => $check['error']];
        }
        $result = $this->transport->post($request['url'], $check['ip'], ['Content-Type' => 'application/json', 'User-Agent' => 'TodoBoard-Integrations/1'], $request['body']);
        if ($result['status'] !== null && $result['status'] >= 200 && $result['status'] < 300) {
            return ['status' => $result['status'], 'error' => null];
        }

        return ['status' => $result['status'], 'error' => $result['error'] !== '' ? $result['error'] : 'The server answered ' . (int) $result['status'] . '.'];
    }

    private function outcome(int $integrationId, ?string $error): void
    {
        $error === null
            ? $this->db->prepare("UPDATE integrations SET last_success_at = ?, last_error = '' WHERE id = ?")->execute([$this->now(), $integrationId])
            : $this->db->prepare('UPDATE integrations SET last_error = ?, last_error_at = ? WHERE id = ?')->execute([mb_substr($error, 0, 500), $this->now(), $integrationId]);
    }

    /**
     * @param list<string> $boardPublicIds
     * @param list<string> $events
     * @return array{0: list<int>, 1: list<string>, 2: array<string, string>}
     */
    private function choices(int $actorId, WorkspaceSummary $workspace, IntegrationType $type, array $boardPublicIds, array $events): array
    {
        $allowed = [];
        foreach ($this->boardsFor($actorId, $workspace) as $board) {
            $allowed[$board->publicId] = $board->id;
        }
        $boardIds = array_values(array_unique(array_map(static fn ($p) => $allowed[$p], array_values(array_filter($boardPublicIds, static fn ($p) => is_string($p) && isset($allowed[$p]))))));
        sort($boardIds);
        $events = array_values(array_intersect(array_keys($type->events()), $events));
        $errors = [];
        if ($boardIds === []) {
            $errors['boards'] = 'Choose at least one board.';
        }
        if ($events === []) {
            $errors['events'] = 'Choose at least one thing to send.';
        }

        return [$boardIds, $events, $errors];
    }

    private function assertManages(int $actorId, WorkspaceSummary $workspace): void
    {
        if (!$this->canManage($actorId, $workspace)) {
            throw AccessDenied::forbidden();
        }
    }

    private function workspace(int $actorId, int $workspaceId): WorkspaceSummary
    {
        foreach ($this->workspaces->workspacesOf($actorId) as $workspace) {
            if ($workspace->id === $workspaceId) {
                $this->assertManages($actorId, $workspace);

                return $workspace;
            }
        }
        throw AccessDenied::hidden();
    }

    private function countActive(int $workspaceId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM integrations WHERE workspace_id = ? AND status <> 'disconnected'");
        $stmt->execute([$workspaceId]);

        return (int) $stmt->fetchColumn();
    }

    private function record(int $integrationId, int $actorId, string $action, string $details = ''): void
    {
        $this->db->prepare('INSERT INTO integration_log (integration_id, actor_id, action, details, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$integrationId, $actorId, $action, mb_substr($details, 0, 500), $this->now()]);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function row(array $row): array
    {
        return [
            'id'            => (int) $row['id'],
            'publicId'      => (string) $row['public_id'],
            'workspaceId'   => (int) $row['workspace_id'],
            'type'          => (string) $row['type'],
            'name'          => (string) $row['name'],
            'secret'        => $row['secret'],
            'secretHint'    => (string) $row['secret_hint'],
            'boardIds'      => array_map('intval', (array) json_decode((string) $row['board_ids'], true)),
            'events'        => array_values(array_map('strval', (array) json_decode((string) $row['events'], true))),
            'status'        => (string) $row['status'],
            'connectedBy'   => $row['connected_by'] !== null ? (int) $row['connected_by'] : null,
            'lastSuccessAt' => $row['last_success_at'],
            'lastError'     => (string) $row['last_error'],
            'lastErrorAt'   => $row['last_error_at'],
            'createdAt'     => (string) $row['created_at'],
        ];
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
