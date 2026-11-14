<?php

declare(strict_types=1);

namespace App\Modules\Board\Application;

use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Domain\BoardRepository;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Identity\Contract\UserDirectory;
use App\Shared\Export\ExportParts;
use App\Support\Clock;

/**
 * A board as plain data for a JSON export or a backup: settings, lists
 * (archived ones too), cards with their people, labels, checklists,
 * comments and attachment details. File contents are not included.
 * Callers check the Export permission first.
 */
final class BoardExport
{
    public const FORMAT = 'todo-board/1';

    public function __construct(
        private BoardRepository $boards,
        private CardQueries $cards,
        private UserDirectory $users,
        private ExportParts $parts,
        private Clock $clock,
    ) {
    }

    /** @return array<string, mixed> */
    public function board(BoardSummary $board): array
    {
        $lists = [...$this->boards->lists($board->id), ...$this->boards->lists($board->id, true)];
        $open = array_merge([], ...array_values($this->cards->openCardsByList($board->id)));
        $cards = [...$open, ...$this->cards->archivedCards($board->id)];
        $cardIds = array_map(static fn ($c) => $c->id, $cards);
        $members = $this->cards->membersForCards($cardIds);
        $people = $this->users->findMany(array_values(array_unique(array_merge([], ...array_values($members)))));
        $parts = $this->parts->forCards($cardIds);
        $listNames = [];
        foreach ($lists as $list) {
            $listNames[$list->id] = $list->name;
        }

        return [
            'id'          => $board->publicId,
            'name'        => $board->name,
            'description' => $board->description,
            'color'       => $board->color,
            'visibility'  => $board->visibility->value,
            'commenting'  => $board->commenting->value,
            'voting'      => $board->voting->value,
            'archived'    => $board->archived,
            'template'    => $board->isTemplate,
            'lists'       => array_map(static fn ($l) => ['name' => $l->name, 'archived' => $l->archived], $lists),
            'cards'       => array_map(static fn ($card) => [
                'id'           => $card->publicId,
                'list'         => $listNames[$card->listId] ?? null,
                'title'        => $card->title,
                'description'  => $card->description,
                'start'        => $card->startDate(),
                'due'          => $card->dueDate(),
                'completed_at' => $card->completedAt,
                'archived'     => $card->archived,
                'created_at'   => $card->createdAt,
                'people'       => array_values(array_map(static fn (int $id) => $people[$id]->name ?? '', $members[$card->id] ?? [])),
            ] + ($parts[$card->id] ?? []), $cards),
        ];
    }

    /**
     * @param list<BoardSummary> $boards
     * @return array<string, mixed>
     */
    public function document(array $boards, string $workspaceName): array
    {
        return [
            'format'      => self::FORMAT,
            'exported_at' => $this->clock->now()->format(DATE_ATOM),
            'workspace'   => $workspaceName,
            'boards'      => array_map(fn (BoardSummary $b) => $this->board($b), $boards),
        ];
    }
}
