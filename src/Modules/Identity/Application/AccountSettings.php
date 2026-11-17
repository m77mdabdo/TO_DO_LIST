<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\I18n\Translator;
use App\Database\Transactions;
use App\Modules\Identity\Contract\Events\AccountDeleted;
use App\Modules\Identity\Domain\PasswordHasher;
use App\Modules\Identity\Domain\UserRepository;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use App\Validation\Validator;

/** The person's own settings, and deleting their account. */
final class AccountSettings
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private Validator $validator,
        private Translator $translator,
        private WorkspaceDirectory $workspaces,
        private DeviceSessions $devices,
        private Transactions $transactions,
        private EventBus $events,
        private Clock $clock,
        private \App\Shared\Export\PersonalData $personal,
    ) {
    }

    /** @param array<string, mixed> $input name, locale ('' = the app default) */
    public function updateProfile(int $userId, array $input): void
    {
        $result = $this->validator->validate($input, ['name' => ['Name', 'required|text|min:2|max:60']]);
        $locale = (string) ($input['locale'] ?? '');
        if ($locale !== '' && !isset(Translator::SUPPORTED[$locale])) {
            $result = $result->with('locale', 'Choose a language from the list.');
        }
        if ($result->fails()) {
            throw new ValidationException($result);
        }
        $this->users->updateProfile($userId, trim((string) $input['name']), $locale !== '' ? $locale : null);
        if ($locale !== '') {
            $this->translator->setLocale($locale);
        }
    }

    /** @return array<string, mixed> everything the app holds about the person, for download */
    public function export(int $userId): array
    {
        return ['format' => 'todo-personal-data/1', 'exported_at' => $this->clock->now()->format(DATE_ATOM)] + $this->personal->forUser($userId);
    }

    public function locale(int $userId): ?string
    {
        return $this->users->locale($userId);
    }

    public function passwordMatches(int $userId, string $password): bool
    {
        $user = $this->users->find($userId);
        $credentials = $user !== null ? $this->users->credentials($user->email) : null;

        return $credentials !== null && $this->hasher->verify($password, $credentials->passwordHash);
    }

    /** Throws when the password is wrong (field: password). */
    public function assertPassword(int $userId, string $password): void
    {
        if (!$this->passwordMatches($userId, $password)) {
            throw new ValidationException(new ValidationResult(['password' => 'That password is not right.']));
        }
    }

    /**
     * Workspaces that would be left without an owner. They must be handed
     * over first.
     *
     * @return list<\App\Modules\Workspace\Contract\WorkspaceSummary>
     */
    public function blockers(int $userId): array
    {
        return $this->workspaces->soleOwnerOf($userId);
    }

    /**
     * Empties the account: personal data removed, memberships ended, every
     * device signed out. Cards and comments stay, credited to "Deleted user".
     */
    public function delete(int $userId, string $password): void
    {
        $this->assertPassword($userId, $password);
        if ($this->blockers($userId) !== []) {
            throw new ValidationException(new ValidationResult(['delete' => 'Hand over the workspaces you own alone before deleting your account.']));
        }
        $user = $this->users->find($userId) ?? throw new \LogicException('No such user.');
        $this->transactions->run(function () use ($user): void {
            $this->events->publish(new AccountDeleted($user->id, $user->email, $this->clock->now()));
            $this->personal->erase($user->id);
            $this->users->anonymize($user->id, $this->hasher->hash(bin2hex(random_bytes(32))));
            $this->devices->endAll($user->id);
        });
    }
}
