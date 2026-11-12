<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Sends email. Which transport is used is a setting (config "mail.driver"),
 * so modules only ever see this interface.
 */
interface Mailer
{
    public function send(Message $message): void;
}
