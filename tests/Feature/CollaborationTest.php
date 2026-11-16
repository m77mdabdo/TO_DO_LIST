<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\UploadedFile;
use App\Modules\Access\Application\Gate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardSummary;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

/** Batch 3: the live board, reactions, files in comments, @card/@board, "is writing", browser notifications. */
final class CollaborationTest extends TestCase
{
    private BoardFixture $f;
    private CardSummary $card;

    public function setUp(): void
    {
        // A private board: only Sara (the owner) is on it until someone is added.
        $this->f = new BoardFixture(new App());
        $this->card = $this->f->app->container->get(CardService::class)->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Launch post');
    }

    /* ---------- the live board ---------- */

    public function testTheBoardReportsChangesOnlyToPeopleWhoCanSeeIt(): void
    {
        $changes = '/boards/' . $this->f->board->publicId . '/changes';
        $now = $this->json($changes . '?since=0');
        $this->assertTrue($now['ok']);
        $version = (string) $now['version'];

        $same = $this->json($changes . '?since=' . $version);
        $this->assertFalse($same['changed'], 'Nothing happened since the page was drawn.');

        $mona = $this->onBoard('mona@example.com', BoardRole::Member);
        $this->cards()->createCard($mona, $this->f->board->id, $this->f->todo->id, 'Write the FAQ');

        $this->f->app->loginAs('sara@example.com');
        $after = $this->json($changes . '?since=' . $version);
        $this->assertTrue($after['changed']);
        $this->assertSame('Mona', $after['by'], 'The page can say who changed it.');
        $this->assertFalse($after['mine']);

        $this->f->colleague('omar@example.com');
        $this->f->app->loginAs('omar@example.com');
        $this->assertSame(404, $this->f->app->get($changes . '?since=0')->status, 'Someone off a private board learns nothing about it.');
    }

    public function testTheBoardPageCarriesItsVersionAndTheStaleNotice(): void
    {
        $page = $this->f->app->page('/boards/' . $this->f->board->publicId);
        $this->assertTrue(str_contains($page, 'data-changes-url='));
        $this->assertTrue(str_contains($page, 'data-version='));
        $this->assertTrue(str_contains($page, 'js-board-stale'));
    }

    /* ---------- reactions ---------- */

    public function testReactionsToggleAndCountOncePerPerson(): void
    {
        $comment = $this->comment('Draft is ready');
        $path = '/comments/' . $comment . '/reactions';

        $on = $this->json($path, ['emoji' => 'heart']);
        $this->assertTrue($on['on']);
        $this->assertSame(1, $on['reactions']['heart']['count']);
        $this->assertTrue($on['reactions']['heart']['mine']);

        $mona = $this->onBoard('mona@example.com', BoardRole::Viewer);
        $both = $this->json($path, ['emoji' => 'heart']);
        $this->assertSame(2, $both['reactions']['heart']['count'], 'Viewers may react, as they may comment.');
        $this->assertSame(['Sara', 'Mona'], $both['reactions']['heart']['names']);

        $off = $this->json($path, ['emoji' => 'heart']);
        $this->assertFalse($off['on']);
        $this->assertSame(1, $off['reactions']['heart']['count'], 'A second press takes the reaction back.');
        $this->assertSame(0, (int) $this->f->app->pdo()->query('SELECT COUNT(*) FROM comment_reactions WHERE user_id = ' . $mona)->fetchColumn());

        $this->assertSame(422, $this->f->app->post($path, ['emoji' => '<script>'], true)->status, 'Only the offered emoji are accepted.');

        $this->f->app->loginAs('sara@example.com');
        $page = $this->f->app->page('/cards/' . $this->card->publicId);
        $this->assertTrue(str_contains($page, 'aria-pressed="true"'), 'The page shows my own reaction as pressed.');

        $this->f->colleague('omar@example.com');
        $this->f->app->loginAs('omar@example.com');
        $this->assertSame(404, $this->f->app->post($path, ['emoji' => 'heart'])->status);
    }

    public function testNobodyReactsOnAnArchivedCard(): void
    {
        $comment = $this->comment('Old news');
        $this->cards()->archive($this->f->owner, $this->card);
        $this->assertSame(403, $this->f->app->post('/comments/' . $comment . '/reactions', ['emoji' => 'thumbs'])->status);
    }

    public function testReactingRaisesTheBoardVersion(): void
    {
        $comment = $this->comment('Ship it?');
        $before = $this->json('/boards/' . $this->f->board->publicId . '/changes?since=0')['version'];
        $this->json('/comments/' . $comment . '/reactions', ['emoji' => 'tada']);
        $after = $this->json('/boards/' . $this->f->board->publicId . '/changes?since=0')['version'];
        $this->assertSame((int) $before + 1, (int) $after);
    }

    /* ---------- copy link ---------- */

    public function testEveryCommentHasACopyableLink(): void
    {
        $comment = $this->comment('Look here');
        $page = $this->f->app->page('/cards/' . $this->card->publicId);
        $this->assertTrue(str_contains($page, 'data-copy="/cards/' . $this->card->publicId . '#comment-' . $comment . '"'));
    }

    /* ---------- files in comments ---------- */

    public function testACommentMayCarryAFileThatBecomesAnAttachment(): void
    {
        $response = $this->f->app->upload('/cards/' . $this->card->publicId . '/comments', ['file' => $this->textFile('notes.txt')], ['body' => '']);
        $this->assertSame(302, $response->status);

        $row = $this->f->app->pdo()->query('SELECT body, attachment_id FROM comments')->fetch();
        $this->assertSame('', $row['body'], 'With a file, the text may be empty.');
        $this->assertTrue($row['attachment_id'] !== null);
        $this->assertSame('notes.txt', $this->f->app->pdo()->query('SELECT name FROM attachments WHERE id = ' . (int) $row['attachment_id'])->fetchColumn());
        $this->assertTrue(str_contains($this->f->app->page('/cards/' . $this->card->publicId), 'comment-file-chip'));

        // Without a file the text is still required.
        $this->f->app->post('/cards/' . $this->card->publicId . '/comments', ['body' => '  ']);
        $this->assertSame(1, (int) $this->f->app->pdo()->query('SELECT COUNT(*) FROM comments')->fetchColumn());
    }

    public function testAViewerMayCommentButNotSendAFile(): void
    {
        $this->onBoard('mona@example.com', BoardRole::Viewer);
        $this->f->app->upload('/cards/' . $this->card->publicId . '/comments', ['file' => $this->textFile('plan.txt')], ['body' => 'See attached']);
        $this->assertSame(0, (int) $this->f->app->pdo()->query('SELECT COUNT(*) FROM comments')->fetchColumn(), 'Nothing is posted half way.');
        $this->assertSame(0, (int) $this->f->app->pdo()->query('SELECT COUNT(*) FROM attachments')->fetchColumn());

        $this->f->app->post('/cards/' . $this->card->publicId . '/comments', ['body' => 'Text alone is fine']);
        $this->assertSame(1, (int) $this->f->app->pdo()->query('SELECT COUNT(*) FROM comments')->fetchColumn());
    }

    /* ---------- @card and @board ---------- */

    public function testAtBoardTellsEveryoneWhoCanSeeTheBoardAndNobodyElse(): void
    {
        $mona = $this->onBoard('mona@example.com', BoardRole::Member);
        $omar = $this->f->colleague('omar@example.com');
        $this->f->app->loginAs('sara@example.com');

        $this->comment('@board the launch moved to Monday');
        $this->assertSame(['comment.mentioned'], $this->notificationTypes($mona));
        $this->assertSame([], $this->notificationTypes($omar), 'Omar is in the workspace but not on this private board.');
        $this->assertSame([], $this->notificationTypes($this->f->owner), 'Never the author.');
    }

    public function testAtCardTellsOnlyThePeopleOnTheCard(): void
    {
        $mona = $this->onBoard('mona@example.com', BoardRole::Member);
        $lina = $this->onBoard('lina@example.com', BoardRole::Member);
        $this->f->app->loginAs('sara@example.com');
        $this->cards()->toggleMember($this->f->owner, $this->card, $mona);

        $this->comment('@card please review');
        $this->assertTrue(in_array('comment.mentioned', $this->notificationTypes($mona), true));
        $this->assertSame([], $this->notificationTypes($lina));
    }

    public function testTheAuthorIsToldWhoCouldNotBeReachedAndWhy(): void
    {
        $this->f->colleague('omar@example.com');
        $this->f->app->loginAs('sara@example.com');
        $this->f->app->post('/cards/' . $this->card->publicId . '/comments', ['body' => '@Omar can you check?']);

        $page = $this->f->app->page('/cards/' . $this->card->publicId);
        $this->assertTrue(str_contains($page, 'Not notified: Omar.'));
        $this->assertTrue(str_contains($page, 'This board is private and they are not members of it.'));
        $this->assertFalse(str_contains($this->f->app->page('/cards/' . $this->card->publicId), 'Not notified'), 'The notice shows once.');
    }

    /* ---------- "is writing" ---------- */

    public function testWritingShowsToOthersOnTheBoardOnly(): void
    {
        $typing = '/cards/' . $this->card->publicId . '/typing';
        $this->assertSame(200, $this->f->app->post($typing, [], true)->status);
        $this->assertSame([], $this->json($typing)['names'], 'I never see myself.');

        $this->onBoard('mona@example.com', BoardRole::Viewer);
        $this->assertSame(['Sara'], $this->json($typing)['names']);

        // Sending the comment clears it.
        $this->f->app->loginAs('sara@example.com');
        $this->comment('Done');
        $this->f->app->loginAs('mona@example.com');
        $this->assertSame([], $this->json($typing)['names']);

        $this->f->colleague('omar@example.com');
        $this->f->app->loginAs('omar@example.com');
        $this->assertSame(404, $this->f->app->get($typing)->status);
        $this->assertSame(404, $this->f->app->post($typing, [], true)->status);
    }

    /* ---------- browser notifications ---------- */

    public function testLatestNotificationsAreOnlyTheSignedInPersons(): void
    {
        $mona = $this->onBoard('mona@example.com', BoardRole::Member);
        $this->f->app->loginAs('sara@example.com');
        $this->comment('@Mona your turn');

        $this->f->app->loginAs('mona@example.com');
        $latest = $this->json('/notifications/latest.json?after=0');
        $this->assertCount(1, $latest['items']);
        $this->assertSame('Sara mentioned you on Launch post', $latest['items'][0]['text']);
        $this->assertSame('your turn', mb_substr($latest['items'][0]['body'], -9));
        $this->assertTrue(str_contains($latest['items'][0]['url'], '/cards/' . $this->card->publicId . '#comment-'), $latest['items'][0]['url']);
        $this->assertSame([], $this->json('/notifications/latest.json?after=' . $latest['latest'])['items'], 'Nothing new after the last one.');

        $this->f->app->loginAs('sara@example.com');
        $this->assertSame([], $this->json('/notifications/latest.json?after=0')['items'], "Sara never sees Mona's notifications.");
        $this->assertTrue($mona > 0);
    }

    public function testTheNotificationsPageOffersBrowserNotifications(): void
    {
        $page = $this->f->app->page('/notifications');
        $this->assertTrue(str_contains($page, 'js-push'));
        $this->assertTrue(str_contains($page, 'Browser notifications'));
    }

    /* ---------- helpers ---------- */

    /** Signs someone up, puts them in the workspace and on the board, and signs them in. */
    private function onBoard(string $email, BoardRole $role): int
    {
        $id = $this->f->colleague($email);
        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $id, $role);
        $this->f->app->container->get(Gate::class)->forget($id, $this->f->board->id);
        $this->f->app->loginAs($email);

        return $id;
    }

    private function comment(string $body): int
    {
        $this->f->app->post('/cards/' . $this->card->publicId . '/comments', ['body' => $body]);

        return (int) $this->f->app->pdo()->query('SELECT MAX(id) FROM comments')->fetchColumn();
    }

    /**
     * @param array<string, string>|null $post
     * @return array<string, mixed>
     */
    private function json(string $path, ?array $post = null): array
    {
        $response = $post === null
            ? $this->f->app->send(new \App\Http\Request('GET', (string) parse_url($path, PHP_URL_PATH), $this->query($path), [], ['accept' => 'application/json'], '10.0.0.1'))
            : $this->f->app->post($path, $post, true);
        $this->assertSame(200, $response->status, "$path gave {$response->status}: {$response->body}");

        return (array) json_decode($response->body, true);
    }

    /** @return array<string, string> */
    private function query(string $path): array
    {
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

        return $query;
    }

    /** @return list<string> */
    private function notificationTypes(int $userId): array
    {
        $stmt = $this->f->app->pdo()->prepare('SELECT type FROM notifications WHERE user_id = ? ORDER BY id');
        $stmt->execute([$userId]);

        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function textFile(string $name): UploadedFile
    {
        $dir = App::scratch('comment-files');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . '/' . bin2hex(random_bytes(4)) . '.txt';
        file_put_contents($path, "Plan for the launch\n");

        return UploadedFile::local($path, $name);
    }

    private function cards(): CardService
    {
        return $this->f->app->container->get(CardService::class);
    }
}
