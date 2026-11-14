<?php

declare(strict_types=1);

namespace App\Modules\Board\Http;

use App\Modules\Board\Contract\ListSummary;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Label\Contract\Label;

/**
 * A board's open cards as CSV that opens cleanly in Excel or Google Sheets,
 * in board order (list by list, card by card).
 */
final class BoardCsv
{
    private const HEADER = ['List', 'Title', 'Description', 'Labels', 'Due date', 'Completed', 'Created'];

    /**
     * @param list<ListSummary> $lists
     * @param array<int, list<CardSummary>> $cardsByList
     * @param array<int, list<Label>> $labels
     */
    public static function make(array $lists, array $cardsByList, array $labels): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM, so Excel reads Arabic text correctly
        fputcsv($out, self::HEADER, escape: '');

        foreach ($lists as $list) {
            foreach ($cardsByList[$list->id] ?? [] as $card) {
                fputcsv($out, array_map(self::safe(...), [
                    $list->name,
                    $card->title,
                    $card->description,
                    implode(', ', array_map(static fn (Label $l) => $l->name, $labels[$card->id] ?? [])),
                    $card->dueDate(),
                    $card->completedAt,
                    $card->createdAt,
                ]), escape: '');
            }
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Stops spreadsheet apps from running text that starts like a formula. */
    public static function safe(?string $value): string
    {
        $value ??= '';

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }
}
