<?php

declare(strict_types=1);

namespace App\Mail;

/** Turns a Message into RFC 5322 headers and a body, UTF-8 safe for Arabic. */
final class MimeFormat
{
    public function __construct(private string $fromAddress, private string $fromName)
    {
    }

    public function recipient(Message $message): string
    {
        return $message->toName === '' ? $message->to : self::encode($message->toName) . ' <' . $message->to . '>';
    }

    public function subject(Message $message): string
    {
        return self::encode($message->subject);
    }

    /** @return list<string> */
    public function headers(): array
    {
        return [
            'From: ' . self::encode($this->fromName) . ' <' . $this->fromAddress . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
    }

    private static function encode(string $text): string
    {
        return preg_match('/[^\x20-\x7E]/', $text) ? '=?UTF-8?B?' . base64_encode($text) . '?=' : $text;
    }
}
