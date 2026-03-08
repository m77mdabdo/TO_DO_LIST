<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\HttpException;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Session\ArraySession;
use Tests\TestCase;

final class CsrfAndHeadersMiddlewareTest extends TestCase
{
    private Csrf $csrf;
    private VerifyCsrfToken $middleware;

    public function setUp(): void
    {
        $this->csrf = new Csrf(new ArraySession());
        $this->middleware = new VerifyCsrfToken($this->csrf);
    }

    public function testGetRequestsPassWithoutAToken(): void
    {
        $this->assertSame(200, $this->run(new Request('GET', '/'))->status());
    }

    public function testPostNeedsTheTokenInTheBodyOrHeader(): void
    {
        $this->assertSame(200, $this->run(new Request('POST', '/', body: ['_token' => $this->csrf->token()]))->status());
        $this->assertSame(200, $this->run(new Request('POST', '/', headers: ['x-csrf-token' => $this->csrf->token()]))->status());

        $this->assertThrows(HttpException::class, fn () => $this->run(new Request('POST', '/', body: ['_token' => 'forged'])));
        $this->assertThrows(HttpException::class, fn () => $this->run(new Request('POST', '/')));
    }

    public function testSecurityHeadersAreAdded(): void
    {
        $response = (new SecurityHeaders())->process(new Request('GET', '/'), fn () => Response::html('ok'));

        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertTrue(str_contains((string) $response->header('Content-Security-Policy'), "script-src 'self'"));
    }

    private function run(Request $request): Response
    {
        return $this->middleware->process($request, fn () => Response::html('ok'));
    }
}
