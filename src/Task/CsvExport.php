<?php

declare(strict_types=1);

namespace App\Task;

/**
 * Turns a user's tasks into CSV that opens cleanly in Excel or Google Sheets.
 */
final class CsvExport
{
    private const HEADER = ['Title', 'Notes', 'Status', 'Priority', 'List', 'Due date', 'Created', 'Completed'];

    /** @param iterable<Task> $tasks */
    public function build(iterable $tasks): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM, so Excel reads Arabic text correctly
        fputcsv($out, self::HEADER, escape: '');

        foreach ($tasks as $task) {
            fputcsv($out, array_map($this->safe(...), [
                $task->title,
                $task->notes,
                $task->status->label(),
                $task->priority->label(),
                $task->category,
                $task->dueDate?->toString(),
                $task->createdAt->format('Y-m-d H:i'),
                $task->completedAt?->format('Y-m-d H:i'),
            ]), escape: '');
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Stops spreadsheet apps from running text that starts like a formula. */
    private function safe(?string $value): string
    {
        $value ??= '';

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }
}
