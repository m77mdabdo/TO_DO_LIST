<?php

declare(strict_types=1);

namespace App\Mail;

/** Keeps sent messages in memory. Used by the tests ("mail.driver" = "memory"). */
final class MemoryMailer implements Mailer
{
    /** @var list<Message> */
    public array $sent = [];

    public function send(Message $message): void
    {
        $this->sent[] = $message;
    }
}
