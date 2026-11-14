<?php

declare(strict_types=1);

namespace App\Modules\Board\Application;

use App\Database\Transactions;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardCatalog;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Board\Contract\Events\BoardChanged;
use App\Modules\Board\Contract\Events\BoardCopied;
use App\Modules\Board\Contract\Events\BoardSeeded;
use App\Modules\Board\Contract\Events\BoardCreated;
use App\Modules\Board\Contract\Events\ListChanged;
use App\Modules\Board\Contract\Events\ListCreated;
use App\Modules\Board\Contract\ListSummary;
use App\Modules\Board\Domain\BoardRepository;
use App\Modules\Board\Domain\BuiltInTemplates;
use App\I18n\Translator;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use App\Validation\Validator;
use InvalidArgumentException;

/**
 * Boards and their lists. Every method takes the acting user and checks
 * the gate first; nothing here trusts the UI to have hidden a button.
 * (Plain lookups for other modules live in BoardQueries, which has no
 * dependency on the gate, so the gate can use them without a cycle.)
 */
final class BoardService implements BoardCatalog
{
    public function __construct(
        private BoardRepository $boards,
        private AccessGate $gate,
        private BoardMembers $members,
        private WorkspaceDirectory $workspaces,
        private Transactions $transactions,
        private EventBus $events,
        private Clock $clock,
        private Validator $validator,
        private ?Translator $translator = null,
    ) {
    }

    /* ---------- reads ---------- */

    /** The board, only if the user may at least view it (else a 404). */
    public function boardFor(int $userId, string $publicId, Permission $permission = Permission::ViewBoard): BoardSummary
    {
        $board = $this->boards->findByPublicId($publicId) ?? throw AccessDenied::hidden();
        $this->gate->authorize($userId, $board->id, $permission);

        return $board;
    }

    /** @return list<BoardSummary> */
    public function boardsInWorkspace(int $userId, int $workspaceId, bool $archived = false): array
    {
        $role = $this->workspaces->roleOf($userId, $workspaceId);

        if ($role === null) {
            return [];
        }

        return $this->boards->visibleInWorkspace(
            $workspaceId,
            $role->managesAllBoards(),
            $role->isFullMember(),
            $this->members->boardIdsOf($userId),
            $archived,
        );
    }

    /** @return list<ListSummary> open lists, in order (the caller already authorized the board) */
    public function listsOf(BoardSummary $board): array
    {
        return $this->boards->lists($board->id);
    }

    /** @return list<ListSummary> */
    public function archivedLists(int $userId, BoardSummary $board): array
    {
        $this->gate->authorize($userId, $board->id, Permission::ViewBoard);

        return $this->boards->lists($board->id, archived: true);
    }

    /* ---------- boards ---------- */

    public function createBoard(int $actorId, int $workspaceId, string $name, BoardVisibility $visibility = BoardVisibility::Private, string $color = 'violet'): BoardSummary
    {
        if (!$this->workspaces->roleOf($actorId, $workspaceId)?->canCreateBoards()) {
            throw AccessDenied::forbidden();
        }

        $this->assertValid(['name' => $name, 'color' => $color], [
            'name'  => ['Board name', 'required|max:100'],
            'color' => ['Color', 'required|in:' . implode(',', BoardSummary::COLORS)],
        ]);

        return $this->transactions->run(function () use ($actorId, $workspaceId, $name, $visibility, $color): BoardSummary {
            $board = $this->boards->create($workspaceId, trim($name), $color, $visibility, $actorId);
            $this->events->publish(new BoardCreated($board->id, $workspaceId, $actorId, $board->name, $this->clock->now()));

            return $board;
        });
    }

    /** @param array<string, mixed> $input name, description, color, visibility */
    public function updateBoard(int $actorId, BoardSummary $board, array $input): void
    {
        $this->gate->authorize($actorId, $board->id, Permission::ManageSettings);

        $this->assertValid($input, [
            'name'        => ['Board name', 'required|max:100'],
            'description' => ['Description', 'max:2000'],
            'color'       => ['Color', 'required|in:' . implode(',', BoardSummary::COLORS)],
            'visibility'  => ['Visibility', 'required|in:private,workspace'],
            'commenting'  => ['Commenting', 'in:off,members,everyone'],
            'voting'      => ['Voting', 'in:off,members,everyone'],
        ]);

        $fields = [
            'name'        => trim((string) $input['name']),
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'color'       => (string) $input['color'],
            'visibility'  => (string) $input['visibility'],
            'commenting'  => (string) ($input['commenting'] ?? '') ?: $board->commenting->value,
            'voting'      => (string) ($input['voting'] ?? '') ?: $board->voting->value,
        ];

        $this->transactions->run(function () use ($actorId, $board, $fields): void {
            $this->boards->update($board->id, $fields);

            $changes = [
                'renamed'    => $fields['name'] !== $board->name ? ['from' => $board->name, 'to' => $fields['name']] : null,
                'visibility' => $fields['visibility'] !== $board->visibility->value ? ['to' => $fields['visibility']] : null,
                'recolored'  => $fields['color'] !== $board->color ? ['to' => $fields['color']] : null,
                'described'  => $fields['description'] !== $board->description ? [] : null,
                'participation' => $fields['commenting'] !== $board->commenting->value || $fields['voting'] !== $board->voting->value
                    ? ['commenting' => $fields['commenting'], 'voting' => $fields['voting']] : null,
            ];

            foreach (array_filter($changes, static fn ($c) => $c !== null) as $change => $details) {
                $this->events->publish(new BoardChanged($board->id, $actorId, $change, $details, $this->clock->now()));
            }
        });
    }

    public function archiveBoard(int $actorId, BoardSummary $board, bool $archive = true): void
    {
        $this->gate->authorize($actorId, $board->id, Permission::ManageSettings);

        $this->transactions->run(function () use ($actorId, $board, $archive): void {
            $this->boards->setArchived($board->id, $archive);
            $this->events->publish(new BoardChanged($board->id, $actorId, $archive ? 'archived' : 'restored', [], $this->clock->now()));
        });
    }

    /* ---------- templates and copies ---------- */

    /** Marks a board as a template (or not). Templates show up when starting a new board. */
    public function setTemplate(int $actorId, BoardSummary $board, bool $isTemplate): void
    {
        $this->gate->authorize($actorId, $board->id, Permission::ManageSettings);
        $this->assertOpen($board);
        $this->boards->update($board->id, ['is_template' => $isTemplate ? 1 : 0]);
        $this->events->publish(new BoardChanged($board->id, $actorId, $isTemplate ? 'made_template' : 'unmade_template', [], $this->clock->now()));
    }

    /**
     * Templates the user may start from in this workspace: boards marked as
     * templates that they can see.
     *
     * @return list<BoardSummary>
     */
    public function templatesIn(int $userId, int $workspaceId): array
    {
        return array_values(array_filter($this->boardsInWorkspace($userId, $workspaceId), static fn (BoardSummary $b) => $b->isTemplate));
    }

    /**
     * A new, independent board from another one: its settings and open lists,
     * and (if asked) its open cards and its members. Nothing stays linked.
     */
    public function copyBoard(int $actorId, BoardSummary $source, string $name, bool $withCards, bool $withMembers): BoardSummary
    {
        $this->gate->authorize($actorId, $source->id, Permission::ViewBoard);
        $lists = $this->boards->lists($source->id);

        return $this->transactions->run(function () use ($actorId, $source, $name, $lists, $withCards, $withMembers): BoardSummary {
            $board = $this->createBoard($actorId, $source->workspaceId, $name, $source->visibility, $source->color);
            $this->boards->update($board->id, [
                'description' => $source->description,
                'commenting'  => $source->commenting->value,
                'voting'      => $source->voting->value,
            ]);

            $listMap = [];
            foreach ($lists as $list) {
                $listMap[$list->id] = $this->boards->createList($board->id, $list->name, $this->boards->listPositionAtEnd($board->id))->id;
            }
            $this->events->publish(new BoardCopied($source->id, $board->id, $actorId, $listMap, $withCards, $withMembers, $this->clock->now()));

            return $this->boards->find($board->id) ?? $board;
        });
    }

    /** A new board from a built-in template (see BuiltInTemplates). */
    public function createFromTemplate(int $actorId, int $workspaceId, string $key, string $name, BoardVisibility $visibility = BoardVisibility::Private): BoardSummary
    {
        $template = BuiltInTemplates::get($key) ?? throw new ValidationException(new ValidationResult(['template' => 'Choose a template.']));
        $t = fn (string $text): string => $this->translator?->t($text) ?? $text;
        $name = trim($name) !== '' ? $name : $t($template['name']);

        return $this->transactions->run(function () use ($actorId, $workspaceId, $key, $template, $name, $visibility, $t): BoardSummary {
            $board = $this->createBoard($actorId, $workspaceId, $name, $visibility, $template['color']);
            foreach ($template['lists'] as $list) {
                $this->boards->createList($board->id, $t($list), $this->boards->listPositionAtEnd($board->id));
            }
            $labels = array_map(static fn (array $label) => [$t($label[0]), $label[1]], $template['labels']);
            $this->events->publish(new BoardSeeded($board->id, $actorId, $key, $labels, $this->clock->now()));

            return $board;
        });
    }

    /* ---------- lists ---------- */

    public function addList(int $actorId, int $boardId, string $name): ListSummary
    {
        $this->gate->authorize($actorId, $boardId, Permission::EditCards);
        $this->assertOpen($this->boards->find($boardId));
        $this->assertValid(['name' => $name], ['name' => ['List name', 'required|max:100']]);

        return $this->transactions->run(function () use ($actorId, $boardId, $name): ListSummary {
            $list = $this->boards->createList($boardId, trim($name), $this->boards->listPositionAtEnd($boardId));
            $this->events->publish(new ListCreated($list->id, $boardId, $actorId, $list->name, $this->clock->now()));

            return $list;
        });
    }

    public function renameList(int $actorId, BoardSummary $board, int $listId, string $name): void
    {
        $this->gate->authorize($actorId, $board->id, Permission::EditCards);
        $this->assertOpen($board);
        $list = $this->listOrFail($listId, $board->id);
        $this->assertValid(['name' => $name], ['name' => ['List name', 'required|max:100']]);

        if (trim($name) === $list->name) {
            return;
        }

        $this->transactions->run(function () use ($actorId, $board, $list, $name): void {
            $this->boards->updateList($list->id, ['name' => trim($name)]);
            $this->events->publish(new ListChanged($list->id, $board->id, $actorId, 'renamed', ['from' => $list->name, 'to' => trim($name)], $this->clock->now()));
        });
    }

    /** Puts the list right after $afterListId (first when null). One row is written. */
    public function moveList(int $actorId, BoardSummary $board, int $listId, ?int $afterListId): void
    {
        $this->gate->authorize($actorId, $board->id, Permission::MoveCards);
        $this->assertOpen($board);
        $list = $this->listOrFail($listId, $board->id);

        $this->transactions->run(function () use ($actorId, $board, $list, $afterListId): void {
            try {
                $position = $this->boards->listPositionAfter($board->id, $afterListId, $list->id);
            } catch (InvalidArgumentException) {
                throw new ValidationException(new ValidationResult(['list' => 'That move is not allowed.']));
            }

            $this->boards->updateList($list->id, ['position' => $position]);
            $this->events->publish(new ListChanged($list->id, $board->id, $actorId, 'moved', ['name' => $list->name], $this->clock->now()));
        });
    }

    public function archiveList(int $actorId, BoardSummary $board, int $listId, bool $archive = true): void
    {
        $this->gate->authorize($actorId, $board->id, Permission::ArchiveItems);
        $this->assertOpen($board);
        $list = $this->listOrFail($listId, $board->id);

        $this->transactions->run(function () use ($actorId, $board, $list, $archive): void {
            $this->boards->setListArchived($list->id, $archive);
            $this->events->publish(new ListChanged($list->id, $board->id, $actorId, $archive ? 'archived' : 'restored', ['name' => $list->name], $this->clock->now()));
        });
    }

    /** An archived board is read-only until it's restored. */
    private function assertOpen(?BoardSummary $board): void
    {
        if ($board === null || $board->archived) {
            throw AccessDenied::forbidden();
        }
    }

    private function listOrFail(int $listId, int $boardId): ListSummary
    {
        // A list id from another board is treated exactly like a missing one (no IDOR).
        return $this->boards->findList($listId, $boardId) ?? throw AccessDenied::hidden();
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, array{0: string, 1: string}> $rules
     */
    private function assertValid(array $input, array $rules): void
    {
        $result = $this->validator->validate($input, $rules);

        if ($result->fails()) {
            throw new ValidationException($result);
        }
    }
}
