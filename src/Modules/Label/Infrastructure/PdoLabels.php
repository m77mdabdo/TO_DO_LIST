<?php

declare(strict_types=1);

namespace App\Modules\Label\Infrastructure;

use App\Modules\Label\Contract\Label;
use App\Modules\Label\Domain\LabelRepository;
use App\Support\Clock;
use PDO;

final class PdoLabels implements LabelRepository
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    /** @return list<Label> */
    public function forBoard(int $boardId): array
    {
        $stmt = $this->db->prepare('SELECT id, board_id, name, color FROM labels WHERE board_id = ? ORDER BY name, id');
        $stmt->execute([$boardId]);

        return array_map([Label::class, 'fromRow'], $stmt->fetchAll());
    }

    public function find(int $labelId, int $boardId): ?Label
    {
        $stmt = $this->db->prepare('SELECT id, board_id, name, color FROM labels WHERE id = ? AND board_id = ?');
        $stmt->execute([$labelId, $boardId]);
        $row = $stmt->fetch();

        return $row ? Label::fromRow($row) : null;
    }

    public function findByName(int $boardId, string $name): ?Label
    {
        $stmt = $this->db->prepare('SELECT id, board_id, name, color FROM labels WHERE board_id = ? AND name = ?');
        $stmt->execute([$boardId, $name]);
        $row = $stmt->fetch();

        return $row ? Label::fromRow($row) : null;
    }

    public function create(int $boardId, string $name, string $color): Label
    {
        $this->db->prepare('INSERT INTO labels (board_id, name, color, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$boardId, $name, $color, $this->clock->now()->format('Y-m-d H:i:s')]);

        return new Label((int) $this->db->lastInsertId(), $boardId, $name, $color);
    }

    /** @return array<int, list<Label>> */
    public function forCards(array $cardIds): array
    {
        $cardIds = array_values(array_unique(array_map('intval', $cardIds)));

        if ($cardIds === []) {
            return [];
        }

        $in = implode(',', array_fill(0, count($cardIds), '?'));
        $stmt = $this->db->prepare("SELECT cl.card_id, l.id, l.board_id, l.name, l.color
            FROM card_labels cl JOIN labels l ON l.id = cl.label_id
            WHERE cl.card_id IN ($in) ORDER BY l.name, l.id");
        $stmt->execute($cardIds);

        $byCard = [];
        foreach ($stmt->fetchAll() as $row) {
            $byCard[(int) $row['card_id']][] = Label::fromRow($row);
        }

        return $byCard;
    }

    public function isAttached(int $cardId, int $labelId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM card_labels WHERE card_id = ? AND label_id = ?');
        $stmt->execute([$cardId, $labelId]);

        return (bool) $stmt->fetchColumn();
    }

    public function attach(int $cardId, int $labelId): void
    {
        $this->db->prepare('INSERT INTO card_labels (card_id, label_id) VALUES (?, ?)')->execute([$cardId, $labelId]);
    }

    public function detach(int $cardId, int $labelId): void
    {
        $this->db->prepare('DELETE FROM card_labels WHERE card_id = ? AND label_id = ?')->execute([$cardId, $labelId]);
    }
}
