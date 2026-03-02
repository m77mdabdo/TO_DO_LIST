<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\FormState;
use App\Http\UrlGenerator;
use App\Security\Csrf;
use App\Session\ArraySession;
use App\Support\FrozenClock;
use App\View\ViewHelpers;
use DateTimeImmutable;
use Tests\TestCase;

final class ViewHelpersTest extends TestCase
{
    private ArraySession $session;
    private ViewHelpers $v;

    public function setUp(): void
    {
        $this->session = new ArraySession();
        $this->v = new ViewHelpers(
            new UrlGenerator('/app'),
            new Csrf($this->session),
            new FormState($this->session),
            $this->session,
            new FrozenClock('2026-02-10 09:00:00'),
        );
    }

    public function testCsrfFieldCarriesTheSessionToken(): void
    {
        $this->assertSame(
            '<input type="hidden" name="_token" value="' . $this->v->csrfToken() . '">',
            $this->v->csrfField(),
        );
    }

    public function testUrlsAreEscaped(): void
    {
        $this->assertSame('/app/search?q=a&amp;b', $this->v->url('search?q=a&b'));
    }

    public function testFlashMessagesShowOnce(): void
    {
        $this->session->flash('success', 'Task added.');

        $this->assertSame('Task added.', $this->v->flash('success'));
        $this->assertNull($this->v->flash('success'));
    }

    public function testDatesUseTheClock(): void
    {
        $this->assertSame('Good morning', $this->v->greeting());
        $this->assertSame('2h ago', $this->v->timeAgo(new DateTimeImmutable('2026-02-10 07:00:00')));
    }
}
