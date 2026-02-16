<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Request;
use Tests\TestCase;

final class RequestTest extends TestCase
{
    public function testNormalisesMethodAndPath(): void
    {
        $request = new Request('post', 'tasks/4/');

        $this->assertSame('POST', $request->method());
        $this->assertTrue($request->isPost());
        $this->assertSame('/tasks/4', $request->path());
        $this->assertSame('/', (new Request('GET', ''))->path());
    }

    public function testInputIsTrimmedAndNeverAnArray(): void
    {
        $request = new Request('POST', '/', ['q' => ' from query '], [
            'title'    => '  Buy milk ',
            'tags'     => ['a', 'b'],
            'password' => ' secret ',
        ]);

        $this->assertSame('Buy milk', $request->input('title'));
        $this->assertSame('', $request->input('tags'));
        $this->assertSame('from query', $request->input('q'));
        $this->assertSame(' secret ', $request->raw('password'));
        $this->assertSame(['title' => 'Buy milk', 'missing' => ''], $request->only(['title', 'missing']));
    }

    public function testDetectsJsonRequests(): void
    {
        $this->assertTrue((new Request('GET', '/', headers: ['accept' => 'application/json']))->wantsJson());
        $this->assertFalse((new Request('GET', '/'))->wantsJson());
    }

    public function testAttributesDoNotChangeTheOriginal(): void
    {
        $request = new Request('GET', '/');
        $copy = $request->withAttribute('id', '5');

        $this->assertSame('5', $copy->attribute('id'));
        $this->assertNull($request->attribute('id'));
    }
}
