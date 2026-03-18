<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Url;
use App\Security\Csrf;
use App\Session\ArraySession;
use App\View\FormState;
use App\View\View;
use Tests\TestCase;

final class ViewTest extends TestCase
{
    private View $view;
    private ArraySession $session;

    public function setUp(): void
    {
        $this->session = new ArraySession();
        $this->view = new View(
            BASE_PATH . '/views',
            new Url('/todolist', BASE_PATH),
            new Csrf($this->session),
            $this->session,
            new FormState($this->session),
        );
    }

    public function testEscapesOutput(): void
    {
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $this->view->e('<script>alert("x")</script>'));
    }

    public function testBuildsLinksInsideASubFolder(): void
    {
        $this->assertSame('/todolist/tasks/3/edit', $this->view->url('/tasks/3/edit'));
        $this->assertTrue(str_starts_with($this->view->asset('css/style.css'), '/todolist/assets/css/style.css?v='));
    }

    public function testFormStateKeepsInputButNeverPasswords(): void
    {
        (new FormState($this->session))->keep(['email' => 'Bad email'], ['email' => 'x@', 'password' => 'secret', '_token' => 't']);

        $form = new FormState($this->session);
        $this->assertSame('Bad email', $form->error('email'));
        $this->assertSame('x@', $form->old('email'));
        $this->assertSame('', $form->old('password'));
    }

    public function testRendersAPartial(): void
    {
        $this->session->flash('errors', ['title' => 'Title is required.']);

        $html = $this->view->partial('partials/field-error', ['field' => 'title']);

        $this->assertTrue(str_contains($html, '<small class="field-error">Title is required.</small>'));
    }
}
