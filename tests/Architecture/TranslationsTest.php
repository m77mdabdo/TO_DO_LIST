<?php

declare(strict_types=1);

namespace Tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Every literal string a template passes to t() / te() must have an
 * Arabic entry, so the default (Arabic) interface never shows stray English.
 */
final class TranslationsTest extends TestCase
{
    public function testEveryTemplateStringHasAnArabicTranslation(): void
    {
        $catalog = require BASE_PATH . '/lang/ar.php';
        $missing = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/views', RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $code = (string) file_get_contents($file->getPathname());
            preg_match_all("/->te?\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $code, $matches);
            // Activity sentences are kept in an array and translated through a variable.
            preg_match_all("/'(:actor[^']*)'/", $code, $sentences);

            foreach ([...$matches[1], ...$sentences[1]] as $raw) {
                $key = stripslashes($raw);
                if (!isset($catalog[$key])) {
                    $missing[$key] = $file->getFilename();
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function testArabicIsTheDefaultAndRightToLeft(): void
    {
        $html = (string) (new \Tests\Support\App(locale: 'ar'))->get('/login')->body;

        $this->assertTrue(str_contains($html, 'lang="ar" dir="rtl"'));
        $this->assertTrue(str_contains($html, 'تسجيل الدخول'));
    }

    public function testSwitchingToEnglishStaysOnThisSite(): void
    {
        $app = new \Tests\Support\App(locale: 'ar');
        $response = $app->send(new \App\Http\Request('POST', '/locale/en', [], [
            '_token' => $app->container->get(\App\Security\Csrf::class)->token(),
        ], ['referer' => 'https://evil.example//phish/login'], '10.0.0.1'));

        $this->assertSame('/', $response->header('Location'));
        $this->assertTrue(str_contains((string) $app->get('/login')->body, 'lang="en" dir="ltr"'));
    }
}
