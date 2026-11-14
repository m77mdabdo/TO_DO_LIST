<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http;

use App\Controllers\Controller;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Audit\Domain\AuditLog;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Session\Session;
use App\View\View;

final class AuditController extends Controller
{
    public const PAGE = 50;

    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private AuditLog $log,
        private WorkspaceDirectory $workspaces,
        private UserDirectory $users,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** Owners and admins only. @param array{workspace: string} $params */
    public function index(Request $request, array $params): Response
    {
        $workspace = $this->workspaces->findForUser($params['workspace'], $this->user($request)->id) ?? throw HttpException::notFound();
        if (!$workspace->role->managesAllBoards()) {
            throw new HttpException(403, 'You do not have permission to do that.');
        }

        $before = $request->input('before');
        $entries = $this->log->forWorkspace($workspace->id, ctype_digit($before) ? (int) $before : null, self::PAGE);

        return $this->render('audit/index', [
            'title'     => 'Audit log',
            'workspace' => $workspace,
            'entries'   => $entries,
            'hasMore'   => count($entries) === self::PAGE,
            'people'    => $this->users->findMany(array_values(array_filter(array_map(static fn ($e) => $e->actorId, $entries)))),
        ]);
    }
}
