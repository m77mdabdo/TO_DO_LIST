<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Controllers\Controller;
use App\Core\Config;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Identity\Application\AccountSettings;
use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Application\DeviceSessions;
use App\Modules\Identity\Application\TwoFactor;
use App\Modules\Identity\Domain\SecurityLog;
use App\Modules\Identity\Domain\Totp;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

/** The signed-in user's security: two-step sign-in, signed-in devices, and the security log. */
final class AccountSecurityController extends Controller
{
    private const CODES_FLASH = 'two_factor_codes';

    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private SecurityLog $log,
        private TwoFactor $twoFactor,
        private DeviceSessions $devices,
        private AuthService $auth,
        private AccountSettings $settings,
        private Config $config,
    ) {
        parent::__construct($view, $url, $session);
    }

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $enabled = $this->twoFactor->enabled($user->id);
        $pending = $enabled ? null : $this->twoFactor->pendingSecret();
        $codes = $this->session->pullFlash(self::CODES_FLASH);

        return $this->render('account/security', [
            'title'        => 'Account security',
            'user'         => $user,
            'events'       => $this->log->forUser($user->id, 50),
            'twoFactorOn'  => $enabled,
            'available'    => $this->twoFactor->available(),
            'setupKey'     => $pending !== null ? Totp::grouped($pending) : null,
            'setupUri'     => $pending !== null ? Totp::uri($pending, $user->email, (string) $this->config->get('app_name', 'Todo Board')) : null,
            'codesLeft'    => $enabled ? $this->twoFactor->recoveryCodesLeft($user->id) : 0,
            'freshCodes'   => is_array($codes) ? array_values(array_map('strval', $codes)) : [],
            'sessions'     => $this->devices->forUser($user->id, $this->auth->deviceSecret()),
        ]);
    }

    public function startTwoFactor(Request $request): Response
    {
        $this->user($request);
        try {
            $this->twoFactor->begin();
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/account/security#two-factor');
        }

        return $this->redirect('/account/security#two-factor');
    }

    /** Body: code. */
    public function confirmTwoFactor(Request $request): Response
    {
        $user = $this->user($request);
        try {
            $codes = $this->twoFactor->confirm($user->id, $request->input('code'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/account/security#two-factor');
        }
        $this->session->flash(self::CODES_FLASH, $codes);
        $this->log->record('two_factor_enabled', $user->id, $request->ip);

        return $this->redirect('/account/security#two-factor', 'Two-step sign-in is on.');
    }

    /** Body: password. */
    public function disableTwoFactor(Request $request): Response
    {
        $user = $this->user($request);
        try {
            $this->settings->assertPassword($user->id, $request->raw('password'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/account/security#two-factor');
        }
        $this->twoFactor->disable($user->id);
        $this->log->record('two_factor_disabled', $user->id, $request->ip);

        return $this->redirect('/account/security#two-factor', 'Two-step sign-in is off.');
    }

    /** Body: password. */
    public function newRecoveryCodes(Request $request): Response
    {
        $user = $this->user($request);
        try {
            $this->settings->assertPassword($user->id, $request->raw('password'));
            $this->session->flash(self::CODES_FLASH, $this->twoFactor->regenerateRecoveryCodes($user->id));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/account/security#two-factor');
        }

        return $this->redirect('/account/security#two-factor', 'New recovery codes made. The old ones no longer work.');
    }

    /** @param array{id: string} $params */
    public function endSession(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $this->devices->end($user->id, (int) $params['id']);

        return $this->redirect('/account/security#devices', 'That device was signed out.');
    }

    public function endOtherSessions(Request $request): Response
    {
        $user = $this->user($request);
        $this->auth->signOutOtherDevices($user->id);
        $this->log->record('signed_out_others', $user->id, $request->ip);

        return $this->redirect('/account/security#devices', 'Every other device was signed out.');
    }

    public function endAllSessions(Request $request): Response
    {
        $user = $this->user($request);
        $this->log->record('signed_out_everywhere', $user->id, $request->ip);
        $this->auth->signOutEverywhere($user->id);

        return $this->redirect('/login', 'You were signed out on every device.');
    }
}
