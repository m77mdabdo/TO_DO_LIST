<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\UploadedFile;
use App\Modules\Access\Application\Gate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Attachment\Application\AttachmentService;
use App\Modules\Attachment\Application\BoardBackgrounds;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Field\Application\FieldService;
use App\Modules\Field\Contract\CustomField;
use App\Validation\ValidationException;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

/** Batch 4: custom fields, card covers and stickers, separator and board-link cards, board backgrounds. */
final class FieldsAndLooksTest extends TestCase
{
    private BoardFixture $f;
    private CardSummary $card;
    private string $dir;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App());
        $this->card = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Launch post');
        $this->dir = App::scratch('uploads');
        @mkdir($this->dir, 0777, true);
    }

    /* ---------- custom field definitions ---------- */

    public function testManagersDefineFieldsWithLimitsAndUniqueNames(): void
    {
        $base = '/boards/' . $this->f->board->publicId . '/fields';
        $this->assertSame(302, $this->f->app->post($base, ['name' => 'Priority', 'type' => 'select', 'options' => "High\nLow\n\nHigh", 'on_card' => '1'])->status);
        $fields = $this->fields()->forBoard($this->f->board->id);
        $this->assertCount(1, $fields);
        $this->assertSame(['High', 'Low'], array_column($fields[0]->options, 'name'), 'Blank and repeated choices are dropped.');
        $this->assertTrue($fields[0]->onCard);

        $this->assertInvalid(fn () => $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'priority', 'type' => 'text']), 'name');
        $this->assertInvalid(fn () => $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'Size', 'type' => 'select', 'options' => '']), 'options');
        $this->assertInvalid(fn () => $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'Bad', 'type' => 'script']), 'type');
        $this->assertInvalid(fn () => $this->fields()->create($this->f->owner, $this->f->board, ['name' => str_repeat('x', 61), 'type' => 'text']), 'name');

        for ($i = 2; $i <= CustomField::MAX_PER_BOARD; $i++) {
            $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'Field ' . $i, 'type' => 'text']);
        }
        $this->assertInvalid(fn () => $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'One too many', 'type' => 'text']), 'name');

        $page = $this->f->app->page($base);
        $this->assertTrue(str_contains($page, 'Priority'));

        // A member fills values.
        $this->onBoard('mona@example.com', BoardRole::Member);
        $this->assertSame(302, $this->f->app->post('/cards/' . $this->card->publicId . '/fields', ['field' => [$fields[0]->id => $fields[0]->options[0]['id']]])->status);

        // A viewer does neither (members manage fields, as they manage labels).
        $this->onBoard('vera@example.com', BoardRole::Viewer);
        $this->assertSame(403, $this->f->app->post($base, ['name' => 'Mine', 'type' => 'text'])->status);
        $this->assertSame(403, $this->f->app->post($base . '/' . $fields[0]->id . '/delete')->status);
        $this->assertCount(CustomField::MAX_PER_BOARD, $this->fields()->forBoard($this->f->board->id));

        // Someone off this private board learns nothing.
        $this->f->colleague('omar@example.com');
        $this->f->app->loginAs('omar@example.com');
        $this->assertSame(404, $this->f->app->get($base)->status);
        $this->assertSame(404, $this->f->app->post('/cards/' . $this->card->publicId . '/fields', ['field' => [$fields[0]->id => 'o1']])->status);
    }

    public function testRenamingReorderingAndRemovingAUsedChoiceNeedsConsent(): void
    {
        $priority = $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'Priority', 'type' => 'select', 'options' => "High\nLow"]);
        $notes = $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'Notes', 'type' => 'text']);
        $high = $priority->options[0]['id'];
        $this->fields()->fill($this->f->owner, $this->f->board, $this->card, [$priority->id => $high]);

        // Renaming a choice keeps the others' ids; dropping a used one asks first.
        $this->assertInvalid(fn () => $this->fields()->update($this->f->owner, $this->f->board, $priority->id, ['name' => 'Urgency', 'options' => "Low\nMedium"]), 'options');
        $this->assertSame($high, $this->values()[$priority->id] ?? null, 'Nothing changed without consent.');

        $updated = $this->fields()->update($this->f->owner, $this->f->board, $priority->id, ['name' => 'Urgency', 'options' => "Low\nMedium", 'confirm' => '1']);
        $this->assertSame('Urgency', $updated->name);
        $this->assertSame($priority->options[1]['id'], $updated->options[0]['id'], 'Low kept its id.');
        $this->assertNull($this->values()[$priority->id] ?? null, 'The removed choice is cleared from the card.');

        $this->fields()->move($this->f->owner, $this->f->board, $notes->id, true);
        $this->assertSame(['Notes', 'Urgency'], array_map(static fn ($f) => $f->name, $this->fields()->forBoard($this->f->board->id)));

        $this->fields()->fill($this->f->owner, $this->f->board, $this->card, [$notes->id => 'Ship on Monday']);
        $this->assertSame(1, $this->fields()->valueCount($notes->id));
        $this->f->app->post('/boards/' . $this->f->board->publicId . '/fields/' . $notes->id . '/delete');
        $this->assertSame(['Urgency'], array_map(static fn ($f) => $f->name, $this->fields()->forBoard($this->f->board->id)));
        $this->assertSame([], $this->values());
    }

    /* ---------- values ---------- */

    public function testValuesAreCheckedPerTypeAndOnlySentFieldsChange(): void
    {
        $text = $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'Notes', 'type' => 'text']);
        $number = $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'Points', 'type' => 'number']);
        $date = $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'Launch', 'type' => 'date']);
        $select = $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'Size', 'type' => 'select', 'options' => "S\nM"]);
        $box = $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'Signed off', 'type' => 'checkbox']);

        $path = '/cards/' . $this->card->publicId . '/fields';
        $this->assertSame(302, $this->f->app->post($path, ['field' => [
            $text->id => '  <b>bold</b>  ', $number->id => '١٢٫٥', $date->id => '2026-02-01', $select->id => $select->options[1]['id'], $box->id => '1',
        ]])->status);
        $values = $this->values();
        $this->assertSame('<b>bold</b>', $values[$text->id]);
        $this->assertSame('12.5', $values[$number->id], 'Arabic digits and decimal sign are understood.');
        $this->assertSame('2026-02-01', $values[$date->id]);
        $this->assertSame('1', $values[$box->id]);

        // One bad value saves nothing.
        foreach ([[$number->id, '12abc'], [$date->id, '2026-02-30'], [$select->id, 'o99'], [$text->id, str_repeat('x', 501)]] as [$id, $bad]) {
            $this->assertInvalid(fn () => $this->fields()->fill($this->f->owner, $this->f->board, $this->card, [$text->id => 'changed', $id => $bad]), 'field_' . $id);
        }
        $this->assertSame('<b>bold</b>', $this->values()[$text->id]);

        // Unticking sends "" for that box only; fields not sent are left alone.
        $this->f->app->post($path, ['field' => [$box->id => '']]);
        $values = $this->values();
        $this->assertNull($values[$box->id] ?? null);
        $this->assertSame('12.5', $values[$number->id]);

        $page = $this->f->app->page('/cards/' . $this->card->publicId);
        $this->assertTrue(str_contains($page, '&lt;b&gt;bold&lt;/b&gt;'), 'Values are escaped on the card page.');

        // A viewer sees values but cannot change them.
        $this->onBoard('vera@example.com', BoardRole::Viewer);
        $this->assertSame(403, $this->f->app->post($path, ['field' => [$text->id => 'mine']])->status);
        $this->assertTrue(str_contains($this->f->app->page('/cards/' . $this->card->publicId), '12.5'));
    }

    public function testFieldsShowOnTilesTheTableExportsSearchAndCopies(): void
    {
        $points = $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'Points', 'type' => 'number', 'on_card' => '1']);
        $secret = $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'Budget code', 'type' => 'text']);
        $box = $this->fields()->create($this->f->owner, $this->f->board, ['name' => 'Signed off', 'type' => 'checkbox']);
        $this->fields()->fill($this->f->owner, $this->f->board, $this->card, [$points->id => '8', $secret->id => 'ZX-41', $box->id => '1']);

        $board = $this->f->app->page('/boards/' . $this->f->board->publicId);
        $this->assertTrue(str_contains($board, 'field-chip'));
        $this->assertTrue(str_contains($board, 'Points'));
        $this->assertFalse(str_contains($board, 'Budget code:'), 'Only fields marked for the cards show on tiles.');

        $table = $this->f->app->page('/boards/' . $this->f->board->publicId . '/table');
        $this->assertTrue(str_contains($table, 'Budget code') && str_contains($table, 'ZX-41'));

        $csv = $this->f->app->get('/boards/' . $this->f->board->publicId . '/export')->body;
        $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", str_replace("\r", '', ltrim($csv, "\u{FEFF}"))))));
        $header = $rows[0];
        $row = array_combine($header, array_slice($rows[1], 0, count($header)));
        $this->assertSame('8', $row['Points']);
        $this->assertSame('ZX-41', $row['Budget code']);
        $this->assertSame('yes', $row['Signed off']);

        $json = json_decode($this->f->app->get('/boards/' . $this->f->board->publicId . '/export.json')->body, true);
        $this->assertSame(['Points' => '8', 'Budget code' => 'ZX-41', 'Signed off' => true], $json['boards'][0]['cards'][0]['fields']);

        $search = $this->f->app->page('/search?q=ZX-41');
        $this->assertTrue(str_contains($search, 'Launch post') && str_contains($search, 'In a custom field'));

        // Copying to another board brings the values, creating the field there.
        $other = $this->f->app->container->get(BoardService::class)->createBoard($this->f->owner, $this->f->workspaceId, 'Other', BoardVisibility::Private);
        $list = $this->f->app->container->get(BoardService::class)->addList($this->f->owner, $other->id, 'Inbox');
        $copy = $this->cards()->copyCard($this->f->owner, $this->card, $other->id, $list->id, 'Launch post', ['fields']);
        $copied = $this->fields()->shownForCards($other->id, [$copy->id]);
        $this->assertSame(['Points' => '8', 'Budget code' => 'ZX-41', 'Signed off' => '✓'], array_combine(
            array_map(static fn ($s) => $s['field']->name, $copied[$copy->id]),
            array_column($copied[$copy->id], 'text'),
        ));

        // Without "fields" in the copy, nothing comes along.
        $bare = $this->cards()->copyCard($this->f->owner, $this->card, $other->id, $list->id, 'Bare', []);
        $this->assertSame([], $this->fields()->shownForCards($other->id, [$bare->id]));
    }

    /* ---------- card looks ---------- */

    public function testCoversStickersAndKinds(): void
    {
        $path = '/cards/' . $this->card->publicId . '/looks';
        $this->f->app->post($path, ['cover' => 'color:mint', 'cover_size' => 'full', 'sticker' => 'rocket', 'kind' => 'card']);
        $card = $this->reload();
        $this->assertSame(['mint', 'full', 'rocket', 'card'], [$card->coverColor, $card->coverSize, $card->sticker, $card->kind]);

        $board = $this->f->app->page('/boards/' . $this->f->board->publicId);
        $this->assertTrue(str_contains($board, 'cover-full') && str_contains($board, '🚀'));

        // Bad choices are refused and change nothing.
        foreach ([['cover' => 'color:hotpink'], ['cover' => 'image:01HXXXXXXXXXXXXXXXXXXXXXXX'], ['sticker' => 'skull'], ['kind' => 'html']] as $bad) {
            $this->assertInvalid(fn () => $this->cards()->setLooks($this->f->owner, $this->reload(), $bad + ['cover' => 'color:mint', 'sticker' => 'rocket']), (string) array_key_first($bad));
        }
        $this->assertSame('rocket', $this->reload()->sticker);

        // Removing the sticker leaves the rest alone.
        $this->f->app->post($path, ['cover' => 'color:mint', 'cover_size' => 'full', 'sticker' => '', 'kind' => 'card']);
        $card = $this->reload();
        $this->assertNull($card->sticker);
        $this->assertSame('mint', $card->coverColor);

        $this->f->app->post($path, ['cover' => '', 'kind' => 'separator']);
        $this->assertTrue($this->reload()->isSeparator());
        $this->assertTrue(str_contains($this->f->app->page('/boards/' . $this->f->board->publicId), 'tile-separator'));

        $this->onBoard('vera@example.com', BoardRole::Viewer);
        $this->assertSame(403, $this->f->app->post($path, ['cover' => 'color:sky'])->status);
    }

    public function testAnImageCoverComesFromTheCardAndGoesAwayWithTheAttachment(): void
    {
        $this->f->app->upload('/cards/' . $this->card->publicId . '/attachments', ['file' => $this->png('cover.png')]);
        $image = $this->attachments()->forCard($this->card->id)[0];

        // Another card's image can not be used.
        $other = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Other');
        $this->assertInvalid(fn () => $this->cards()->setLooks($this->f->owner, $other, ['cover' => 'image:' . $image->publicId]), 'cover');

        $this->f->app->post('/cards/' . $this->card->publicId . '/looks', ['cover' => 'image:' . $image->publicId, 'cover_size' => 'top']);
        $this->assertSame($image->id, $this->reload()->coverAttachmentId);
        $board = $this->f->app->page('/boards/' . $this->f->board->publicId);
        $this->assertTrue(str_contains($board, '/attachments/' . $image->publicId), 'The tile loads the cover through the attachment route.');
        $this->assertTrue(str_contains($this->f->app->page('/cards/' . $this->card->publicId), 'It is the card’s cover'));

        $this->f->app->post('/attachments/' . $image->publicId . '/delete');
        $card = $this->reload();
        $this->assertNull($card->coverAttachmentId);
        $this->assertFalse($card->hasCover());
        $this->assertSame(200, $this->f->app->get('/boards/' . $this->f->board->publicId)->status);
        $this->assertSame(200, $this->f->app->get('/cards/' . $this->card->publicId)->status);
    }

    public function testABoardLinkOnlyPointsAtABoardTheEditorCanOpenAndHidesItFromOthers(): void
    {
        $boards = $this->f->app->container->get(BoardService::class);
        $hiring = $boards->createBoard($this->f->owner, $this->f->workspaceId, 'Hiring', BoardVisibility::Private);
        $path = '/cards/' . $this->card->publicId . '/looks';

        $this->f->app->post($path, ['kind' => 'board', 'link_board' => $hiring->publicId]);
        $card = $this->reload();
        $this->assertSame(['board', $hiring->id], [$card->kind, $card->linkBoardId]);
        $this->assertTrue(str_contains($this->f->app->page('/boards/' . $this->f->board->publicId), 'Hiring'));

        $this->assertInvalid(fn () => $this->cards()->setLooks($this->f->owner, $card, ['kind' => 'board', 'link_board' => $this->f->board->publicId]), 'link_board');

        // Mona is on this board but not on Hiring: she sees the card, not the board's name.
        $this->onBoard('mona@example.com', BoardRole::Member);
        $tile = $this->f->app->page('/boards/' . $this->f->board->publicId);
        $this->assertFalse(str_contains($tile, 'Hiring'));
        $this->assertTrue(str_contains($tile, 'A board you cannot open.'));
        $this->assertFalse(str_contains($this->f->app->page('/cards/' . $this->card->publicId), 'Hiring'));

        // And she can not link to a board she can not open.
        $this->assertInvalid(fn () => $this->cards()->setLooks($this->f->app->container->get(\App\Modules\Identity\Contract\Authenticator::class)->user()->id, $this->reload(), ['kind' => 'board', 'link_board' => $hiring->publicId]), 'link_board');
    }

    /* ---------- board backgrounds ---------- */

    public function testBoardBackgroundsAreCheckedStoredPrivatelyAndShownToMembersOnly(): void
    {
        $base = '/boards/' . $this->f->board->publicId;
        $this->f->app->post($base, ['name' => $this->f->board->name, 'color' => 'sky', 'visibility' => 'private', 'background' => 'gradient']);
        $this->assertTrue(str_contains($this->f->app->page($base), 'board-bg-gradient'));
        $this->f->app->post($base, ['name' => $this->f->board->name, 'color' => 'sky', 'visibility' => 'private', 'background' => 'plaid']);
        $this->assertTrue(str_contains($this->f->app->page($base), 'board-bg-gradient'), 'An unknown background is refused.');

        $svg = $this->dir . '/x.svg';
        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->f->app->upload($base . '/background', ['background_picture' => UploadedFile::local($svg, 'x.svg')]);
        $pdf = $this->dir . '/x.pdf';
        file_put_contents($pdf, "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n");
        $this->f->app->upload($base . '/background', ['background_picture' => UploadedFile::local($pdf, 'x.pdf')]);
        $big = new UploadedFile('big.png', $svg, BoardBackgrounds::MAX_BYTES + 1, UPLOAD_ERR_OK, false);
        $this->f->app->upload($base . '/background', ['background_picture' => $big]);
        $this->assertFalse($this->backgrounds()->has($this->f->board->id), 'Scripts, documents and huge files are refused.');

        $this->assertSame(302, $this->f->app->upload($base . '/background', ['background_picture' => $this->png('sea.png')])->status);
        $this->assertTrue($this->backgrounds()->has($this->f->board->id));
        $this->assertTrue(str_contains($this->f->app->page($base), 'board-picture'));
        $picture = $this->f->app->get($base . '/background');
        $this->assertSame(200, $picture->status);
        $this->assertSame('image/png', $picture->header('Content-Type'));
        $this->assertTrue(str_contains((string) $picture->header('Content-Security-Policy'), 'sandbox'));

        // A member sees it but can not change it; an outsider gets nothing.
        $this->onBoard('mona@example.com', BoardRole::Member);
        $this->assertSame(200, $this->f->app->get($base . '/background')->status);
        $this->assertSame(403, $this->f->app->upload($base . '/background', ['background_picture' => $this->png('mine.png')])->status);
        $this->assertSame(403, $this->f->app->post($base . '/background/remove')->status);
        $this->f->colleague('omar@example.com');
        $this->f->app->loginAs('omar@example.com');
        $this->assertSame(404, $this->f->app->get($base . '/background')->status);

        $this->f->app->loginAs('sara@example.com');
        $this->f->app->post($base . '/background/remove');
        $this->assertFalse($this->backgrounds()->has($this->f->board->id));
        $this->assertSame(404, $this->f->app->get($base . '/background')->status);
    }

    /* ---------- helpers ---------- */

    private function assertInvalid(callable $action, string $field): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());

            return;
        }
        $this->assertTrue(false, "Expected a validation error on $field.");
    }

    /** @return array<int, string> field id => value for the test card */
    private function values(): array
    {
        return $this->fields()->valuesForCards([$this->card->id])[$this->card->id] ?? [];
    }

    private function reload(): CardSummary
    {
        return $this->f->app->container->get(CardQueries::class)->find($this->card->id) ?? $this->card;
    }

    private function onBoard(string $email, BoardRole $role): int
    {
        $id = $this->f->colleague($email);
        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $id, $role);
        $this->f->app->container->get(Gate::class)->forget($id, $this->f->board->id);
        $this->f->app->loginAs($email);

        return $id;
    }

    private function png(string $name): UploadedFile
    {
        $path = $this->dir . '/' . bin2hex(random_bytes(4)) . '.png';
        file_put_contents($path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));

        return UploadedFile::local($path, $name);
    }

    private function cards(): CardService
    {
        return $this->f->app->container->get(CardService::class);
    }

    private function fields(): FieldService
    {
        return $this->f->app->container->get(FieldService::class);
    }

    private function attachments(): AttachmentService
    {
        return $this->f->app->container->get(AttachmentService::class);
    }

    private function backgrounds(): BoardBackgrounds
    {
        return $this->f->app->container->get(BoardBackgrounds::class);
    }
}
