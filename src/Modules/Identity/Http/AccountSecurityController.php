<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Identity\Domain\SecurityLog;
use App\Session\Session;
use App\View\View;

/** The signed-in user's own security log: recent sign-ins, resets, refused requests. */
final class AccountSecurityController extends Controller
{
    public function __construct(View $view, Url $url, Session $session, private SecurityLog $log)
    {
        parent::__construct($view, $url, $session);
    }

    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return $this->render('account/security', [
            'title'  => 'Account security',
            'user'   => $user,
            'events' => $this->log->forUser($user->id, 50),
        ]);
    }
}
