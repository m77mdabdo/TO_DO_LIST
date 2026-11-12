<?php

declare(strict_types=1);

namespace App\Modules\Invitation\Http;

use App\Controllers\Controller;
use App\Http\HttpException;
use App\Http\Intended;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Identity\Contract\Authenticator;
use App\Modules\Invitation\Application\InvitationService;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class InvitationController extends Controller
{
    /** Flash key for a fresh link: shown once to the person who made it. */
    public const LINK_FLASH = 'invitation_link';

    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private InvitationService $invitations,
        private WorkspaceDirectory $workspaces,
        private Authenticator $auth,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** @param array{workspace: string} $params */
    public function store(Request $request, array $params): Response
    {
        $actor = $this->user($request);
        $workspace = $this->workspaces->findForUser($params['workspace'], $actor->id) ?? throw HttpException::notFound();
        $back = '/workspaces/' . $workspace->publicId . '/members';

        try {
            [, $link] = $this->invitations->invite($actor->id, $workspace->id, [
                'email'      => $request->input('email'),
                'role'       => $request->input('role'),
                'board_role' => $request->input('board_role'),
                'boards'     => $request->body()['boards'] ?? [],
            ]);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $back . '#invite');
        }

        $this->session->flash(self::LINK_FLASH, $link);

        return $this->redirect($back . '#invitations', 'Invitation sent.');
    }

    /** @param array{invitation: string} $params */
    public function resend(Request $request, array $params): Response
    {
        try {
            $link = $this->invitations->resend($this->user($request)->id, $params['invitation']);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/');
        }

        $this->session->flash(self::LINK_FLASH, $link);

        return $this->redirect($this->backToMembers($request), 'Invitation sent again with a new link.');
    }

    /** @param array{invitation: string} $params */
    public function revoke(Request $request, array $params): Response
    {
        try {
            $this->invitations->revoke($this->user($request)->id, $params['invitation']);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/');
        }

        return $this->redirect($this->backToMembers($request), 'Invitation cancelled.');
    }

    /** The page an invitation link opens. Works signed in or out. @param array{token: string} $params */
    public function show(Request $request, array $params): Response
    {
        $found = $this->invitations->lookup($params['token']);
        $user = $this->auth->user();

        if ($found !== null && $user === null) {
            // After signing in or signing up, come back here.
            Intended::remember($this->session, '/invitations/' . $params['token']);
        }

        return $this->render('invitations/show', [
            'title'      => 'Invitation',
            'bodyClass'  => 'page-auth',
            'found'      => $found,
            'user'       => $user,
            'token'      => $params['token'],
        ]);
    }

    /** @param array{token: string} $params */
    public function accept(Request $request, array $params): Response
    {
        try {
            $workspace = $this->invitations->accept($this->user($request), $params['token']);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/invitations/' . $params['token']);
        }

        return $this->redirect('/', $this->view->t('Welcome to :company!', ['company' => $workspace->companyName]));
    }

    /** Back to the members page the form was on (a local path only), else home. */
    private function backToMembers(Request $request): string
    {
        $back = $request->input('back');

        return preg_match('#^/workspaces/[0-9A-HJKMNP-TV-Z]{26}/members$#', $back) === 1 ? $back . '#invitations' : '/';
    }
}
