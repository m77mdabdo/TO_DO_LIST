<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Workspace\Contract\WorkspaceRole;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class ActivityPagesTest extends TestCase
{
    public function testBoardActivityIsPagedWithAnOlderLink(): void
    {
        $f = new BoardFixture(new App());
        $boards = $f->app->container->get(BoardService::class);
        for ($i = 1; $i <= 40; $i++) {
            $boards->addList($f->owner, $f->board->id, "List $i");
        }
        $path = '/boards/' . $f->board->publicId . '/activity';

        $first = $f->app->page($path);
        $this->assertTrue(str_contains($first, 'List 40'));
        $this->assertFalse(str_contains($first, 'List 10<'));
        preg_match('#activity\?before=(\d+)#', $first, $m);

        $second = $f->app->page($path . '?before=' . $m[1]);
        $this->assertTrue(str_contains($second, 'List 10<'));
        $this->assertTrue(str_contains($second, 'created this board'));
    }

    public function testActivityIsHiddenFromPeopleOutsideTheBoard(): void
    {
        $f = new BoardFixture(new App(), BoardVisibility::Private);
        $f->colleague('omar@example.com', WorkspaceRole::Member);

        $this->assertSame(404, $f->app->get('/boards/' . $f->board->publicId . '/activity')->status);
    }
}
