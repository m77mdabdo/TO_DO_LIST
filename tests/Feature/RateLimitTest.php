<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Security\RateLimitRule;
use App\Security\RateLimits;
use App\Support\Clock;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class RateLimitTest extends TestCase
{
    public function testEveryLimitedEndpointHasARule(): void
    {
        $names = array_keys((new App())->container->get(RateLimits::class)->all());

        foreach (['login', 'register', 'password-reset-ip', 'password-reset-email', 'password-reset-submit', 'verification-resend', 'invitations', 'api-token', 'api-ip'] as $rule) {
            $this->assertTrue(in_array($rule, $names, true), "Rule $rule exists");
        }
    }

    public function testRulePathsMatchSegments(): void
    {
        $api = new RateLimitRule('api', '*', '/api/**', 1, 1);
        $invites = new RateLimitRule('inv', 'POST', '/workspaces/*/invitations', 1, 1);

        $this->assertTrue($api->matches('GET', '/api/v1/boards'));
        $this->assertTrue($api->matches('POST', '/api'));
        $this->assertFalse($api->matches('GET', '/apis'));
        $this->assertTrue($invites->matches('POST', '/workspaces/01HX/invitations'));
        $this->assertFalse($invites->matches('GET', '/workspaces/01HX/invitations'));
        $this->assertFalse($invites->matches('POST', '/workspaces/a/b/invitations'));
    }

    public function testTooManyLoginsGet429UntilTheWindowPasses(): void
    {
        $app = new App(config: ['rate_limits' => ['login' => ['max' => 3, 'seconds' => 60]]]);

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(302, $this->from($app, '10.0.0.1', '/login', ['email' => 'a@example.com', 'password' => 'x'])->status);
        }
        $refused = $this->from($app, '10.0.0.1', '/login', ['email' => 'a@example.com', 'password' => 'x']);

        $this->assertSame(429, $refused->status);
        $this->assertSame('60', $refused->header('Retry-After'));
        $this->assertTrue(str_contains($refused->body, 'Too many requests'));
        $this->assertSame(302, $this->from($app, '10.0.0.2', '/login', ['email' => 'a@example.com', 'password' => 'x'])->status, 'Another IP has its own count.');

        $app->container->get(Clock::class)->travel('+61 seconds');
        $this->assertSame(302, $this->from($app, '10.0.0.1', '/login', ['email' => 'a@example.com', 'password' => 'x'])->status);
    }

    public function testResetRequestsAreLimitedPerEmailEvenFromManyAddresses(): void
    {
        $app = new App();

        foreach (['10.1.0.1', '10.1.0.2', '10.1.0.3'] as $ip) {
            $this->assertSame(302, $this->from($app, $ip, '/forgot-password', ['email' => 'sara@example.com'])->status);
        }

        $this->assertSame(429, $this->from($app, '10.1.0.4', '/forgot-password', ['email' => 'Sara@Example.com'])->status, 'Same address, other case.');
        $this->assertSame(302, $this->from($app, '10.1.0.4', '/forgot-password', ['email' => 'mona@example.com'])->status);
    }

    public function testTheApiAnswersJsonWithLimitHeaders(): void
    {
        $f = new BoardFixture(new App(config: ['rate_limits' => ['api-token' => ['max' => 2, 'seconds' => 60]]]));
        $f->app->post('/integrations/tokens', ['name' => 'Script', 'scope' => 'read', 'days' => '90', 'boards' => [$f->board->publicId]]);
        preg_match('#(tdb_[0-9a-f]{40})#', $f->app->page('/integrations'), $m);
        $token = $m[1];

        $first = $this->api($f->app, $token);
        $this->assertSame(200, $first->status);
        $this->assertSame('2', $first->header('X-RateLimit-Limit'));
        $this->assertSame('1', $first->header('X-RateLimit-Remaining'));
        $this->assertSame(200, $this->api($f->app, $token)->status);

        $refused = $this->api($f->app, $token);
        $this->assertSame(429, $refused->status);
        $this->assertSame('application/json; charset=utf-8', $refused->header('Content-Type'));
        $body = json_decode($refused->body, true);
        $this->assertSame(['ok' => false, 'error' => 'rate_limited'], ['ok' => $body['ok'], 'error' => $body['error']]);
        $this->assertSame(60, $body['retry_after']);

        $this->assertSame(401, $this->api($f->app, 'tdb_' . str_repeat('0', 40))->status, 'Another token is counted on its own.');
    }

    public function testRefusedRequestsAreWrittenToTheSecurityLog(): void
    {
        $app = new App(config: ['rate_limits' => ['verification-resend' => ['max' => 1, 'seconds' => 900]]]);
        $id = $app->signUp('Sara', 'sara@example.com', confirm: false);

        $app->post('/email/verification');
        $this->assertSame(429, $app->post('/email/verification')->status);

        $row = $app->pdo()->query("SELECT user_id, detail FROM security_events WHERE type = 'rate_limited'")->fetch();
        $this->assertSame(['user_id' => $id, 'detail' => 'verification-resend'], $row);
        $this->assertSame(0, (int) $app->pdo()->query("SELECT COUNT(*) FROM rate_limit_hits WHERE bucket LIKE '%sara%' OR bucket LIKE '%10.0.0%'")->fetchColumn(), 'Buckets are hashed.');
    }

    /** @param array<string, string> $body */
    private function from(App $app, string $ip, string $path, array $body): Response
    {
        $body['_token'] = $app->container->get(Csrf::class)->token();

        return $app->send(new Request('POST', $path, [], $body, [], $ip));
    }

    private function api(App $app, string $token): Response
    {
        return $app->send(new Request('GET', '/api/v1/me', [], [], ['authorization' => 'Bearer ' . $token], '10.0.0.1'));
    }
}
