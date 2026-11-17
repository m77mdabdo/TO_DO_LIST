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
use App\Modules\Workspace\Contract\WorkspaceSummary;
use App\Session\Session;
use App\View\View;

/** The workspace's audit log for owners and admins: filter by person, action and dates, and download as CSV. */
final class AuditController extends Controller
{
    public const PAGE = 50;
    public const CSV_ROWS = 10000;
    /** Groups offered in the action filter, besides each single action. */
    public const GROUPS = ['invitation.' => 'Invitations', 'member.' => 'Members', 'board.' => 'Boards', 'workspace.' => 'Workspace rules and ownership', 'account.' => 'Accounts'];

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

    /** Owners and admins only. Query: actor, action, from, to (YYYY-MM-DD), before. @param array{workspace: string} $params */
    public function index(Request $request, array $params): Response
    {
        $workspace = $this->workspace($request, $params);
        $filters = $this->filters($request, $workspace);
        $before = $request->input('before');
        $entries = $this->log->search($workspace->id, $filters, ctype_digit($before) ? (int) $before : null, self::PAGE);
        $members = $this->workspaces->members($workspace->id);

        return $this->render('audit/index', [
            'title'     => 'Audit log',
            'workspace' => $workspace,
            'entries'   => $entries,
            'hasMore'   => count($entries) === self::PAGE,
            'people'    => $this->users->findMany([...array_values(array_filter(array_map(static fn ($e) => $e->actorId, $entries))), ...array_column($members, 'userId')]),
            'actorIds'  => array_column($members, 'userId'),
            'actions'   => $this->log->actions($workspace->id),
            'groups'    => self::GROUPS,
            'filters'   => $filters,
            'query'     => array_filter(['actor' => $filters['actor'] !== null ? (string) $filters['actor'] : '', 'action' => (string) $filters['action'], 'from' => (string) $filters['from'], 'to' => (string) $filters['to']], static fn ($v) => $v !== ''),
        ]);
    }

    /** The same entries as a spreadsheet (up to CSV_ROWS, newest first). @param array{workspace: string} $params */
    public function csv(Request $request, array $params): Response
    {
        $workspace = $this->workspace($request, $params);
        $entries = $this->log->search($workspace->id, $this->filters($request, $workspace), null, self::CSV_ROWS);
        $people = $this->users->findMany(array_values(array_filter(array_map(static fn ($e) => $e->actorId, $entries))));

        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF"); // so Excel reads Arabic correctly
        fputcsv($out, ['time', 'actor', 'actor_email', 'action', 'target', 'details'], ',', '"', '');
        foreach ($entries as $entry) {
            $actor = $entry->actorId !== null ? ($people[$entry->actorId] ?? null) : null;
            fputcsv($out, array_map([self::class, 'safe'], [
                $entry->createdAt,
                $actor?->name ?? '',
                $actor?->email ?? '',
                $entry->action,
                $entry->target,
                $entry->details !== [] ? (string) json_encode($entry->details, JSON_UNESCAPED_UNICODE) : '',
            ]), ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return (new Response($csv))
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="audit-' . $workspace->publicId . '.csv"')
            ->withHeader('Cache-Control', 'no-store');
    }

    /** A cell a spreadsheet won't run as a formula. */
    public static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    /** @param array{workspace: string} $params */
    private function workspace(Request $request, array $params): WorkspaceSummary
    {
        $workspace = $this->workspaces->findForUser($params['workspace'], $this->user($request)->id) ?? throw HttpException::notFound();
        if (!$workspace->role->managesAllBoards()) {
            throw new HttpException(403, 'You do not have permission to do that.');
        }

        return $workspace;
    }

    /** @return array{actor: ?int, action: ?string, from: ?string, to: ?string} only values that make sense */
    private function filters(Request $request, WorkspaceSummary $workspace): array
    {
        $actor = $request->input('actor');
        $action = $request->input('action');
        $date = static fn (string $v): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;

        return [
            'actor'  => ctype_digit($actor) ? (int) $actor : null,
            'action' => isset(self::GROUPS[$action]) || in_array($action, $this->log->actions($workspace->id), true) ? $action : null,
            'from'   => $date($request->input('from')),
            'to'     => $date($request->input('to')),
        ];
    }
}
