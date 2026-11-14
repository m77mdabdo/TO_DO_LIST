<?php

declare(strict_types=1);

namespace App\Modules\Attachment\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Attachment\Application\AttachmentService;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class AttachmentController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private AttachmentService $attachments,
        private CardQueries $cards,
        private AccessGate $gate,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** Multipart field: file. @param array{card: string} $params */
    public function upload(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $card = $this->card($user->id, $params['card']);

        try {
            $this->attachments->upload($user->id, $card, $request->file('file'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->cardPath($card) . '#attachments');
        }

        return $this->redirect($this->cardPath($card) . '#attachments', 'File attached.');
    }

    /** Body: url, title. @param array{card: string} $params */
    public function link(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $card = $this->card($user->id, $params['card']);

        try {
            $this->attachments->addLink($user->id, $card, $request->input('url'), $request->input('title'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->cardPath($card) . '#attachments');
        }

        return $this->redirect($this->cardPath($card) . '#attachments', 'Link added.');
    }

    /**
     * Sends the file after checking that the user may see its board. Images
     * open in the browser; everything else downloads. Either way the response
     * is sandboxed so a file can never run as a page of this site.
     *
     * @param array{attachment: string} $params
     */
    public function show(Request $request, array $params): Response
    {
        [$attachment, $path] = $this->attachments->fileFor($this->user($request)->id, $params['attachment']);
        $inline = $attachment->isImage() && $request->input('download') === '';
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $attachment->name) ?: 'file';

        return (new Response((string) file_get_contents($path)))
            ->withHeader('Content-Type', (string) $attachment->mime)
            ->withHeader('Content-Length', (string) filesize($path))
            ->withHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($attachment->name))
            ->withHeader('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox")
            ->withHeader('Cache-Control', 'private, max-age=3600')
            ->withHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    /** @param array{attachment: string} $params */
    public function destroy(Request $request, array $params): Response
    {
        $card = $this->attachments->remove($this->user($request)->id, $params['attachment']);

        return $this->redirect($this->cardPath($card) . '#attachments', 'Attachment removed.');
    }

    private function card(int $userId, string $publicId): CardSummary
    {
        $card = $this->cards->findByPublicId($publicId) ?? throw AccessDenied::hidden();
        $this->gate->authorize($userId, $card->boardId, Permission::ViewBoard);

        return $card;
    }

    private function cardPath(CardSummary $card): string
    {
        return '/cards/' . $card->publicId;
    }
}
