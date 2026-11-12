<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\I18n\Translator;

/** Switches the interface language and goes back to the page the user was on. */
final class LocaleController
{
    public function __construct(private Translator $translator, private Url $url)
    {
    }

    /** @param array{locale: string} $params */
    public function update(Request $request, array $params): Response
    {
        $this->translator->setLocale($params['locale']);

        return Response::redirect($this->url->to($this->safeBackPath($request)));
    }

    /**
     * The path part of the Referer only, so the redirect always stays on
     * this site whatever the header says (no open redirect).
     */
    private function safeBackPath(Request $request): string
    {
        $path = (string) parse_url((string) $request->header('referer'), PHP_URL_PATH);
        $base = rtrim($this->url->to('/'), '/');

        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return '/';
        }

        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }

        return $path;
    }
}
