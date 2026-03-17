<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Request;
use Tests\TestCase;

final class RequestTest extends TestCase
{
    public function testBuildsThePathRelativeToTheAppFolder(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'post', 'REQUEST_URI' => '/todolist/tasks/7/move?x=1', 'HTTP_ACCEPT' => 'application/json'];
        $_GET = ['x' => '1'];
        $_POST = ['status' => ' done '];

        $request = Request::fromGlobals('/todolist');

        $this->assertSame('POST', $request->method);
        $this->assertSame('/tasks/7/move', $request->path);
        $this->assertSame('done', $request->input('status'));
        $this->assertTrue($request->wantsJson());
    }

    public function testIndexPhpPrefixIsIgnored(): void
    {
        $_SERVER = ['REQUEST_URI' => '/index.php/login'];

        $this->assertSame('/login', Request::fromGlobals('')->path);
    }

    public function testArraysNeverSneakInAsText(): void
    {
        $request = new Request('POST', '/', [], ['title' => ['a', 'b'], 'password' => ' keep spaces ']);

        $this->assertSame('', $request->input('title'));
        $this->assertSame(' keep spaces ', $request->raw('password'));
        $this->assertSame(['title' => ''], $request->only(['title']));
    }
}
