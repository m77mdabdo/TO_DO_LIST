<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\I18n\Translator;
use App\Modules\Identity\Application\AccountSettings;
use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Domain\SecurityLog;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

/** The person's own settings page: name, language, their data, deleting the account. */
final class AccountController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private AccountSettings $settings,
        private AuthService $auth,
        private SecurityLog $log,
    ) {
        parent::__construct($view, $url, $session);
    }

    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return $this->render('account/settings', [
            'title'     => 'Your account',
            'user'      => $user,
            'locale'    => $this->settings->locale($user->id),
            'locales'   => array_keys(Translator::SUPPORTED),
            'blockers'  => $this->settings->blockers($user->id),
        ]);
    }

    /** Body: name, locale. */
    public function profile(Request $request): Response
    {
        $user = $this->user($request);
        try {
            $this->settings->updateProfile($user->id, ['name' => $request->input('name'), 'locale' => $request->input('locale')]);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/account#profile');
        }
        $this->auth->refresh();

        return $this->redirect('/account#profile', 'Saved.');
    }

    public function export(Request $request): Response
    {
        $user = $this->user($request);
        $this->log->record('data_exported', $user->id, $request->ip);

        return (new Response((string) json_encode($this->settings->export($user->id), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)))
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="my-data.json"')
            ->withHeader('Cache-Control', 'no-store');
    }

    /** Body: password, confirm (the word DELETE). */
    public function delete(Request $request): Response
    {
        $user = $this->user($request);
        if (strtoupper(trim($request->input('confirm'))) !== 'DELETE') {
            return $this->backWithErrors(new ValidationException(new \App\Validation\ValidationResult(['confirm' => 'Type DELETE to confirm.'])), $request, '/account#delete');
        }
        try {
            $this->settings->delete($user->id, $request->raw('password'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/account#delete');
        }
        $this->auth->logout();

        return $this->redirect('/login', 'Your account was deleted.');
    }
}
