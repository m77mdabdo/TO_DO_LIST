<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\DotEnv;
use App\Mail\Message;
use App\Mail\MimeFormat;
use Tests\TestCase;

final class MailTest extends TestCase
{
    public function testHeaderFieldsCannotCarryExtraHeaders(): void
    {
        $message = new Message('sara@example.com', "Sara\r\nBcc: x@evil.test", "Hi\nX-Evil: 1", 'Body');

        $this->assertSame('Sara Bcc: x@evil.test', $message->toName);
        $this->assertSame('Hi X-Evil: 1', $message->subject);
        $this->assertThrows(\InvalidArgumentException::class, fn () => new Message("a@b.test\r\nBcc: x@evil.test", '', 'Hi', ''));
    }

    public function testArabicNamesAndSubjectsAreEncoded(): void
    {
        $format = new MimeFormat('team@todo.test', 'To Do List');
        $message = new Message('sara@example.com', 'سارة', 'دعوة', 'Body');

        $this->assertSame('=?UTF-8?B?' . base64_encode('سارة') . '?= <sara@example.com>', $format->recipient($message));
        $this->assertSame('=?UTF-8?B?' . base64_encode('دعوة') . '?=', $format->subject($message));
        $this->assertSame('From: To Do List <team@todo.test>', $format->headers()[0]);
    }

    public function testDotEnvReadsValuesWithoutOverridingTheServer(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "# comment\nTODO_TEST_A=plain # note\nTODO_TEST_B=\"To Do List\"\nlower=ignored\nTODO_TEST_C=from-file\n");
        putenv('TODO_TEST_C=from-server');

        DotEnv::load($file);
        unlink($file);

        $this->assertSame('plain', getenv('TODO_TEST_A'));
        $this->assertSame('To Do List', getenv('TODO_TEST_B'));
        $this->assertSame('from-server', getenv('TODO_TEST_C'));
        $this->assertFalse(getenv('lower'));

        putenv('TODO_TEST_A'); putenv('TODO_TEST_B'); putenv('TODO_TEST_C');
    }
}
