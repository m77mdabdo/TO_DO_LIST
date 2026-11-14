<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application;

use App\Database\Transactions;
use App\Http\HttpException;
use App\Modules\Workspace\Contract\Events\MemberJoined;
use App\Modules\Workspace\Contract\Events\MemberRemoved;
use App\Modules\Workspace\Contract\Events\MemberRoleChanged;
use App\Modules\Workspace\Contract\Events\WorkspaceCreated;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceInfo;
use App\Modules\Workspace\Contract\WorkspaceMembership;
use App\Modules\Workspace\Contract\WorkspaceProvisioning;
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Modules\Workspace\Contract\WorkspaceSummary;
use App\Modules\Workspace\Domain\WorkspaceRepository;
use App\Session\Session;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use App\Validation\Validator;

/**
 * Companies and their workspaces: creating them, who is in them, which
 * one the user is currently working in, and changing members' roles.
 *
 * Role rules: owners and admins manage members. Owners may give any role;
 * admins may give member or guest and may not touch owners or admins.
 * Nobody changes their own role here, and a workspace always keeps an owner.
 *
 * Roles are cached per request in a hash map keyed "user:workspace", so a
 * page that checks the same membership many times hits the database once.
 */
final class WorkspaceService implements WorkspaceDirectory, WorkspaceProvisioning, WorkspaceMembership
{
    private const CURRENT_KEY = 'workspace';

    /** @var array<string, ?WorkspaceRole> */
    private array $roles = [];

    public function __construct(
        private WorkspaceRepository $workspaces,
        private Transactions $transactions,
        private EventBus $events,
        private Clock $clock,
        private Session $session,
        private Validator $validator,
    ) {
    }

    /** @param array<string, mixed> $input company_name */
    public function create(int $ownerId, array $input): WorkspaceSummary
    {
        $result = $this->validator->validate($input, ['company_name' => ['Company name', 'required|text|min:2|max:100']]);

        if ($result->fails()) {
            throw new ValidationException($result);
        }

        $workspaceId = $this->createWorkspace($ownerId, trim((string) $input['company_name']));
        $this->switchTo($ownerId, $workspaceId);

        return $this->current($ownerId);
    }

    public function ensureWorkspaceFor(int $userId, string $userName): int
    {
        return $this->workspaces->firstWorkspaceIdOf($userId)
            ?? $this->createWorkspace($userId, trim($userName) !== '' ? trim($userName) : 'My company');
    }

    public function switchTo(int $userId, int|string $workspace): bool
    {
        $summary = is_int($workspace)
            ? $this->firstMatching($userId, static fn (WorkspaceSummary $w) => $w->id === $workspace)
            : $this->workspaces->findForUser($workspace, $userId);

        if ($summary === null) {
            return false;
        }

        $this->session->put(self::CURRENT_KEY, $summary->publicId);
        $this->workspaces->markOpened($userId, $summary->id);

        return true;
    }

    public function workspacesOf(int $userId): array
    {
        return $this->workspaces->summariesOf($userId);
    }

    public function roleOf(int $userId, int $workspaceId): ?WorkspaceRole
    {
        $key = $userId . ':' . $workspaceId;

        if (!array_key_exists($key, $this->roles)) {
            $this->roles[$key] = $this->workspaces->role($userId, $workspaceId);
        }

        return $this->roles[$key];
    }

    public function findForUser(string $publicId, int $userId): ?WorkspaceSummary
    {
        return $this->workspaces->findForUser($publicId, $userId);
    }

    public function current(int $userId): ?WorkspaceSummary
    {
        $chosen = $this->session->get(self::CURRENT_KEY);

        if (is_string($chosen) && ($summary = $this->workspaces->findForUser($chosen, $userId)) !== null) {
            return $summary;
        }

        // A new session: reopen the workspace the user was last in, else their first one.
        $last = $this->workspaces->lastOpenedOf($userId);
        $summary = ($last !== null ? $this->workspaces->findForUser($last, $userId) : null)
            ?? $this->workspaces->summariesOf($userId)[0]
            ?? null;

        if ($summary !== null) {
            $this->session->put(self::CURRENT_KEY, $summary->publicId);
        }

        return $summary;
    }

    public function members(int $workspaceId): array
    {
        return $this->workspaces->members($workspaceId);
    }

    public function describe(int $workspaceId): ?WorkspaceInfo
    {
        return $this->workspaces->info($workspaceId);
    }

    public function canManageMembers(int $actorId, int $workspaceId): bool
    {
        return $this->roleOf($actorId, $workspaceId)?->managesAllBoards() ?? false;
    }

    public function mayGrant(int $actorId, int $workspaceId, WorkspaceRole $role): bool
    {
        return in_array($role, $this->roleOf($actorId, $workspaceId)?->grantable() ?? [], true);
    }

    public function join(int $workspaceId, int $userId, WorkspaceRole $role, int $actorId): WorkspaceRole
    {
        $current = $this->roleOf($userId, $workspaceId);

        if ($current === null) {
            $this->workspaces->addMember($workspaceId, $userId, $role);
            $this->events->publish(new MemberJoined($workspaceId, $userId, $role, $actorId, $this->clock->now()));
        } elseif ($role->rank() > $current->rank()) {
            $this->workspaces->setRole($workspaceId, $userId, $role);
            $this->events->publish(new MemberRoleChanged($workspaceId, $userId, $current, $role, $actorId, $this->clock->now()));
        } else {
            return $current;
        }

        $this->roles[$userId . ':' . $workspaceId] = $role;

        return $role;
    }

    public function open(int $userId, int $workspaceId): void
    {
        $this->switchTo($userId, $workspaceId);
    }

    /** @throws ValidationException|HttpException */
    public function changeRole(int $actorId, int $workspaceId, int $userId, string $role): void
    {
        $target = $this->manageable($actorId, $workspaceId, $userId);
        $new = WorkspaceRole::tryFrom($role);

        if ($new === null || !$this->mayGrant($actorId, $workspaceId, $new)) {
            $this->fail('role', 'You cannot give that role.');
        }
        if ($new === $target) {
            return;
        }

        $this->transactions->run(function () use ($workspaceId, $userId, $target, $new, $actorId): void {
            if ($target === WorkspaceRole::Owner && $this->workspaces->ownerCount($workspaceId) <= 1) {
                $this->fail('role', 'A workspace needs at least one owner.');
            }
            $this->workspaces->setRole($workspaceId, $userId, $new);
            $this->roles[$userId . ':' . $workspaceId] = $new;
            $this->events->publish(new MemberRoleChanged($workspaceId, $userId, $target, $new, $actorId, $this->clock->now()));
        });
    }

    /** @throws ValidationException|HttpException */
    public function removeMember(int $actorId, int $workspaceId, int $userId): void
    {
        $target = $this->manageable($actorId, $workspaceId, $userId);
        $this->remove($workspaceId, $userId, $target, $actorId);
    }

    /** The user leaves the workspace themselves. The last owner can't. */
    public function leave(int $userId, int $workspaceId): void
    {
        $role = $this->roleOf($userId, $workspaceId) ?? throw HttpException::notFound();
        $this->remove($workspaceId, $userId, $role, $userId);

        if ($this->session->get(self::CURRENT_KEY) !== null) {
            $this->session->forget(self::CURRENT_KEY);
        }
    }

    private function remove(int $workspaceId, int $userId, WorkspaceRole $role, int $actorId): void
    {
        $this->transactions->run(function () use ($workspaceId, $userId, $role, $actorId): void {
            if ($role === WorkspaceRole::Owner && $this->workspaces->ownerCount($workspaceId) <= 1) {
                $this->fail('member', 'A workspace needs at least one owner.');
            }
            $this->workspaces->removeMember($workspaceId, $userId);
            $this->roles[$userId . ':' . $workspaceId] = null;
            $this->events->publish(new MemberRemoved($workspaceId, $userId, $role, $actorId, $this->clock->now()));
        });
    }

    /** The target's role, after checking the actor may manage them. */
    private function manageable(int $actorId, int $workspaceId, int $userId): WorkspaceRole
    {
        $actor = $this->roleOf($actorId, $workspaceId) ?? throw HttpException::notFound();
        $target = $this->roleOf($userId, $workspaceId) ?? throw HttpException::notFound();

        if ($actorId === $userId) {
            $this->fail('member', 'You cannot change your own role.');
        }
        if (!$actor->managesAllBoards() || !$actor->outranksForManagement($target)) {
            throw new HttpException(403, 'You do not have permission to do that.');
        }

        return $target;
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }

    private function createWorkspace(int $ownerId, string $companyName): int
    {
        return $this->transactions->run(function () use ($ownerId, $companyName): int {
            ['companyId' => $companyId, 'workspaceId' => $workspaceId] = $this->workspaces->createCompany($companyName, $companyName, $ownerId);
            $this->roles[$ownerId . ':' . $workspaceId] = WorkspaceRole::Owner;
            $this->events->publish(new WorkspaceCreated($workspaceId, $companyId, $ownerId, $companyName, $this->clock->now()));

            return $workspaceId;
        });
    }

    /** @param callable(WorkspaceSummary): bool $match */
    private function firstMatching(int $userId, callable $match): ?WorkspaceSummary
    {
        foreach ($this->workspaces->summariesOf($userId) as $summary) {
            if ($match($summary)) {
                return $summary;
            }
        }

        return null;
    }
}
