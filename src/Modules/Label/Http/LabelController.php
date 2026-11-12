<?php

declare(strict_types=1);

namespace App\Modules\Label\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Label\Application\LabelService;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class LabelController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private LabelService $labels,
        private BoardDirectory $boards,
        private CardQueries $cards,
    ) {
        parent::__construct($view, $url, $session);
    }

    /**
     * Body: name, color, and optionally card (public id) to attach it to right away.
     *
     * @param array{board: string} $params
     */
    public function store(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->findByPublicId($params['board']) ?? throw AccessDenied::hidden();
        $card = $request->input('card') !== '' ? $this->cards->findByPublicId($request->input('card')) : null;
        $back = $card && $card->boardId === $board->id ? '/cards/' . $card->publicId : '/boards/' . $board->publicId;

        try {
            $label = $this->labels->ensureLabel($user->id, $board->id, $request->input('name'), $request->input('color'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $back);
        }

        if ($card !== null && $card->boardId === $board->id) {
            $this->labels->attach($user->id, $card->id, $label->id);
        }

        return $this->redirect($back);
    }

    /** @param array{card: string, id: int} $params */
    public function toggle(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $card = $this->cards->findByPublicId($params['card']) ?? throw AccessDenied::hidden();
        $attached = $this->labels->toggle($user->id, $card->id, $params['id']);

        return $request->wantsJson()
            ? Response::json(['ok' => true, 'attached' => $attached])
            : $this->redirect('/cards/' . $card->publicId);
    }
}
