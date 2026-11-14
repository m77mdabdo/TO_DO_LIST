<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Request;
use App\Http\Response;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Integration\Application\Webhooks;
use App\Modules\Integration\Domain\UrlGuard;
use App\Modules\Integration\Domain\WebhookTransport;
use App\Support\Clock;
use App\Support\FrozenClock;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class IntegrationTest extends TestCase
{
    private BoardFixture $f;
    /** @var list<array{url: string, ip: string, headers: array<string, string>, body: string}> */
    private array $sent = [];
    private int $answer = 200;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App(), BoardVisibility::Private);
        $this->f->app->container->singleton(UrlGuard::class, fn () => new UrlGuard(static fn (string $host): array => match ($host) {
            'hooks.example.com'    => ['93.184.216.34'],
            'internal.example.com' => ['10.0.0.5'],
            default                => [],
        }));
        $test = $this;
        $this->f->app->container->singleton(WebhookTransport::class, fn () => new class ($test) implements WebhookTransport {
            public function __construct(private IntegrationTest $test)
            {
            }

            public function post(string $url, string $ip, array $headers, string $body): array
            {
                return $this->test->record($url, $ip, $headers, $body);
            }
        });
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: ?int, error: string}
     */
    public function record(string $url, string $ip, array $headers, string $body): array
    {
        $this->sent[] = compact('url', 'ip', 'headers', 'body');

        return ['status' => $this->answer, 'error' => ''];
    }

    public function testATokenOnlyReachesItsBoardsAndScope(): void
    {
        $other = $this->f->app->container->get(BoardService::class)->createBoard($this->f->owner, $this->f->workspaceId, 'Other', BoardVisibility::Private);
        $read = $this->token('Reader', 'read');
        $write = $this->token('Writer', 'write');

        $boards = json_decode($this->api('GET', '/api/v1/boards', $read)->body, true)['boards'];
        $this->assertSame(['Launch'], array_column($boards, 'name'));
        $this->assertSame(['To do', 'Doing', 'Done'], array_column($boards[0]['lists'], 'name'));
        $this->assertSame(404, $this->api('GET', '/api/v1/boards/' . $other->publicId . '/cards', $read)->status, 'Not one of its boards.');

        $path = '/api/v1/boards/' . $this->f->board->publicId . '/cards';
        $this->assertSame(403, $this->api('POST', $path, $read, ['list' => 'To do', 'title' => 'Nope'])->status);
        $created = $this->api('POST', $path, $write, ['list' => 'to do', 'title' => 'From a script', 'due_date' => '2026-02-01']);
        $this->assertSame(201, $created->status, $created->body);
        $this->assertSame('From a script', json_decode($this->api('GET', $path, $read)->body, true)['cards'][0]['title']);
        $this->assertSame(422, $this->api('POST', $path, $write, ['list' => 'To do', 'title' => ''])->status);

        $this->assertSame(401, $this->api('GET', '/api/v1/boards', 'tdb_' . str_repeat('0', 40))->status);
        $this->assertSame(401, $this->api('GET', '/api/v1/boards', null)->status, 'The session cookie is not enough.');

        // Revoked, then expired.
        $this->f->app->post('/integrations/tokens/' . $this->f->app->pdo()->query("SELECT public_id FROM api_tokens WHERE name = 'Reader'")->fetchColumn() . '/revoke');
        $this->assertSame(401, $this->api('GET', '/api/v1/me', $read)->status);
        /** @var FrozenClock $clock */
        $clock = $this->f->app->container->get(Clock::class);
        $clock->travel('+91 days');
        $this->assertSame(401, $this->api('GET', '/api/v1/me', $write)->status);
    }

    public function testATokenNeverOutlivesItsOwnersAccess(): void
    {
        $mona = $this->f->colleague('mona@example.com');
        $this->f->app->container->get(\App\Modules\Access\Contract\BoardMembers::class)->add($this->f->board->id, $mona, \App\Modules\Access\Contract\BoardRole::Member);
        $this->f->app->loginAs('mona@example.com');
        $token = $this->token('Mine', 'write');
        $this->assertSame(200, $this->api('GET', '/api/v1/boards/' . $this->f->board->publicId . '/cards', $token)->status);

        $this->f->app->container->get(\App\Modules\Access\Contract\BoardMembers::class)->remove($this->f->board->id, $mona);
        $this->f->app->container->get(\App\Modules\Access\Contract\AccessGate::class)->forget($mona, $this->f->board->id);
        $this->assertSame(404, $this->api('GET', '/api/v1/boards/' . $this->f->board->publicId . '/cards', $token)->status);

        // A token must name its boards.
        $this->f->app->post('/integrations/tokens', ['name' => 'Everything', 'scope' => 'read', 'days' => '30']);
        $this->assertSame(1, (int) $this->f->app->pdo()->query('SELECT COUNT(*) FROM api_tokens')->fetchColumn());
    }

    public function testWebhooksAreSignedQueuedAndRetried(): void
    {
        $base = '/boards/' . $this->f->board->publicId . '/webhooks';
        foreach (['http://hooks.example.com/x', 'https://internal.example.com/x', 'https://127.0.0.1/x', 'https://user:pw@hooks.example.com/x'] as $bad) {
            $this->f->app->post($base, ['url' => $bad, 'events' => ['card.created']]);
        }
        $this->assertSame(0, (int) $this->f->app->pdo()->query('SELECT COUNT(*) FROM webhooks')->fetchColumn(), 'Only public https addresses.');

        $this->f->app->post($base, ['url' => 'https://hooks.example.com/todo', 'events' => ['card.created', 'card.moved']]);
        $secret = (string) $this->f->app->pdo()->query('SELECT secret FROM webhooks')->fetchColumn();
        $this->assertTrue(str_contains($this->f->app->page($base), 'https://hooks.example.com/todo'));

        $cards = $this->f->app->container->get(CardService::class);
        $card = $cards->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Ship it');
        $cards->setComplete($this->f->owner, $card, true); // card.changed: not chosen

        $this->assertSame([1, 0], $this->webhooks()->deliverDue());
        $this->assertCount(1, $this->sent);
        [$message] = $this->sent;
        $this->assertSame('93.184.216.34', $message['ip'], 'Sent to the address that was checked.');
        $this->assertSame('sha256=' . hash_hmac('sha256', $message['body'], $secret), $message['headers']['X-Todo-Signature']);
        $payload = json_decode($message['body'], true);
        $this->assertSame(['card.created', 'Ship it', 'To do', 'Launch'], [$payload['event'], $payload['card']['title'], $payload['card']['list'], $payload['board']['name']]);
        $this->assertSame([0, 0], $this->webhooks()->deliverDue(), 'Sent once.');

        // A failing receiver is retried later, then given up on.
        $this->answer = 500;
        $cards->move($this->f->owner, $this->f->app->container->get(CardQueries::class)->find($card->id), $this->f->doing->id, null);
        $this->assertSame([0, 1], $this->webhooks()->deliverDue());
        $this->assertSame([0, 0], $this->webhooks()->deliverDue(), 'Waits before retrying.');
        /** @var FrozenClock $clock */
        $clock = $this->f->app->container->get(Clock::class);
        for ($i = 0; $i < 5; $i++) {
            $clock->travel('+13 hours');
            $this->webhooks()->deliverDue();
        }
        $row = $this->f->app->pdo()->query("SELECT status, attempts, response_code FROM webhook_deliveries WHERE event = 'card.moved'")->fetch();
        $this->assertSame(['failed', 6, 500], [$row['status'], (int) $row['attempts'], (int) $row['response_code']]);
    }

    public function testTheGuardRefusesPrivateAndSpecialAddresses(): void
    {
        foreach (['127.0.0.1', '10.1.2.3', '192.168.1.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '::1', 'fd00::1'] as $ip) {
            $this->assertFalse(UrlGuard::isPublic($ip), $ip);
        }
        $this->assertTrue(UrlGuard::isPublic('93.184.216.34'));
        $this->assertFalse((new UrlGuard(static fn () => []))->check('https://[::1]/x')['ok']);
    }

    private function webhooks(): Webhooks
    {
        return $this->f->app->container->get(Webhooks::class);
    }

    private function token(string $name, string $scope): string
    {
        $this->f->app->post('/integrations/tokens', ['name' => $name, 'scope' => $scope, 'days' => '90', 'boards' => [$this->f->board->publicId]]);
        preg_match('#<code dir="ltr" class="token-value">(tdb_[0-9a-f]{40})</code>#', $this->f->app->page('/integrations'), $m);
        $this->assertTrue($m !== [], 'The new token is shown once.');

        return $m[1];
    }

    /** @param array<string, mixed> $body */
    private function api(string $method, string $path, ?string $token, array $body = []): Response
    {
        return $this->f->app->send(new Request($method, $path, [], $body, $token !== null ? ['authorization' => 'Bearer ' . $token] : [], '10.0.0.1'));
    }
}
