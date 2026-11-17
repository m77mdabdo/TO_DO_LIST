<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\UploadedFile;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Import\Application\CsvImport;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class ImportTest extends TestCase
{
    private BoardFixture $f;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App(), BoardVisibility::Private);
    }

    public function testCsvIsPreviewedRowByRowThenImportedInOneGo(): void
    {
        $mona = $this->f->colleague('mona@example.com');
        $this->f->app->container->get(\App\Modules\Access\Contract\BoardMembers::class)->add($this->f->board->id, $mona, \App\Modules\Access\Contract\BoardRole::Member);
        $this->f->app->container->get(\App\Modules\Card\Application\CardService::class)->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Already here');

        $csv = "\xEF\xBB\xBFالعنوان;القائمة;الموعد;الوسوم;المسؤول;مكتمل;الوصف\n"
            . "Write copy;To do;31/01/2026;Urgent, Web;mona@example.com;;\"Two\nlines\"\n"
            . "Already here;To do;;;;;\n"
            . ";To do;;;;;no title\n"
            . "Bad date;Doing;2026-02-30;;;;\n"
            . "Ship;Launch week;2026-02-10;;stranger@example.com;yes;\n";
        $response = $this->upload('cards.csv', $csv);
        $this->assertSame(302, $response->status);
        $id = (string) $this->f->app->pdo()->query("SELECT public_id FROM imports WHERE status = 'pending'")->fetchColumn();
        $page = $this->f->app->page('/imports/' . $id);

        // Columns were guessed from the Arabic headers, and every row has its result.
        $this->assertTrue(str_contains($page, '<strong>2</strong> cards will be added'), 'Write copy and Ship.');
        $this->assertTrue(str_contains($page, '<strong>1</strong> rows skipped as duplicates'));
        $this->assertTrue(str_contains($page, '<strong>2</strong> rows have a problem'));
        $this->assertTrue(str_contains($page, 'No title.'));
        $this->assertTrue(str_contains($page, 'The due date is not a date'));
        $this->assertTrue(str_contains($page, 'is not someone who can open this board'));
        $this->assertTrue(str_contains($page, 'Launch week'), 'A new list will be made.');
        $this->assertSame(1, $this->cardCount(), 'Nothing is saved by the preview.');

        // Without "create lists", Ship has a problem too.
        $this->assertTrue(str_contains($this->f->app->post('/imports/' . $id, ['action' => 'preview', 'skip_duplicates' => '1'])->body, '<strong>1</strong> cards will be added'));

        $map = CsvImport::guessMapping(['العنوان', 'القائمة', 'الموعد', 'الوسوم', 'المسؤول', 'مكتمل', 'الوصف']);
        $this->f->app->post('/imports/' . $id, ['action' => 'run', 'create_lists' => '1', 'skip_duplicates' => '1', 'map' => array_map('strval', $map)]);
        $this->assertSame(3, $this->cardCount());
        $card = $this->f->app->pdo()->query("SELECT * FROM cards WHERE title = 'Write copy'")->fetch();
        $this->assertSame(["Two\nlines", '2026-01-31'], [$card['description'], substr((string) $card['due_at'], 0, 10)]);
        $this->assertSame(2, (int) $this->f->app->pdo()->query('SELECT COUNT(*) FROM card_labels WHERE card_id = ' . (int) $card['id'])->fetchColumn());
        $this->assertSame($mona, (int) $this->f->app->pdo()->query('SELECT user_id FROM card_members WHERE card_id = ' . (int) $card['id'])->fetchColumn());
        $this->assertTrue($this->f->app->pdo()->query("SELECT completed_at FROM cards WHERE title = 'Ship'")->fetchColumn() !== null);
        $this->assertSame('done', $this->f->app->pdo()->query('SELECT status FROM imports')->fetchColumn());
        $this->assertSame(404, $this->f->app->get('/imports/' . $id)->status, 'It runs once.');
    }

    public function testImportsAreOnlyForPeopleWhoMayAddCardsAndCanBeCancelled(): void
    {
        $this->upload('cards.csv', "title\nOne\n");
        $id = (string) $this->f->app->pdo()->query('SELECT public_id FROM imports')->fetchColumn();

        $this->f->colleague('omar@example.com');
        $this->f->app->loginAs('omar@example.com');
        $this->assertSame(404, $this->f->app->get('/imports/' . $id)->status, "Someone else's import.");
        $this->assertSame(404, $this->f->app->get('/boards/' . $this->f->board->publicId . '/import')->status);

        $this->f->app->loginAs('sara@example.com');
        $this->f->app->post('/imports/' . $id . '/cancel');
        $this->assertSame('cancelled', $this->f->app->pdo()->query('SELECT status FROM imports')->fetchColumn());
        $this->assertSame(0, $this->cardCount());

        // Junk is refused with a message.
        $this->upload('cards.csv', "only a header\n");
        $this->assertSame(1, (int) $this->f->app->pdo()->query('SELECT COUNT(*) FROM imports')->fetchColumn());
        $this->assertSame(['2026-01-31', '2026-01-31', false, null], [CsvImport::date('31/01/2026'), CsvImport::date('٢٠٢٦-٠١-٣١'), CsvImport::date('soon'), CsvImport::date('')]);
    }

    public function testATrelloBoardArrivesWithEverythingAndIsNotImportedTwiceByAccident(): void
    {
        $mona = $this->f->colleague('mona@example.com');
        $json = json_encode([
            'id' => 'abc123', 'name' => 'Old Trello board',
            'members' => [['id' => 'm1', 'fullName' => 'Mona'], ['id' => 'm2', 'fullName' => 'Somebody Else']],
            'labels' => [['id' => 'l1', 'name' => 'Bug', 'color' => 'red'], ['id' => 'l2', 'name' => '', 'color' => 'lime']],
            'lists' => [['id' => 'L2', 'name' => 'Done', 'pos' => 2], ['id' => 'L1', 'name' => 'Backlog', 'pos' => 1], ['id' => 'L3', 'name' => 'Old', 'pos' => 3, 'closed' => true]],
            'cards' => [
                ['id' => 'c1', 'name' => 'Fix login', 'desc' => 'Steps', 'idList' => 'L1', 'pos' => 1, 'due' => '2026-02-01T12:00:00.000Z', 'start' => '2026-01-25T00:00:00.000Z',
                 'idLabels' => ['l1', 'l2'], 'idMembers' => ['m1', 'm2'], 'attachments' => [['name' => 'Spec', 'url' => 'https://example.com/spec', 'isUpload' => false], ['name' => 'evil', 'url' => 'javascript:alert(1)']]],
                ['id' => 'c2', 'name' => 'Shipped', 'idList' => 'L2', 'pos' => 1, 'dueComplete' => true],
                ['id' => 'c3', 'name' => 'Gone', 'idList' => 'L1', 'pos' => 2, 'closed' => true],
                ['id' => 'c4', 'name' => 'In closed list', 'idList' => 'L3', 'pos' => 1],
            ],
            'checklists' => [['id' => 'k1', 'idCard' => 'c1', 'name' => 'Todo', 'checkItems' => [['name' => 'B', 'state' => 'complete', 'pos' => 2], ['name' => 'A', 'state' => 'incomplete', 'pos' => 1]]]],
            'actions' => [['type' => 'commentCard', 'date' => '2025-12-01T10:00:00.000Z', 'memberCreator' => ['fullName' => 'Somebody Else'], 'data' => ['card' => ['id' => 'c1'], 'text' => 'Ping @mona']]],
        ]);
        $this->f->app->upload('/import/trello', ['file' => $this->file('board.json', (string) $json)]);
        $id = (string) $this->f->app->pdo()->query('SELECT public_id FROM imports')->fetchColumn();
        $page = $this->f->app->page('/imports/' . $id);
        $this->assertTrue(str_contains($page, 'Old Trello board'));
        $this->assertTrue(str_contains($page, 'Somebody Else'), 'Listed as not found.');
        $this->assertTrue(str_contains($page, '1 lists and 2 cards'), 'Archived ones are left out.');

        $this->f->app->post('/imports/' . $id, ['name' => 'From Trello', 'visibility' => 'private']);
        $board = $this->f->app->pdo()->query("SELECT * FROM boards WHERE name = 'From Trello'")->fetch();
        $this->assertTrue($board !== false);
        $boardId = (int) $board['id'];
        $this->assertSame(['Backlog', 'Done'], $this->f->app->pdo()->query("SELECT name FROM lists WHERE board_id = $boardId ORDER BY position")->fetchAll(\PDO::FETCH_COLUMN));
        $this->assertSame(['Fix login', 'Shipped'], $this->f->app->pdo()->query("SELECT title FROM cards WHERE board_id = $boardId ORDER BY title")->fetchAll(\PDO::FETCH_COLUMN));
        $card = $this->f->app->pdo()->query("SELECT * FROM cards WHERE title = 'Fix login'")->fetch();
        $cardId = (int) $card['id'];
        $this->assertSame(['2026-01-25', '2026-02-01'], [substr((string) $card['start_at'], 0, 10), substr((string) $card['due_at'], 0, 10)]);
        $this->assertSame(['Bug', 'Lime'], $this->f->app->pdo()->query("SELECT l.name FROM labels l JOIN card_labels cl ON cl.label_id = l.id WHERE cl.card_id = $cardId ORDER BY l.name")->fetchAll(\PDO::FETCH_COLUMN));
        $this->assertSame([$mona], array_map('intval', $this->f->app->pdo()->query("SELECT user_id FROM card_members WHERE card_id = $cardId")->fetchAll(\PDO::FETCH_COLUMN)));
        $this->assertSame([['A', 0], ['B', 1]], array_map(static fn ($r) => [$r['text'], $r['done_at'] !== null ? 1 : 0], $this->f->app->pdo()->query("SELECT i.text, i.done_at FROM checklist_items i WHERE i.card_id = $cardId ORDER BY i.position")->fetchAll()));
        $comment = (string) $this->f->app->pdo()->query("SELECT body FROM comments WHERE card_id = $cardId")->fetchColumn();
        $this->assertTrue(str_starts_with($comment, '**Somebody Else** · 2025-12-01 (Trello)'));
        $this->assertSame(0, (int) $this->f->app->pdo()->query("SELECT COUNT(*) FROM notifications WHERE user_id = $mona AND type LIKE '%mention%'")->fetchColumn(), 'Imported mentions notify nobody.');
        $this->assertSame(['https://example.com/spec'], $this->f->app->pdo()->query("SELECT url FROM attachments WHERE card_id = $cardId")->fetchAll(\PDO::FETCH_COLUMN));
        $this->assertTrue($this->f->app->pdo()->query("SELECT completed_at FROM cards WHERE title = 'Shipped'")->fetchColumn() !== null);

        // The same export again: warned, and refused until confirmed.
        $this->f->app->upload('/import/trello', ['file' => $this->file('board.json', (string) $json)]);
        $again = (string) $this->f->app->pdo()->query("SELECT public_id FROM imports WHERE status = 'pending'")->fetchColumn();
        $this->assertTrue(str_contains($this->f->app->page('/imports/' . $again), 'already imported here'));
        $this->f->app->post('/imports/' . $again, ['name' => 'Twice']);
        $this->assertSame(0, (int) $this->f->app->pdo()->query("SELECT COUNT(*) FROM boards WHERE name = 'Twice'")->fetchColumn());
        $this->f->app->post('/imports/' . $again, ['name' => 'Twice', 'again' => '1']);
        $this->assertSame(1, (int) $this->f->app->pdo()->query("SELECT COUNT(*) FROM boards WHERE name = 'Twice'")->fetchColumn());

        $this->f->app->upload('/import/trello', ['file' => $this->file('x.json', '{"not": "trello"}')]);
        $this->assertSame(0, (int) $this->f->app->pdo()->query("SELECT COUNT(*) FROM imports WHERE status = 'pending'")->fetchColumn());
    }

    private function upload(string $name, string $content): \App\Http\Response
    {
        return $this->f->app->upload('/boards/' . $this->f->board->publicId . '/import', ['file' => $this->file($name, $content)]);
    }

    private function file(string $name, string $content): UploadedFile
    {
        $dir = App::scratch('upload');
        mkdir($dir, 0777, true);
        $path = $dir . '/' . $name;
        file_put_contents($path, $content);

        return UploadedFile::local($path, $name);
    }

    private function cardCount(): int
    {
        return (int) $this->f->app->pdo()->query('SELECT COUNT(*) FROM cards WHERE board_id = ' . $this->f->board->id)->fetchColumn();
    }
}
