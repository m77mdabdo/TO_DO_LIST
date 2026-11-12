<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http;

use App\Controllers\Controller;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Workspace\Application\WorkspaceService;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class WorkspaceController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private WorkspaceService $workspaces,
        private UserDirectory $users,
    ) {
        parent::__construct($view, $url, $session);
    }

    public function create(): Response
    {
        return $this->render('workspaces/create', ['title' => 'New company']);
    }

    public function store(Request $request): Response
    {
        try {
            $this->workspaces->create($this->user($request)->id, ['company_name' => $request->input('company_name')]);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/workspaces/new');
        }

        return $this->redirect('/', 'Your company workspace is ready.');
    }

    /** @param array{workspace: string} $params */
    public function switch(Request $request, array $params): Response
    {
        if (!$this->workspaces->switchTo($this->user($request)->id, $params['workspace'])) {
            throw HttpException::notFound();
        }

        return $this->redirect('/');
    }

    /** @param array{workspace: string} $params */
    public function members(Request $request, array $params): Response
    {
        $workspace = $this->workspaces->findForUser($params['workspace'], $this->user($request)->id)
            ?? throw HttpException::notFound();

        $members = $this->workspaces->members($workspace->id);
        // One query for every member's name, however many members there are.
        $people = $this->users->findMany(array_column($members, 'userId'));

        return $this->render('workspaces/members', [
            'title'     => 'Members',
            'workspace' => $workspace,
            'members'   => $members,
            'people'    => $people,
        ]);
    }
}
