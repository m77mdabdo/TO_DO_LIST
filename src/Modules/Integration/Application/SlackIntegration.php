<?php

declare(strict_types=1);

namespace App\Modules\Integration\Application;

use App\I18n\Translator;
use App\Modules\Integration\Contract\IntegrationType;

/**
 * Slack, through an "incoming webhook": the workspace admin makes one in
 * Slack for a channel and pastes its address here. Messages go to that one
 * channel only; nothing is read back from Slack and no Slack account is
 * linked. Only hooks.slack.com addresses are accepted.
 */
final class SlackIntegration implements IntegrationType
{
    public function __construct(private Translator $translator)
    {
    }

    public function key(): string
    {
        return 'slack';
    }

    public function name(): string
    {
        return 'Slack';
    }

    public function description(): string
    {
        return 'Posts a short message to one Slack channel when something happens on the boards you choose.';
    }

    public function permissions(): array
    {
        return [
            'Sends card titles, list and board names, who did it, and a link back. Comment text is cut to a short excerpt.',
            'Only for the boards you choose, and only while the person who connected it can still open them.',
            'Reads nothing from Slack and needs no Slack account here.',
        ];
    }

    public function secretLabel(): string
    {
        return 'Slack webhook address';
    }

    public function setupSteps(): array
    {
        return [
            'In Slack, open api.slack.com/apps, create an app "from scratch" for your Slack workspace.',
            'Under "Incoming Webhooks" turn them on, then "Add New Webhook to Workspace" and pick the channel.',
            'Copy the address that starts with https://hooks.slack.com/services/ and paste it here.',
        ];
    }

    public function checkSecret(string $secret): ?string
    {
        return preg_match('#^https://hooks\.slack\.com/services/[A-Za-z0-9]+/[A-Za-z0-9]+/[A-Za-z0-9]+$#', $secret) === 1
            ? null
            : 'Paste the whole address; it starts with https://hooks.slack.com/services/';
    }

    public function hint(string $secret): string
    {
        return '…' . substr($secret, -4);
    }

    public function events(): array
    {
        return [
            'card.created'   => 'A card was added',
            'card.moved'     => 'A card was moved to another list',
            'card.completed' => 'A card was completed',
            'card.member_changed' => 'Someone was added to or removed from a card',
            'comment.posted' => 'Someone commented',
        ];
    }

    public function request(string $secret, array $payload): array
    {
        $actor = self::escape((string) ($payload['actor']['name'] ?? $this->t('Someone')));
        $card = isset($payload['card']) ? '<' . $payload['card']['url'] . '|' . self::escape((string) $payload['card']['title']) . '>' : '';
        $board = isset($payload['board']) ? '<' . $payload['board']['url'] . '|' . self::escape((string) $payload['board']['name']) . '>' : '';
        $d = (array) ($payload['details'] ?? []);

        $text = match ($payload['kind']) {
            'card.created'   => $this->t(':actor added :card to :list on :board', ['actor' => "*$actor*", 'card' => $card, 'list' => '*' . self::escape((string) ($payload['card']['list'] ?? '')) . '*', 'board' => $board]),
            'card.moved'     => $this->t(':actor moved :card from :from to :to on :board', ['actor' => "*$actor*", 'card' => $card, 'from' => '*' . self::escape((string) ($d['from_list'] ?? '')) . '*', 'to' => '*' . self::escape((string) ($d['to_list'] ?? '')) . '*', 'board' => $board]),
            'card.completed' => $this->t(':actor completed :card on :board', ['actor' => "*$actor*", 'card' => $card, 'board' => $board]),
            'card.member_changed' => $this->t(!empty($d['added']) ? ':actor added :member to :card on :board' : ':actor took :member off :card on :board', ['actor' => "*$actor*", 'member' => '*' . self::escape((string) ($d['member'] ?? '')) . '*', 'card' => $card, 'board' => $board]),
            'comment.posted' => $this->t(':actor commented on :card on :board', ['actor' => "*$actor*", 'card' => $card, 'board' => $board]) . "\n>" . self::escape(mb_substr((string) ($d['excerpt'] ?? ''), 0, 200)),
            default          => $this->t(':actor changed :card on :board', ['actor' => "*$actor*", 'card' => $card, 'board' => $board]),
        };

        return ['url' => $secret, 'body' => (string) json_encode(['text' => $text, 'unfurl_links' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }

    public function testRequest(string $secret, string $workspaceName): array
    {
        $text = $this->t('Test message from :app: this channel will get updates from :workspace.', ['app' => 'Todo Board', 'workspace' => '*' . self::escape($workspaceName) . '*']);

        return ['url' => $secret, 'body' => (string) json_encode(['text' => $text], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }

    /** Slack's own escaping for message text: &, < and >. */
    private static function escape(string $text): string
    {
        return strtr($text, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    }

    /** @param array<string, string> $replace */
    private function t(string $text, array $replace = []): string
    {
        return $this->translator->t($text, $replace);
    }
}
