<?php

declare(strict_types=1);

namespace App\Modules\Access\Application;

use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Access\Contract\Events\BoardMemberChanged;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;

/**
 * Who is on a board, with which role. Only people already in the board's
 * workspace can be added (outsiders come in through an invitation), and a
 * guest can't be made a board manager.
 */
final class BoardMemberService
{
    public function __construct(
        private Gate $gate,
        private BoardMembers $members,
        private WorkspaceDirectory $workspaces,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function set(int $actorId, BoardSummary $board, int $userId, string $role): void
    {
        $this->authorize($actorId, $board);
        $new = BoardRole::tryFrom($role) ?? $this->fail('role', 'Choose a board role.');
        $workspaceRole = $this->workspaces->roleOf($userId, $board->workspaceId) ?? $this->fail('user_id', 'Choose someone from this workspace.');

        if ($userId === $actorId) {
            $this->fail('user_id', 'You cannot change your own role.');
        }
        if ($workspaceRole === WorkspaceRole::Guest && $new === BoardRole::Manager) {
            $this->fail('role', 'A guest cannot manage a board.');
        }

        $old = $this->members->role($board->id, $userId);
        if ($old === $new) {
            return;
        }

        $this->members->add($board->id, $userId, $new);
        $this->gate->forget($userId, $board->id);
        $this->events->publish(new BoardMemberChanged($board->id, $board->workspaceId, $userId, $actorId, $old, $new, $this->clock->now()));
    }

    public function remove(int $actorId, BoardSummary $board, int $userId): void
    {
        $this->authorize($actorId, $board);
        $old = $this->members->role($board->id, $userId) ?? throw AccessDenied::hidden();

        if ($userId === $actorId) {
            $this->fail('user_id', 'You cannot change your own role.');
        }

        $this->members->remove($board->id, $userId);
        $this->gate->forget($userId, $board->id);
        $this->events->publish(new BoardMemberChanged($board->id, $board->workspaceId, $userId, $actorId, $old, null, $this->clock->now()));
    }

    /**
     * The board's people for its members page: explicit members first, then
     * those who get a role from the workspace, then who could be added.
     *
     * @return array{explicit: array<int, BoardRole>, implicit: array<int, BoardRole>, addable: list<int>, workspaceRoles: array<int, WorkspaceRole>}
     */
    public function overview(BoardSummary $board): array
    {
        $explicit = $this->members->membersOf($board->id);
        $everyone = $this->gate->peopleOn($board->id);
        $workspaceRoles = [];
        foreach ($this->workspaces->members($board->workspaceId) as ['userId' => $id, 'role' => $role]) {
            $workspaceRoles[$id] = $role;
        }

        return [
            'explicit'       => $explicit,
            'implicit'       => array_diff_key($everyone, $explicit),
            'addable'        => array_values(array_diff(array_keys($workspaceRoles), array_keys($explicit))),
            'workspaceRoles' => $workspaceRoles,
        ];
    }

    private function authorize(int $actorId, BoardSummary $board): void
    {
        $this->gate->authorize($actorId, $board->id, Permission::ManageMembers);

        if ($board->archived) {
            throw AccessDenied::forbidden(); // archived boards are read-only
        }
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
