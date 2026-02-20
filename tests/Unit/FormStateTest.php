<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\FormState;
use App\Session\ArraySession;
use Tests\TestCase;

final class FormStateTest extends TestCase
{
    public function testRemembersErrorsAndInputForTheNextRequest(): void
    {
        $session = new ArraySession();
        (new FormState($session))->remember(['title' => 'Title is required.'], ['title' => '', 'notes' => 'Hi']);

        $next = new FormState($session);

        $this->assertSame('Title is required.', $next->error('title'));
        $this->assertSame('Hi', $next->old('notes'));
        $this->assertTrue($next->hasOld());

        // read again in the same request
        $this->assertSame(['title' => 'Title is required.'], $next->errors());

        // gone on the request after that
        $this->assertSame([], (new FormState($session))->errors());
    }

    public function testNeverKeepsPasswordsOrTheToken(): void
    {
        $session = new ArraySession();
        (new FormState($session))->remember([], ['email' => 'a@b.co', 'password' => 'secret', '_token' => 'x']);

        $next = new FormState($session);

        $this->assertSame('a@b.co', $next->old('email'));
        $this->assertSame('', $next->old('password'));
        $this->assertSame('', $next->old('_token'));
    }
}
