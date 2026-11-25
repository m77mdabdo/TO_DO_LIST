<?php

declare(strict_types=1);

namespace App\Http;

use Closure;
use PDO;
use Throwable;

/**
 * Answers GET /up for load balancers and container health checks: 200 when the
 * database answers, 503 when it doesn't. index.php calls it before the session
 * and the rest of the app start, so a check every few seconds costs one query.
 * The body never includes error details.
 */
final class HealthCheck
{
    /** @param Closure(): PDO $connect */
    public function __construct(private Closure $connect)
    {
    }

    /** @return array{int, string} the status code and the JSON body */
    public function run(): array
    {
        try {
            ($this->connect)()->query('SELECT 1')->fetchColumn();

            return [200, '{"status":"ok"}'];
        } catch (Throwable) {
            return [503, '{"status":"unavailable"}'];
        }
    }
}
