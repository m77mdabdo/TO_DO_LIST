<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Security\Csrf;
use App\Session\ArraySession;
use Tests\TestCase;

final class CsrfTest extends TestCase
{
    public function testTokenIsStablePerSessionAndChecked(): void
    {
        $csrf = new Csrf(new ArraySession());
        $token = $csrf->token();

        $this->assertSame(64, strlen($token));
        $this->assertSame($token, $csrf->token());
        $this->assertTrue($csrf->isValid($token));
        $this->assertFalse($csrf->isValid('guess'));
        $this->assertFalse($csrf->isValid(null));
        $this->assertFalse($csrf->isValid([$token]));
    }

    public function testEachSessionGetsItsOwnToken(): void
    {
        $this->assertFalse((new Csrf(new ArraySession()))->token() === (new Csrf(new ArraySession()))->token());
    }
}
