<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\UrlGenerator;
use Tests\TestCase;

final class UrlGeneratorTest extends TestCase
{
    public function testBuildsLinksFromTheBasePath(): void
    {
        $this->assertSame('/', (new UrlGenerator(''))->to('/'));
        $this->assertSame('/tasks/3/edit', (new UrlGenerator(''))->to('tasks/3/edit'));
        $this->assertSame('/to_do_list/login', (new UrlGenerator('/to_do_list/'))->to('/login'));
    }

    public function testDetectsTheBasePath(): void
    {
        $this->assertSame('', UrlGenerator::detectBasePath(['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/login']));

        // XAMPP: http://localhost/to_do_list/login, forwarded to public/index.php
        $this->assertSame('/to_do_list', UrlGenerator::detectBasePath([
            'SCRIPT_NAME' => '/to_do_list/public/index.php',
            'REQUEST_URI' => '/to_do_list/login',
        ]));

        // the public folder itself is the web root's sub folder
        $this->assertSame('/to_do_list/public', UrlGenerator::detectBasePath([
            'SCRIPT_NAME' => '/to_do_list/public/index.php',
            'REQUEST_URI' => '/to_do_list/public/login',
        ]));
    }

    public function testAssetsCarryAVersion(): void
    {
        $this->assertSame('/app/assets/css/style.css?v=1', (new UrlGenerator('/app'))->asset('css/style.css'));
    }
}
