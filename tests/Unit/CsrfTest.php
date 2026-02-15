<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Security\Csrf;
use App\Session\ArraySession;
use Tests\TestCase;

final class CsrfTest extends TestCase
{
    public function testTokenStaysTheSameForTheSession(): void
    {
        $csrf = new Csrf(new ArraySession());

        $this->assertSame(64, strlen($csrf->token()));
        $this->assertSame($csrf->token(), $csrf->token());
    }

    public function testOnlyTheSessionTokenMatches(): void
    {
        $csrf = new Csrf(new ArraySession());

        $this->assertTrue($csrf->matches($csrf->token()));
        $this->assertFalse($csrf->matches('forged'));
        $this->assertFalse($csrf->matches(null));
        $this->assertFalse($csrf->matches(['array']));
    }

    public function testRefreshIssuesANewToken(): void
    {
        $csrf = new Csrf(new ArraySession());
        $old = $csrf->token();
        $csrf->refresh();

        $this->assertFalse($csrf->matches($old));
    }
}
