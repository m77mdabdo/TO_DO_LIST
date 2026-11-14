<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application;

use App\Core\Config;
use App\I18n\Translator;
use App\Mail\Mailer;
use App\Mail\Message;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Notification\Contract\Messages;
use App\Modules\Notification\Domain\NotificationStore;

/**
 * Delivers one event to the people it concerns: a row in each person's
 * bell and, for important kinds, an email. Never to the person who did it,
 * never twice for the same event (the dedupe key), and never about a board
 * the person can no longer see.
 */
final class Notifier
{
    public function __construct(
        private NotificationStore $store,
        private AccessGate $gate,
        private UserDirectory $users,
        private Mailer $mailer,
        private Translator $translator,
        private Config $config,
    ) {
    }

    /**
     * @param list<int> $recipients
     * @param array<string, string> $data
     * @return int how many people were notified
     */
    public function notify(array $recipients, string $type, ?int $actorId, ?int $boardId, ?int $cardId, string $subject, array $data, string $link, string $dedupeKey): int
    {
        $recipients = array_values(array_unique(array_filter(
            $recipients,
            fn (int $id) => $id !== $actorId && ($boardId === null || $this->gate->can($id, $boardId, Permission::ViewBoard)),
        )));
        if ($recipients === []) {
            return 0;
        }

        $emailed = in_array($type, Messages::EMAILED, true);
        // One query for the actor and every recipient.
        $people = $emailed ? $this->users->findMany([...$recipients, ...($actorId !== null ? [$actorId] : [])]) : [];
        $count = 0;

        foreach ($recipients as $userId) {
            if (!$this->store->add($userId, $type, $actorId, $boardId, $cardId, $subject, $data, $link, $dedupeKey)) {
                continue;
            }
            $count++;

            if ($emailed && isset($people[$userId]) && $this->store->emailEnabled($userId)) {
                $this->email($people[$userId]->email, $people[$userId]->name, $type, $actorId !== null ? ($people[$actorId]->name ?? '') : '', $subject, $data, $link);
            }
        }

        return $count;
    }

    /** @param array<string, string> $data */
    private function email(string $to, string $name, string $type, string $actor, string $subject, array $data, string $link): void
    {
        $replace = [':actor' => $actor !== '' ? $actor : $this->translator->t('Someone'), ':subject' => $subject];
        foreach ($data as $key => $value) {
            $replace[':' . $key] = in_array($key, ['role'], true) ? $this->translator->t($value) : $value;
        }
        $sentence = strtr($this->translator->t(Messages::sentence($type)), $replace);

        $body = implode("\n", [
            $sentence,
            ...(isset($data['excerpt']) ? ['', '"' . $data['excerpt'] . '"'] : []),
            '',
            rtrim((string) $this->config->get('url', ''), '/') . $link,
            '',
            $this->translator->t('You get these emails for mentions, assignments and due dates. Turn them off from your notifications page.'),
        ]);

        $this->mailer->send(new Message($to, $name, $sentence, $body));
    }
}
