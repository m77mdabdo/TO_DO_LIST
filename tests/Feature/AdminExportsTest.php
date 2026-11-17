<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Database\Backup;
use App\Http\FileResponse;
use App\Http\UploadedFile;
use App\Modules\Card\Application\CardService;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use PDO;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;
use ZipArchive;

/** A board's ZIP export, the audit log's filters and CSV, and how long backups are kept. */
final class AdminExportsTest extends TestCase
{
    public function testTheZipHoldsTheJsonAndTheFilesAfterAPreview(): void
    {
        $f = new BoardFixture(new App());
        $app = $f->app;
        $card = $app->container->get(CardService::class)->createCard($f->owner, $f->board->id, $f->todo->id, 'With a file');
        $dir = App::scratch('zip-upload');
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/a.png', (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
        $app->upload('/cards/' . $card->publicId . '/attachments', ['file' => UploadedFile::local($dir . '/a.png', '../../logo final.png')]);

        $page = $app->page('/boards/' . $f->board->publicId . '/export-zip');
        $this->assertTrue(str_contains($page, 'Download the ZIP'));
        $this->assertTrue(str_contains($page, '<dd>1</dd>'), 'One file is counted.');

        $response = $app->post('/boards/' . $f->board->publicId . '/export.zip');
        $this->assertTrue($response instanceof FileResponse);
        assert($response instanceof FileResponse);
        $this->assertSame('application/zip', $response->header('Content-Type'));
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->path) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
        }
        $this->assertTrue(in_array('board.json', $names, true));
        $files = array_values(array_filter($names, static fn ($n) => str_starts_with($n, 'attachments/' . $card->publicId . '/')));
        $this->assertCount(1, $files);
        $this->assertFalse(str_contains($files[0], '..'), 'No path that climbs out of the folder.');
        $this->assertTrue(str_contains((string) $zip->getFromName('board.json'), 'With a file'));
        $zip->close();
        unlink($response->path);
    }

    public function testViewersWithoutExportCantDownloadTheZip(): void
    {
        $f = new BoardFixture(new App());
        $f->colleague('guest@example.com', \App\Modules\Workspace\Contract\WorkspaceRole::Guest);
        $f->app->loginAs('guest@example.com');

        $this->assertSame(404, $f->app->get('/boards/' . $f->board->publicId . '/export-zip')->status);
        $this->assertSame(404, $f->app->post('/boards/' . $f->board->publicId . '/export.zip')->status);
    }

    public function testTheAuditLogFiltersAndDownloadsAsCsv(): void
    {
        $f = new BoardFixture(new App());
        $app = $f->app;
        $mona = $f->colleague('mona@example.com');
        $ws = $app->container->get(WorkspaceDirectory::class)->current($f->owner);
        $base = '/workspaces/' . $ws->publicId;
        $app->loginAs('sara@example.com');
        $app->post($base . '/members/' . $mona . '/suspend', ['on' => '1']);
        $app->post($base . '/rules', ['public_links' => '1', 'invite_approval' => '1']);
        $app->pdo()->exec("INSERT INTO audit_log (workspace_id, actor_id, action, target, details, created_at) VALUES ({$ws->id}, NULL, 'member.left', '=HYPERLINK(\"x\")', '{}', '2025-01-01 10:00:00')");

        $all = $app->page($base . '/audit');
        $this->assertTrue(str_contains($all, 'suspended'));
        $this->assertTrue(str_contains($all, 'changed the workspace rules'));

        $members = $app->page($base . '/audit?action=member.');
        $this->assertTrue(str_contains($members, 'suspended'));
        $this->assertFalse(str_contains($members, 'changed the workspace rules'));

        $since = $app->page($base . '/audit?from=2026-01-01');
        $this->assertFalse(str_contains($since, 'HYPERLINK'));
        $this->assertTrue(str_contains($app->page($base . '/audit?to=2025-12-31&action=bad%25'), 'HYPERLINK'), 'An unknown action is ignored.');

        $csv = $app->get($base . '/audit.csv?action=member.');
        $this->assertSame('text/csv; charset=utf-8', $csv->header('Content-Type'));
        $this->assertTrue(str_contains($csv->body, 'member.suspended'));
        $this->assertTrue(str_contains($csv->body, "'=HYPERLINK"), 'Formulas are defused.');
        $this->assertFalse(str_contains($csv->body, 'workspace.policy_changed'));

        $app->post($base . '/members/' . $mona . '/suspend', ['on' => '0']);
        $app->loginAs('mona@example.com');
        $this->assertSame(403, $app->get($base . '/audit.csv')->status);
    }

    public function testOldBackupsArePrunedByAgeButTheNewestStays(): void
    {
        $f = new BoardFixture(new App());
        $dir = App::scratch('backup-age');
        $backup = new Backup($f->app->container->get(PDO::class), $dir);
        $old = $backup->database('20250101-000000-aaaa');
        $newest = $backup->database('20250102-000000-bbbb');
        touch($old, strtotime('2025-01-01'));
        touch($newest, strtotime('2025-01-02'));

        $this->assertCount(1, $backup->prune(10, 30, strtotime('2026-01-20')));
        $this->assertSame([basename($newest)], array_map('basename', glob($dir . '/database-*') ?: []), 'Even when too old, the newest backup is kept.');
    }
}
