<?php

declare(strict_types=1);

namespace App\Modules\Import\Http;

use App\Controllers\Controller;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Import\Application\CsvImport;
use App\Modules\Import\Application\ImportStore;
use App\Modules\Import\Application\TrelloImport;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;
use RuntimeException;

/** CSV into a board, and a Trello export as a new board: upload, preview, run or cancel. */
final class ImportController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private ImportStore $store,
        private CsvImport $csv,
        private TrelloImport $trello,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private WorkspaceDirectory $workspaces,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** @param array{board: string} $params */
    public function csvForm(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->board($user->id, $params['board']);

        return $this->render('imports/csv-form', [
            'title'   => 'Import cards',
            'board'   => $board,
            'pending' => $this->store->pendingFor($user->id, $board->id),
            'fields'  => CsvImport::FIELDS,
        ]);
    }

    /** @param array{board: string} $params */
    public function csvUpload(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->board($user->id, $params['board']);
        try {
            $id = $this->csv->upload($user->id, $board, $request->file('file'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/boards/' . $board->publicId . '/import');
        } catch (RuntimeException $e) {
            throw new HttpException(500, $e->getMessage());
        }

        return $this->redirect('/imports/' . $id);
    }

    public function trelloForm(Request $request): Response
    {
        $user = $this->user($request);
        $workspace = $this->workspaces->current($user->id) ?? throw AccessDenied::hidden();

        return $this->render('imports/trello-form', [
            'title'     => 'Import from Trello',
            'workspace' => $workspace,
            'pending'   => $this->store->pendingFor($user->id),
        ]);
    }

    public function trelloUpload(Request $request): Response
    {
        $user = $this->user($request);
        $workspace = $this->workspaces->current($user->id) ?? throw AccessDenied::hidden();
        try {
            $id = $this->trello->upload($user->id, $workspace, $request->file('file'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/import/trello');
        } catch (RuntimeException $e) {
            throw new HttpException(500, $e->getMessage());
        }

        return $this->redirect('/imports/' . $id);
    }

    /** @param array{import: string} $params */
    public function show(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $import = $this->store->pending($user->id, $params['import']) ?? throw HttpException::notFound('This import is finished, cancelled or not yours.');

        if ($import['kind'] === 'trello') {
            return $this->render('imports/trello-preview', [
                'title'   => 'Import from Trello',
                'import'  => $import,
                'preview' => $this->trello->preview($import),
            ]);
        }

        return $this->csvPreview($request, $user->id, $import);
    }

    /** Body (CSV): map[field], create_lists, skip_duplicates, action (preview|run). Body (Trello): name, visibility, again. @param array{import: string} $params */
    public function run(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $import = $this->store->pending($user->id, $params['import']) ?? throw HttpException::notFound('This import is finished, cancelled or not yours.');
        $back = '/imports/' . $import['publicId'];

        if ($import['kind'] === 'trello') {
            try {
                $board = $this->trello->run(
                    $user->id,
                    $import,
                    $request->input('name'),
                    BoardVisibility::tryFrom($request->input('visibility')) ?? BoardVisibility::Private,
                    $request->input('again') === '1',
                );
            } catch (ValidationException $e) {
                return $this->backWithErrors($e, $request, $back);
            }

            return $this->redirect('/boards/' . $board->publicId, 'The Trello board was imported.');
        }

        if ($request->input('action') !== 'run') {
            return $this->csvPreview($request, $user->id, $import);
        }
        $board = $this->boardById($user->id, (int) $import['boardId']);
        try {
            $done = $this->csv->run($user->id, $board, $import, $this->csvMap($request, $import), $this->csvOptions($request));
        } catch (ValidationException $e) {
            return $this->csvPreview($request, $user->id, $import, (string) current($e->errors()));
        }
        $this->session->flash('success', $this->view->t(':count cards imported.', ['count' => (string) $done['cards']]));

        return $this->redirect('/boards/' . $board->publicId);
    }

    /** @param array{import: string} $params */
    public function cancel(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $import = $this->store->pending($user->id, $params['import']);
        $this->store->cancel($user->id, $params['import']);
        $board = $import !== null && $import['boardId'] !== null ? $this->boards->find($import['boardId']) : null;

        return $this->redirect($board !== null ? '/boards/' . $board->publicId : '/import/trello', 'Import cancelled. Nothing was changed.');
    }

    /** @param array<string, mixed> $import */
    private function csvPreview(Request $request, int $userId, array $import, ?string $runError = null): Response
    {
        $board = $this->boardById($userId, (int) $import['boardId']);
        $map = $this->csvMap($request, $import);
        $options = $this->csvOptions($request);

        return $this->render('imports/csv-preview', [
            'title'   => 'Import cards',
            'board'   => $board,
            'import'  => $import,
            'header'  => $import['data']['header'],
            'map'     => $map,
            'options' => $options,
            'fields'  => CsvImport::FIELDS,
            'runError' => $runError,
            'plan'    => $this->csv->plan($userId, $board, $import['data']['rows'], $map, $options),
        ]);
    }

    /**
     * @param array<string, mixed> $import
     * @return array<string, int>
     */
    private function csvMap(Request $request, array $import): array
    {
        $input = $request->body()['map'] ?? [];

        return CsvImport::mapping($import['data']['header'], is_array($input) ? $input : []);
    }

    /** @return array{create_lists: bool, skip_duplicates: bool} */
    private function csvOptions(Request $request): array
    {
        if (!$request->isPost()) {
            return ['create_lists' => true, 'skip_duplicates' => true];
        }

        return ['create_lists' => $request->input('create_lists') === '1', 'skip_duplicates' => $request->input('skip_duplicates') === '1'];
    }

    private function board(int $userId, string $publicId): BoardSummary
    {
        $board = $this->boards->findByPublicId($publicId) ?? throw AccessDenied::hidden();

        return $this->boardById($userId, $board->id);
    }

    private function boardById(int $userId, int $boardId): BoardSummary
    {
        $board = $this->boards->find($boardId) ?? throw AccessDenied::hidden();
        $this->gate->authorize($userId, $board->id, Permission::EditCards);

        return $board;
    }
}
