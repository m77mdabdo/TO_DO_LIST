<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Task\CsvExport;
use Tests\Support\TaskFactory;
use Tests\TestCase;

final class CsvExportTest extends TestCase
{
    public function testExportsOneRowPerTask(): void
    {
        $csv = (new CsvExport())->build([
            TaskFactory::make(['title' => 'Pay rent', 'category' => 'Home', 'due_date' => '2026-02-01']),
            TaskFactory::make(['title' => 'تقرير', 'status' => 'done', 'completed_at' => '2026-01-12 10:00:00']),
        ]);

        $lines = explode("\n", trim($csv));

        $this->assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"));
        $this->assertCount(3, $lines);
        $this->assertSame('"Pay rent",,"To do",Normal,Home,2026-02-01,"2026-01-10 09:00",', $lines[1]);
        $this->assertTrue(str_contains($lines[2], 'تقرير'));
    }

    public function testFormulasAreTurnedIntoText(): void
    {
        $csv = (new CsvExport())->build([TaskFactory::make(['title' => '=HYPERLINK("http://evil.test")'])]);

        $this->assertTrue(str_contains($csv, "\"'=HYPERLINK"));
    }
}
