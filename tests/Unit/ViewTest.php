<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\View\View;
use RuntimeException;
use Tests\TestCase;

final class ViewTest extends TestCase
{
    private View $view;

    public function setUp(): void
    {
        $this->view = new View(dirname(__DIR__) . '/Fixtures/views');
        $this->view->share('app', 'Todo');
    }

    public function testRendersATemplateInsideTheLayout(): void
    {
        $html = $this->view->render('hello', ['name' => '<b>Sara</b>', 'title' => 'Hi']);

        $this->assertSame("<main title=\"Hi\">Hello &lt;b&gt;Sara&lt;/b&gt; from Todo!\n</main>\n", $html);
    }

    public function testPageDataWinsOverSharedData(): void
    {
        $this->assertSame("Hello Sara from Mine!\n", $this->view->partial('hello', ['name' => 'Sara', 'app' => 'Mine']));
    }

    public function testMissingTemplatesFailLoudly(): void
    {
        $this->assertThrows(RuntimeException::class, fn () => $this->view->partial('nope'));
    }

    public function testAnErrorDoesNotLeakHalfAPage(): void
    {
        $level = ob_get_level();

        $this->assertThrows(RuntimeException::class, fn () => $this->view->partial('broken'));
        $this->assertSame($level, ob_get_level());
    }
}
