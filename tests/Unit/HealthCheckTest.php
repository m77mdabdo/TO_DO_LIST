<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Database\ConnectionFactory;
use App\Http\HealthCheck;
use PDOException;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    public function testOkWhenTheDatabaseAnswers(): void
    {
        [$status, $body] = (new HealthCheck(static fn () => ConnectionFactory::sqlite(':memory:')))->run();

        $this->assertSame(200, $status);
        $this->assertSame('{"status":"ok"}', $body);
    }

    public function testUnavailableWithoutDetailsWhenItDoesNot(): void
    {
        $check = new HealthCheck(static function () {
            throw new PDOException('SQLSTATE[HY000] [2002] Connection refused to db:3306 as user todo');
        });

        [$status, $body] = $check->run();

        $this->assertSame(503, $status);
        $this->assertSame('{"status":"unavailable"}', $body);
    }
}
