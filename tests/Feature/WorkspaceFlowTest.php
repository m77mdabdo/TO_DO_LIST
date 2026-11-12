<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Support\Clock;
use Tests\Support\App;
use Tests\TestCase;

final class WorkspaceFlowTest extends TestCase
{
    private App $app;

    public function setUp(): void
    {
        $this->app = new App();
    }

    public function testSigningUpGivesTheUserTheirOwnWorkspace(): void
    {
        $userId = $this->app->signUp();

        $workspaces = $this->directory()->workspacesOf($userId);

        $this->assertCount(1, $workspaces);
        $this->assertSame(WorkspaceRole::Owner, $workspaces[0]->role);
        $this->assertSame('Sara Ali', $workspaces[0]->name);
    }

    public function testLoggingInAgainDoesNotCreateAnotherWorkspace(): void
    {
        $userId = $this->app->signUp();
        $this->app->loginAs('sara@example.com');

        $this->assertCount(1, $this->directory()->workspacesOf($userId));
    }

    public function testCreatingACompanySwitchesToItsWorkspace(): void
    {
        $userId = $this->app->signUp();

        $response = $this->app->post('/workspaces', ['company_name' => 'Nile Design']);

        $this->assertSame('/', $response->header('Location'));
        $this->assertSame('Nile Design', $this->directory()->current($userId)?->name);
        $this->assertCount(2, $this->directory()->workspacesOf($userId));
    }

    public function testTheNextSignInOpensTheLastWorkspaceUsed(): void
    {
        $userId = $this->app->signUp();
        $own = $this->directory()->current($userId);
        $this->app->post('/workspaces', ['company_name' => 'Nile Design']);

        $this->app->loginAs('sara@example.com');
        $this->assertSame('Nile Design', $this->directory()->current($userId)?->name);

        $this->app->container->get(Clock::class)->travel('+1 hour');
        $this->app->post('/workspaces/' . $own->publicId . '/switch');
        $this->app->loginAs('sara@example.com');
        $this->assertSame('Sara Ali', $this->directory()->current($userId)?->name);
    }

    public function testCompanyNameIsValidated(): void
    {
        $this->app->signUp();

        $response = $this->app->post('/workspaces', ['company_name' => 'x']);

        $this->assertSame('/workspaces/new', $response->header('Location'));
    }

    public function testNobodyCanSeeOrSwitchIntoAnotherCompanysWorkspace(): void
    {
        $saraId = $this->app->signUp();
        $saraWorkspace = $this->directory()->current($saraId);
        $omarId = $this->app->signUp('Omar', 'omar@example.com');

        $this->assertSame(404, $this->app->get('/workspaces/' . $saraWorkspace->publicId . '/members')->status);
        $this->assertSame(404, $this->app->post('/workspaces/' . $saraWorkspace->publicId . '/switch')->status);
        $this->assertSame('Omar', $this->directory()->current($omarId)?->name);
        $this->assertNull($this->directory()->roleOf($omarId, $saraWorkspace->id));
    }

    public function testMembersPageListsTheOwner(): void
    {
        $userId = $this->app->signUp();
        $workspace = $this->directory()->current($userId);

        $html = $this->app->page('/workspaces/' . $workspace->publicId . '/members');

        $this->assertTrue(str_contains($html, 'sara@example.com'));
    }

    private function directory(): WorkspaceDirectory
    {
        return $this->app->container->get(WorkspaceDirectory::class);
    }
}
