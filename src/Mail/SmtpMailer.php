<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Logger;
use RuntimeException;

/**
 * A small SMTP client (RFC 5321) for one message per connection:
 * EHLO, STARTTLS or implicit TLS, AUTH LOGIN, MAIL, RCPT, DATA, QUIT.
 * Enough for Gmail, Outlook or any provider's submission port, with no
 * library. The certificate is always verified.
 *
 * A failed send is logged without the password and not thrown: an email
 * that can't go out must not undo the action that caused it.
 */
final class SmtpMailer implements Mailer
{
    private const TIMEOUT = 15;

    /** @var resource|null */
    private $socket = null;

    /**
     * @param string $encryption "tls" (STARTTLS, usually port 587), "ssl" (implicit TLS, port 465) or "" (none)
     */
    public function __construct(
        private string $host,
        private int $port,
        private string $encryption,
        private string $username,
        #[\SensitiveParameter] private string $password,
        private string $fromAddress,
        private MimeFormat $format,
        private Logger $logger,
    ) {
    }

    public function send(Message $message): void
    {
        try {
            $this->deliver($message);
        } catch (RuntimeException $e) {
            $this->logger->error(new RuntimeException('Could not send "' . $message->subject . '" to ' . $message->to . ': ' . $e->getMessage(), 0, $e));
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    private function deliver(Message $message): void
    {
        $scheme = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $this->host]]);
        $socket = @stream_socket_client("$scheme://{$this->host}:{$this->port}", $errno, $error, self::TIMEOUT, STREAM_CLIENT_CONNECT, $context);

        if ($socket === false) {
            throw new RuntimeException("Connection to {$this->host}:{$this->port} failed: $error");
        }
        $this->socket = $socket;
        stream_set_timeout($socket, self::TIMEOUT);

        $this->expect(220);
        $this->command('EHLO ' . $this->clientName(), 250);

        if ($this->encryption === 'tls') {
            $this->command('STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('Could not start TLS.');
            }
            $this->command('EHLO ' . $this->clientName(), 250);
        }

        if ($this->username !== '') {
            $this->command('AUTH LOGIN', 334);
            $this->command(base64_encode($this->username), 334);
            $this->command(base64_encode($this->password), 235, 'AUTH LOGIN (password)');
        }

        $this->command('MAIL FROM:<' . $this->fromAddress . '>', 250);
        $this->command('RCPT TO:<' . $message->to . '>', [250, 251]);
        $this->command('DATA', 354);

        $body = str_replace(["\r\n", "\r"], "\n", $message->body);
        // Dot-stuffing: a line that starts with "." would otherwise end the message early.
        $body = (string) preg_replace('/^\./m', '..', $body);
        $data = implode("\r\n", [
            ...$this->format->headers(),
            'To: ' . $this->format->recipient($message),
            'Subject: ' . $this->format->subject($message),
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $this->messageIdHost() . '>',
            '',
            str_replace("\n", "\r\n", $body),
        ]);
        $this->command($data . "\r\n.", 250, 'message body');
        $this->command('QUIT', 221);
    }

    /** @param int|list<int> $expected */
    private function command(string $line, int|array $expected, ?string $logAs = null): void
    {
        if (fwrite($this->socketOrFail(), $line . "\r\n") === false) {
            throw new RuntimeException('Could not write ' . ($logAs ?? strtok($line, ' ')) . '.');
        }
        $this->expect($expected, $logAs ?? (string) strtok($line, ' '));
    }

    /** Reads a (possibly multi-line) reply and checks its code. @param int|list<int> $expected */
    private function expect(int|array $expected, string $after = 'connect'): void
    {
        $reply = '';
        do {
            $line = fgets($this->socketOrFail(), 1024);
            if ($line === false) {
                throw new RuntimeException("No reply after $after.");
            }
            $reply .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, (array) $expected, true)) {
            throw new RuntimeException("Unexpected reply after $after: " . trim($reply));
        }
    }

    /** @return resource */
    private function socketOrFail()
    {
        return $this->socket ?? throw new RuntimeException('Not connected.');
    }

    private function clientName(): string
    {
        $name = gethostname();

        return is_string($name) && preg_match('/^[A-Za-z0-9.-]+$/', $name) ? $name : 'localhost';
    }

    private function messageIdHost(): string
    {
        $at = strrchr($this->fromAddress, '@');

        return $at !== false ? substr($at, 1) : 'localhost';
    }
}
