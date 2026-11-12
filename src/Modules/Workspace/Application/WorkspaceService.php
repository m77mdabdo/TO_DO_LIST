<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application;

use App\Database\Transactions;
use App\Modules\Workspace\Contract\Events\WorkspaceCreated;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceProvisioning;
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Modules\Workspace\Contract\WorkspaceSummary;
use App\Modules\Workspace\Domain\WorkspaceRepository;
use App\Session\Session;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\Validator;

/**
 * Companies and their workspaces: creating them, who is in them, and which
 * one the user is currently working in.
 *
 * Roles are cached per request in a hash map keyed "user:workspace", so a
 * page that checks the same membership many times hits the database once.
 */
final class WorkspaceService implements WorkspaceDirectory, WorkspaceProvisioning
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
