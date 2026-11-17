<?php

declare(strict_types=1);

namespace App\Modules\Board\Http;

use App\Controllers\Controller;
use App\Http\FileResponse;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Application\BoardZip;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Session\Session;
use App\View\View;
use RuntimeException;

/** A board with its files as one ZIP: first the size, then the download. Needs the Export permission. */
final class ZipExportController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private BoardService $boards,
        private BoardZip $zip,
        private WorkspaceDirectory $workspaces,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** @param array{board: string} $params */
    public function preview(Request $request, array $params): Response
    {
        $board = $this->boards->boardFor($this->user($request)->id, $params['board'], Permission::Export);

        return $this->render('boards/export-zip', [
            'title'     => 'Export with files',
            'board'     => $board,
            'preview'   => $this->zip->preview($board),
            'maxBytes'  => $this->zip->maxBytes(),
            'available' => BoardZip::available(),
        ]);
    }

    /** @param array{board: string} $params */
    public function download(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->boardFor($user->id, $params['board'], Permission::Export);
        try {
            $path = $this->zip->build($board, $this->workspaces->current($user->id)->companyName ?? '');
        } catch (RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return new FileResponse($path, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="board-' . $board->publicId . '.zip"',
            'Cache-Control'       => 'no-store',
        ], deleteAfterSend: true);
    }
}
