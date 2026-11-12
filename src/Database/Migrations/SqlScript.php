<?php

declare(strict_types=1);

namespace App\Database\Migrations;

/**
 * Splits a .sql file into single statements in one O(n) pass over its lines.
 *
 * A statement ends with ";" at the end of a line. Trigger bodies
 * (CREATE TRIGGER ... BEGIN ... END;) contain inner semicolons, so
 * everything up to their closing END; stays one statement.
 */
final class SqlScript
{
    /** @return list<string> */
    public static function statements(string $sql): array
    {
        $statements = [];
        $buffer = [];
        $inTrigger = false;

        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            $trimmed = trim($line);

            if ($buffer === [] && ($trimmed === '' || str_starts_with($trimmed, '--'))) {
                continue;
            }

            $buffer[] = $line;

            if (!$inTrigger && preg_match('/^CREATE\s+(TEMP\s+|TEMPORARY\s+)?TRIGGER\b/i', $trimmed)) {
                $inTrigger = true;
            }

            $ends = $inTrigger ? (bool) preg_match('/^END\s*;$/i', $trimmed) : str_ends_with($trimmed, ';');

            if ($ends) {
                $statements[] = rtrim(trim(implode("\n", $buffer)), ';');
                $buffer = [];
                $inTrigger = false;
            }
        }

        if (trim(implode('', $buffer)) !== '') {
            $statements[] = rtrim(trim(implode("\n", $buffer)), ';');
        }

        return $statements;
    }
}
