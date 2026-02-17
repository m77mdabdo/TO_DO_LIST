<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Response;
use Tests\TestCase;

final class ResponseTest extends TestCase
{
    public function testJsonKeepsArabicReadable(): void
    {
        $response = Response::json(['title' => 'مهمة'], 201);

        $this->assertSame('{"title":"مهمة"}', $response->body());
        $this->assertSame(201, $response->status());
        $this->assertSame('application/json; charset=utf-8', $response->header('content-type'));
    }

    public function testRedirectUsesSeeOther(): void
    {
        $response = Response::redirect('/login');

        $this->assertSame(303, $response->status());
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testWithHeaderReturnsACopy(): void
    {
        $response = Response::html('<p>Hi</p>');
        $copy = $response->withHeader('X-Frame-Options', 'DENY');

        $this->assertSame('DENY', $copy->header('X-Frame-Options'));
        $this->assertNull($response->header('X-Frame-Options'));
    }
}
