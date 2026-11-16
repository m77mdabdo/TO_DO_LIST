<?php

declare(strict_types=1);

namespace App\Modules\Personal\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Personal\Application\Inbox;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

/** The personal inbox: quick capture, editing, files, and moving an item to a board. */
final class InboxController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private Inbox $inbox,
        private WorkspaceDirectory $workspaces,
    ) {
        parent::__construct($view, $url, $session);
    }

    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return $this->render('personal/inbox', [
            'title'   => 'Inbox',
            'items'   => $this->inbox->items($user->id),
            'targets' => $this->inbox->targets($user->id, $this->workspaces->workspacesOf($user->id)),
            'max'     => Inbox::MAX_ITEMS,
            'editing' => $this->session->pullFlash('inbox.editing'),
        ]);
    }

    /** Body: title, description, due_date. Answers JSON for the quick-capture box in the header. */
    public function store(Request $request): Response
    {
        $user = $this->user($request);
        try {
            $this->inbox->capture($user->id, $request->input('title'), $request->input('description'), $request->input('due_date'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/inbox#capture');
        }
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'message' => $this->view->t('Added to your inbox.'), 'count' => $this->inbox->count($user->id)]);
        }

        return $this->redirect('/inbox', 'Added to your inbox.');
    }

    /** Body: title, description, due_date. @param array{item: string} $params */
    public function update(Request $request, array $params): Response
    {
        try {
            $this->inbox->update($this->user($request)->id, $params['item'], $request->input('title'), $request->input('description'), $request->input('due_date'));
        } catch (ValidationException $e) {
            $this->session->flash('inbox.editing', $params['item']);

            return $this->backWithErrors($e, $request, '/inbox#item-' . $params['item']);
        }

        return $this->redirect('/inbox#item-' . $params['item'], 'Saved.');
    }

    /** @param array{item: string} $params */
    public function destroy(Request $request, array $params): Response
    {
        $this->inbox->delete($this->user($request)->id, $params['item']);

        return $this->redirect('/inbox', 'Removed from your inbox.');
    }

    /** @param array{item: string} $params */
    public function addFile(Request $request, array $params): Response
    {
        try {
            $this->inbox->addFile($this->user($request)->id, $params['item'], $request->file('file'));
        } catch (ValidationException $e) {
            $this->session->flash('inbox.editing', $params['item']);

            return $this->backWithErrors($e, $request, '/inbox#item-' . $params['item']);
        }

        return $this->redirect('/inbox#item-' . $params['item'], 'File added.');
    }

    /** Sends a file only to the item's owner, sandboxed like card attachments. @param array{file: string} $params */
    public function file(Request $request, array $params): Response
    {
        $file = $this->inbox->file($this->user($request)->id, $params['file']);
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $file['name']) ?: 'file';

        return (new Response((string) file_get_contents($file['path'])))
            ->withHeader('Content-Type', $file['mime'])
            ->withHeader('Content-Length', (string) filesize($file['path']))
            ->withHeader('Content-Disposition', 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($file['name']))
            ->withHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    /** @param array{file: string} $params */
    public function removeFile(Request $request, array $params): Response
    {
        $this->inbox->removeFile($this->user($request)->id, $params['file']);

        return $this->redirect('/inbox', 'File removed.');
    }

    /** Body: to ("boardPublicId:listId"). @param array{item: string} $params */
    public function move(Request $request, array $params): Response
    {
        try {
            $card = $this->inbox->move($this->user($request)->id, $params['item'], $request->input('to'));
        } catch (ValidationException $e) {
            $this->session->flash('inbox.editing', $params['item']);

            return $this->backWithErrors($e, $request, '/inbox#item-' . $params['item']);
        }

        return $this->redirect('/cards/' . $card->publicId, 'Moved from your inbox to the board.');
    }
}
