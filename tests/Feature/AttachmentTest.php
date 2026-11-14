<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\UploadedFile;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Activity\Contract\ActivityFeed;
use App\Modules\Attachment\Application\AttachmentService;
use App\Modules\Attachment\Domain\UploadPolicy;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardSummary;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class AttachmentTest extends TestCase
{
    private BoardFixture $f;
    private CardSummary $card;
    private string $dir;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App());
        $this->card = $this->f->app->container->get(CardService::class)->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Logo');
        $this->dir = App::scratch('uploads');
        mkdir($this->dir, 0777, true);
    }

    public function testAnImageIsStoredOutsideTheWebRootAndServedOnlyToPeopleOnTheBoard(): void
    {
        $response = $this->f->app->upload('/cards/' . $this->card->publicId . '/attachments', ['file' => $this->png('../../logo final.png')]);
        $this->assertSame(302, $response->status);

        $attachment = $this->service()->forCard($this->card->id)[0];
        $this->assertSame('logo final.png', $attachment->name, 'The path part of the name is dropped.');
        $this->assertSame('image/png', $attachment->mime);
        $this->assertSame(1, preg_match('/^[a-f0-9]{32}$/', (string) $attachment->storedName));

        $page = $this->f->app->page('/cards/' . $this->card->publicId);
        $this->assertTrue(str_contains($page, 'logo final.png'));

        $file = $this->f->app->get('/attachments/' . $attachment->publicId);
        $this->assertSame(200, $file->status);
        $this->assertSame('image/png', $file->header('Content-Type'));
        $this->assertTrue(str_starts_with((string) $file->header('Content-Disposition'), 'inline'));
        $this->assertTrue(str_contains((string) $file->header('Content-Security-Policy'), 'sandbox'));
        $this->assertSame('nosniff', $file->header('X-Content-Type-Options'));
        $this->assertTrue(str_starts_with((string) $this->f->app->get('/attachments/' . $attachment->publicId . '?download=1')->header('Content-Disposition'), 'attachment'));

        $this->assertSame('attachment.added', $this->f->app->container->get(ActivityFeed::class)->forCard($this->card->id)[0]->type);

        // Someone in another company gets a 404, as if it did not exist.
        $this->f->app->signUp('Eve', 'eve@example.com');
        $this->assertSame(404, $this->f->app->get('/attachments/' . $attachment->publicId)->status);
    }

    public function testTheRealContentDecidesNotTheName(): void
    {
        $html = $this->dir . '/page.png';
        file_put_contents($html, '<html><script>alert(1)</script></html>');
        $this->f->app->upload('/cards/' . $this->card->publicId . '/attachments', ['file' => UploadedFile::local($html, 'page.png')]);
        $this->assertSame([], $this->service()->forCard($this->card->id));

        $svg = $this->dir . '/x.svg';
        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->f->app->upload('/cards/' . $this->card->publicId . '/attachments', ['file' => UploadedFile::local($svg, 'x.svg')]);
        $this->assertSame([], $this->service()->forCard($this->card->id));

        $big = new UploadedFile('big.pdf', $html, UploadPolicy::MAX_BYTES + 1, UPLOAD_ERR_OK, false);
        $this->f->app->upload('/cards/' . $this->card->publicId . '/attachments', ['file' => $big]);
        $this->assertSame([], $this->service()->forCard($this->card->id));

        $text = $this->dir . '/notes.txt';
        file_put_contents($text, "Plain notes\n");
        $this->f->app->upload('/cards/' . $this->card->publicId . '/attachments', ['file' => UploadedFile::local($text, "notes\u{202E}txt.exe.txt")]);
        $saved = $this->service()->forCard($this->card->id);
        $this->assertCount(1, $saved);
        $this->assertSame('notestxt.exe.txt', $saved[0]->name, 'Direction overrides are removed from names.');
        $this->assertTrue(str_starts_with((string) $this->f->app->get('/attachments/' . $saved[0]->publicId)->header('Content-Disposition'), 'attachment'));
    }

    public function testLinksMustBeWebAddresses(): void
    {
        $path = '/cards/' . $this->card->publicId . '/links';
        $this->f->app->post($path, ['url' => 'javascript:alert(1)']);
        $this->f->app->post($path, ['url' => 'ftp://example.com/file']);
        $this->assertSame([], $this->service()->forCard($this->card->id));

        $this->f->app->post($path, ['url' => 'https://example.com/brief', 'title' => 'Brief']);
        $this->f->app->post($path, ['url' => 'https://figma.com/file/abc']);
        $links = $this->service()->forCard($this->card->id);
        $this->assertSame(['figma.com', 'Brief'], array_map(static fn ($a) => $a->name, $links));
        $this->assertTrue(str_contains($this->f->app->page('/cards/' . $this->card->publicId), 'rel="noopener noreferrer nofollow"'));
    }

    public function testViewersSeeButCanNotAddAndOnlyTheUploaderOrAManagerRemoves(): void
    {
        $this->f->app->upload('/cards/' . $this->card->publicId . '/attachments', ['file' => $this->png('a.png')]);
        $attachment = $this->service()->forCard($this->card->id)[0];

        $viewer = $this->f->colleague('viewer@example.com');
        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $viewer, BoardRole::Viewer);
        $member = $this->f->colleague('member@example.com');
        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $member, BoardRole::Member);

        $this->f->app->loginAs('viewer@example.com');
        $this->assertSame(200, $this->f->app->get('/attachments/' . $attachment->publicId)->status);
        $this->assertSame(403, $this->f->app->upload('/cards/' . $this->card->publicId . '/attachments', ['file' => $this->png('b.png')])->status);
        $this->assertSame(403, $this->f->app->post('/attachments/' . $attachment->publicId . '/delete')->status);

        $this->f->app->loginAs('member@example.com');
        $this->assertSame(403, $this->f->app->post('/attachments/' . $attachment->publicId . '/delete')->status, 'A member may not remove what someone else added.');

        $this->f->app->loginAs('sara@example.com');
        $stored = $this->f->app->container->get(\App\Modules\Attachment\Domain\FileStore::class);
        $this->assertTrue($stored->path((string) $attachment->storedName) !== null);
        $this->f->app->post('/attachments/' . $attachment->publicId . '/delete');
        $this->assertSame([], $this->service()->forCard($this->card->id));
        $this->assertNull($stored->path((string) $attachment->storedName));
    }

    public function testBoardTilesShowHowManyAttachmentsACardHas(): void
    {
        $this->f->app->post('/cards/' . $this->card->publicId . '/links', ['url' => 'https://example.com']);
        $this->assertTrue(str_contains($this->f->app->page('/boards/' . $this->f->board->publicId), 'title="Attachments"'));
    }

    private function png(string $name): UploadedFile
    {
        $path = $this->dir . '/' . bin2hex(random_bytes(4)) . '.png';
        // 1x1 transparent PNG.
        file_put_contents($path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));

        return UploadedFile::local($path, $name);
    }

    private function service(): AttachmentService
    {
        return $this->f->app->container->get(AttachmentService::class);
    }
}
