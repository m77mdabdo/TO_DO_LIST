<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Clock;

/**
 * The default for local work: each email is written to storage/mail as an
 * .eml file you can open in any mail app, and nothing leaves the machine.
 */
final class LogMailer implements Mailer
{
    public function __construct(private string $dir, private MimeFormat $format, private Clock $clock)
    {
    }

    public function send(Message $message): void
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }

        $lines = [
            ...$this->format->headers(),
            'To: ' . $this->format->recipient($message),
            'Subject: ' . $this->format->subject($message),
            'Date: ' . $this->clock->now()->format(DATE_RFC2822),
            '',
            $message->body,
        ];

        $name = $this->clock->now()->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
        file_put_contents($this->dir . '/' . $name, implode("\r\n", $lines));
    }
}
