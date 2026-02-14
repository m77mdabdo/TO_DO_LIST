<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Session\ArraySession;
use Tests\TestCase;

final class SessionTest extends TestCase
{
    public function testStoresAndForgetsValues(): void
    {
        $session = new ArraySession();
        $session->set('user_id', 7);

        $this->assertSame(7, $session->get('user_id'));

        $session->forget('user_id');
        $this->assertNull($session->get('user_id'));
    }

    public function testFlashValuesCanOnlyBeReadOnce(): void
    {
        $session = new ArraySession();
        $session->flash('success', 'Saved.');

        $this->assertSame('Saved.', $session->pull('success'));
        $this->assertNull($session->pull('success'));
    }

    public function testInvalidateClearsEverything(): void
    {
        $session = new ArraySession(['user_id' => 3]);
        $session->invalidate();

        $this->assertNull($session->get('user_id'));
    }
}
