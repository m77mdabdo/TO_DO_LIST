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
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Modules\Workspace\Contract\WorkspaceSummary;
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
        $viewer = $this->user($request);
        $workspace = $this->workspaces->findForUser($params['workspace'], $viewer->id)
            ?? throw HttpException::notFound();

        $members = $this->workspaces->members($workspace->id);
        // One query for every member's name, however many members there are.
        $people = $this->users->findMany(array_column($members, 'userId'));

        return $this->render('workspaces/members', [
            'title'     => 'Members',
            'workspace' => $workspace,
            'members'   => $members,
            'people'    => $people,
            'viewerId'  => $viewer->id,
            'grantable' => $workspace->role->grantable(),
            'suspended' => $suspended = ($workspace->role->managesAllBoards() ? $this->workspaces->suspendedMembers($workspace->id) : []),
            'suspendedPeople' => $this->users->findMany(array_column($suspended, 'userId')),
            'policy'    => $this->workspaces->policy($workspace->id),
        ]);
    }

    /** @param array{workspace: string, id: string} $params */
    public function changeRole(Request $request, array $params): Response
    {
        $workspace = $this->workspaceOf($request, $params);

        try {
            $this->workspaces->changeRole($this->user($request)->id, $workspace->id, (int) $params['id'], $request->input('role'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->membersPath($workspace->publicId));
        }

        return $this->redirect($this->membersPath($workspace->publicId), 'Role updated.');
    }

    /** @param array{workspace: string, id: string} $params */
    public function removeMember(Request $request, array $params): Response
    {
        $workspace = $this->workspaceOf($request, $params);

        try {
            $this->workspaces->removeMember($this->user($request)->id, $workspace->id, (int) $params['id']);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->membersPath($workspace->publicId));
        }

        return $this->redirect($this->membersPath($workspace->publicId), 'Member removed.');
    }

    /** Body: on (1 suspends, 0 turns back on). @param array{workspace: string, id: string} $params */
    public function suspend(Request $request, array $params): Response
    {
        $workspace = $this->workspaceOf($request, $params);
        $on = $request->input('on') === '1';
        try {
            $this->workspaces->setSuspended($this->user($request)->id, $workspace->id, (int) $params['id'], $on);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->membersPath($workspace->publicId));
        }

        return $this->redirect($this->membersPath($workspace->publicId), $on ? 'Member suspended. They can\'t open anything here until you turn them back on.' : 'Member turned back on.');
    }

    /** Body: user_id. @param array{workspace: string} $params */
    public function transfer(Request $request, array $params): Response
    {
        $workspace = $this->workspaceOf($request, $params);
        try {
            $this->workspaces->transferOwnership($this->user($request)->id, $workspace->id, (int) $request->input('user_id'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->membersPath($workspace->publicId) . '#ownership');
        }

        return $this->redirect($this->membersPath($workspace->publicId), 'Ownership handed over. You are now an admin.');
    }

    /** Body: members_invite, invite_role, invite_approval, public_links. @param array{workspace: string} $params */
    public function rules(Request $request, array $params): Response
    {
        $workspace = $this->workspaceOf($request, $params);
        $this->workspaces->savePolicy($this->user($request)->id, $workspace->id, new \App\Modules\Workspace\Contract\WorkspacePolicy(
            $request->input('members_invite') === '1',
            $request->input('invite_role') === 'guest' ? WorkspaceRole::Guest : WorkspaceRole::Member,
            $request->input('invite_approval') === '1',
            $request->input('public_links') === '1',
        ));

        return $this->redirect($this->membersPath($workspace->publicId) . '#rules', 'Workspace rules saved.');
    }

    /** @param array{workspace: string} $params */
    public function leave(Request $request, array $params): Response
    {
        $workspace = $this->workspaceOf($request, $params);

        try {
            $this->workspaces->leave($this->user($request)->id, $workspace->id);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->membersPath($workspace->publicId));
        }

        return $this->redirect('/', 'You left the workspace.');
    }

    /** @param array{workspace: string} $params */
    private function workspaceOf(Request $request, array $params): WorkspaceSummary
    {
        return $this->workspaces->findForUser($params['workspace'], $this->user($request)->id) ?? throw HttpException::notFound();
    }

    private function membersPath(string $publicId): string
    {
        return '/workspaces/' . $publicId . '/members';
    }
}
