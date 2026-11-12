<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * One plain-text email. Line breaks are stripped from the address, name and
 * subject when the message is built, so user input can never add headers.
 */
final class Message
{
    public readonly string $to;
    public readonly string $toName;
    public readonly string $subject;

    public function __construct(string $to, string $toName, string $subject, public readonly string $body)
    {
        $this->to = self::oneLine($to);
        if (filter_var($this->to, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Not an email address: ' . $this->to);
        }
        $this->toName = self::oneLine($toName);
        $this->subject = self::oneLine($subject);
    }

    private static function oneLine(string $value): string
    {
        return trim((string) preg_replace('/[\r\n\0]+/', ' ', $value));
    }
}
