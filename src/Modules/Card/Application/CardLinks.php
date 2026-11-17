<?php

declare(strict_types=1);

namespace App\Modules\Card\Application;

use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Card\Contract\Events\CardChanged;
use App\Shared\Events\EventBus;
use App\Shared\Ids\Ulid;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use PDO;

/**
 * Links between two cards of the same workspace (Trello's "connected
 * cards"). A link shows on both cards. Adding one needs EditCards on this
 * card and the right to open the other card's board; removing one needs
 * EditCards on either side. What the other card is (title, board, list) is
 * only shown to a viewer who can open its board, checked on every view.
 */
final class CardLinks
{
    public const MAX_PER_CARD = 50;

    public function __construct(
        private PDO $db,
        private CardQueries $cards,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    /**
     * What the viewer may see of each card linked to $card.
     *
     * @return list<array{id: int, card: ?CardSummary, board: ?string, list: ?string}> card is null when the viewer can't open it
     */
    public function forCard(int $viewerId, CardSummary $card): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, CASE WHEN card_id = :a THEN linked_card_id ELSE card_id END AS other
             FROM card_links WHERE card_id = :b OR linked_card_id = :c ORDER BY id',
        );
        $stmt->execute(['a' => $card->id, 'b' => $card->id, 'c' => $card->id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $others = $this->cards->findMany(array_map(static fn (array $r) => (int) $r['other'], $rows));

        $out = [];
        foreach ($rows as $row) {
            $other = $others[(int) $row['other']] ?? null;
            $visible = $other !== null && !$other->archived && $this->gate->can($viewerId, $other->boardId, Permission::ViewBoard);
            $board = $visible ? $this->boards->find($other->boardId) : null;
            $list = $visible ? $this->boards->listOnBoard($other->listId, $other->boardId) : null;
            $out[] = [
                'id'    => (int) $row['id'],
                'card'  => $visible && $board !== null && !$board->archived ? $other : null,
                'board' => $board?->name,
                'list'  => $list?->name,
            ];
        }

        return $out;
    }

    /**
     * Links $card to the card named by $target (a card address or its id).
     *
     * @throws ValidationException
     */
    public function add(int $actorId, CardSummary $card, string $target): void
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::EditCards);
        $publicId = self::publicIdIn($target);
        $other = $publicId !== null ? $this->cards->findByPublicId($publicId) : null;
        $board = $this->boards->find($card->boardId);
        $otherBoard = $other !== null ? $this->boards->find($other->boardId) : null;

        // A card the person can't open looks the same as one that doesn't exist.
        if ($other === null || $otherBoard === null || $board === null || $other->archived || !$this->gate->can($actorId, $other->boardId, Permission::ViewBoard)) {
            $this->fail('No card with that address that you can open.');
        }
        if ($other->id === $card->id) {
            $this->fail('A card cannot be linked to itself.');
        }
        if ($otherBoard->workspaceId !== $board->workspaceId) {
            $this->fail('Only cards in the same workspace can be linked.');
        }
        if ($this->linkBetween($card->id, $other->id) !== null) {
            $this->fail('These cards are already linked.');
        }
        if ($this->count($card->id) >= self::MAX_PER_CARD) {
            $this->fail('A card can have up to 50 linked cards.');
        }

        $this->db->prepare('INSERT INTO card_links (card_id, linked_card_id, created_by, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$card->id, $other->id, $actorId, $this->clock->now()->format('Y-m-d H:i:s')]);
        // Only this card's board hears about it, and without the other card's title.
        $this->events->publish(new CardChanged($card->id, $card->boardId, $actorId, 'linked', $card->title, [], $this->clock->now()));
    }

    public function remove(int $actorId, CardSummary $card, int $linkId): void
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::EditCards);
        $stmt = $this->db->prepare('SELECT id FROM card_links WHERE id = ? AND (card_id = ? OR linked_card_id = ?)');
        $stmt->execute([$linkId, $card->id, $card->id]);
        if ($stmt->fetchColumn() === false) {
            throw AccessDenied::forbidden();
        }

        $this->db->prepare('DELETE FROM card_links WHERE id = ?')->execute([$linkId]);
        $this->events->publish(new CardChanged($card->id, $card->boardId, $actorId, 'unlinked', $card->title, [], $this->clock->now()));
    }

    /** The card id in "https://…/cards/01J…#x", "/cards/01J…" or "01J…", else null. */
    public static function publicIdIn(string $text): ?string
    {
        $text = trim($text);
        if (Ulid::isValid($text)) {
            return $text;
        }

        return preg_match('#/cards/([0-9A-HJKMNP-TV-Z]{26})(?:[/?\#]|$)#i', $text, $m) === 1 && Ulid::isValid(strtoupper($m[1])) ? strtoupper($m[1]) : null;
    }

    private function linkBetween(int $a, int $b): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM card_links WHERE (card_id = ? AND linked_card_id = ?) OR (card_id = ? AND linked_card_id = ?)');
        $stmt->execute([$a, $b, $b, $a]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function count(int $cardId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM card_links WHERE card_id = ? OR linked_card_id = ?');
        $stmt->execute([$cardId, $cardId]);

        return (int) $stmt->fetchColumn();
    }

    private function fail(string $message): never
    {
        throw new ValidationException(new ValidationResult(['link' => $message]));
    }
}
