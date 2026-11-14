<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Logger;

/**
 * Sends through PHP's mail(), i.e. the server's sendmail or the SMTP
 * settings in php.ini (XAMPP: sendmail.ini). A failed send is logged, not
 * thrown: an email that can't go out must not undo the action that caused it.
 */
final class NativeMailer implements Mailer
{
    public function __construct(private MimeFormat $format, private Logger $logger)
    {
    }

    public function send(Message $message): void
    {
        $sent = mail(
            $this->format->recipient($message),
            $this->format->subject($message),
            str_replace("\n", "\r\n", str_replace("\r\n", "\n", $message->body)),
            implode("\r\n", $this->format->headers()),
        );

        if (!$sent) {
            $this->logger->error(new \RuntimeException('Could not send "' . $message->subject . '" to ' . $message->to . ' with mail().'));
        }
    }
}
