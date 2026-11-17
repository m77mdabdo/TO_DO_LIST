<?php

declare(strict_types=1);

namespace App\Modules\Import\Application;

use App\Database\Transactions;
use App\Http\UploadedFile;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardCatalog;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardWriter;
use App\Modules\Label\Contract\Label;
use App\Modules\Label\Contract\LabelCatalog;
use App\Modules\Identity\Contract\UserDirectory;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;

/**
 * Cards from a spreadsheet (CSV) into an existing board, in three steps:
 * upload (read and kept as pending), preview (choose which column is what,
 * see every row's problems), and run (one transaction: all the good rows
 * arrive, or none do). Rows with a problem are listed and left out.
 */
final class CsvImport
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_ROWS = 2000;
    public const MAX_COLUMNS = 40;
    public const TITLE_MAX = 200;
    public const DESCRIPTION_MAX = 10000;

    /** What a column can be, in the order shown. */
    public const FIELDS = [
        'title'       => 'Title',
        'description' => 'Description',
        'list'        => 'List',
        'labels'      => 'Labels',
        'start_date'  => 'Start date',
        'due_date'    => 'Due date',
        'members'     => 'Members',
        'complete'    => 'Done',
    ];

    /** Header names (lower case) each field is guessed from. */
    private const GUESSES = [
        'title'       => ['title', 'name', 'card', 'card name', 'task', 'summary', 'العنوان', 'عنوان', 'الاسم', 'المهمة', 'البطاقة', 'اسم البطاقة'],
        'description' => ['description', 'desc', 'notes', 'details', 'الوصف', 'وصف', 'ملاحظات', 'التفاصيل'],
        'list'        => ['list', 'list name', 'status', 'column', 'stage', 'القائمة', 'الحالة', 'العمود', 'المرحلة'],
        'labels'      => ['labels', 'label', 'tags', 'tag', 'category', 'الوسوم', 'الوسم', 'التصنيف', 'الفئة'],
        'start_date'  => ['start', 'start date', 'starts', 'begin', 'البدء', 'تاريخ البدء', 'يبدأ'],
        'due_date'    => ['due', 'due date', 'deadline', 'الموعد', 'تاريخ الاستحقاق', 'الموعد النهائي', 'تاريخ التسليم'],
        'members'     => ['members', 'member', 'assignee', 'assignees', 'assigned to', 'owner', 'people', 'الأعضاء', 'المسؤول', 'المكلف', 'الأشخاص'],
        'complete'    => ['done', 'complete', 'completed', 'finished', 'مكتمل', 'مكتملة', 'منجز', 'تم'],
    ];

    private const YES = ['1', 'yes', 'y', 'true', 'x', 'done', 'complete', 'completed', 'نعم', 'مكتمل', 'مكتملة', 'تم', 'منجز'];

    public function __construct(
        private ImportStore $store,
        private BoardDirectory $boards,
        private BoardCatalog $catalog,
        private CardQueries $cards,
        private CardWriter $writer,
        private LabelCatalog $labels,
        private AccessGate $gate,
        private UserDirectory $users,
        private Transactions $transactions,
        private Clock $clock,
    ) {
    }

    /** Reads the file and keeps it as a pending import. Returns its public id. */
    public function upload(int $actorId, BoardSummary $board, ?UploadedFile $file): string
    {
        $this->assertCanImport($actorId, $board);
        if ($file === null || $file->error !== UPLOAD_ERR_OK || !is_file($file->tmpPath)) {
            $this->fail('file', 'Choose a CSV file.');
        }
        if ($file->size > self::MAX_BYTES) {
            $this->fail('file', 'The file is larger than 2 MB.');
        }
        $text = (string) file_get_contents($file->tmpPath);
        $rows = self::parse($text);
        if (count($rows) < 2) {
            $this->fail('file', 'The file needs a header row and at least one row of cards.');
        }
        $header = array_map(static fn (string $h) => mb_substr(trim($h), 0, 80), array_shift($rows));
        if (count($header) > self::MAX_COLUMNS) {
            $this->fail('file', 'The file has more than 40 columns.');
        }
        if (count($rows) > self::MAX_ROWS) {
            $this->fail('file', 'The file has more than 2000 rows. Split it into smaller files.');
        }

        return $this->store->create($actorId, $board->workspaceId, $board->id, 'csv', $file->clientName, hash('sha256', $text), ['header' => $header, 'rows' => $rows]);
    }

    /**
     * Rows of CSV text as lists of cells. Accepts a BOM, ",", ";" or tab
     * separators (guessed from the first line), quoted cells with new lines,
     * and files saved by Excel in Windows Arabic (cp1256) or UTF-16.
     *
     * @return list<list<string>>
     */
    public static function parse(string $text): array
    {
        if (str_starts_with($text, "\xFF\xFE") || str_starts_with($text, "\xFE\xFF")) {
            $text = (string) mb_convert_encoding(substr($text, 2), 'UTF-8', str_starts_with($text, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
        }
        $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (!mb_check_encoding($text, 'UTF-8')) {
            $converted = @iconv('WINDOWS-1256', 'UTF-8//IGNORE', $text);
            $text = $converted !== false ? $converted : mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $first = strtok($text, "\n") ?: '';
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($counts);
        $separator = (string) array_key_first($counts);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, $separator, '"', '')) !== false) {
            if ($row === [null] || implode('', array_map('strval', $row)) === '') {
                continue;
            }
            $rows[] = array_map(static fn ($cell) => trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $cell)), $row);
        }
        fclose($stream);

        return $rows;
    }

    /**
     * Column for each field, guessed from the header names.
     *
     * @param list<string> $header
     * @return array<string, int> field => column index (-1 = not used)
     */
    public static function guessMapping(array $header): array
    {
        $map = array_fill_keys(array_keys(self::FIELDS), -1);
        foreach ($header as $i => $name) {
            $name = mb_strtolower(trim($name));
            foreach (self::GUESSES as $field => $names) {
                if ($map[$field] === -1 && in_array($name, $names, true)) {
                    $map[$field] = $i;
                    continue 2;
                }
            }
        }
        if ($map['title'] === -1 && $header !== []) {
            $map['title'] = 0;
        }

        return $map;
    }

    /**
     * The mapping sent by the preview form, checked against the header.
     *
     * @param list<string> $header
     * @param array<string, mixed> $input field => column index
     * @return array<string, int>
     */
    public static function mapping(array $header, array $input): array
    {
        if ($input === []) {
            return self::guessMapping($header);
        }
        $map = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $value = $input[$field] ?? '-1';
            $map[$field] = is_string($value) && ctype_digit($value) && (int) $value < count($header) ? (int) $value : -1;
        }

        return $map;
    }

    /**
     * What would happen to each row, without changing anything.
     *
     * @param list<list<string>> $rows
     * @param array<string, int> $map
     * @param array{create_lists: bool, skip_duplicates: bool} $options
     * @return array{rows: list<array<string, mixed>>, ok: int, skipped: int, errors: int, newLists: list<string>, newLabels: list<string>, ignoredLabels: list<string>}
     */
    public function plan(int $actorId, BoardSummary $board, array $rows, array $map, array $options): array
    {
        $lists = [];
        foreach ($this->boards->openLists($board->id) as $list) {
            $lists[mb_strtolower($list->name)] = $list;
        }
        $firstList = $lists !== [] ? reset($lists) : null;
        $labels = [];
        foreach ($this->labels->forBoard($board->id) as $label) {
            $labels[mb_strtolower($label->name)] = $label;
        }
        $canLabels = $this->gate->can($actorId, $board->id, Permission::ManageLabels);
        $people = $this->people($board);
        $existing = [];
        foreach ($this->cards->openCardsByList($board->id) as $listId => $cards) {
            foreach ($cards as $card) {
                $existing[$listId . '|' . mb_strtolower($card->title)] = true;
            }
        }

        $out = [];
        $newLists = $newLabels = $ignoredLabels = [];
        $seen = [];
        $counts = ['ok' => 0, 'skipped' => 0, 'errors' => 0];
        foreach ($rows as $i => $row) {
            $cell = static fn (string $field): string => $map[$field] >= 0 ? (string) ($row[$map[$field]] ?? '') : '';
            $problems = [];
            $notes = [];
            $title = $cell('title');
            if ($title === '') {
                $problems[] = ['No title.', []];
            } elseif (mb_strlen($title) > self::TITLE_MAX) {
                $problems[] = ['The title is longer than 200 characters.', []];
            }
            $description = $cell('description');
            if (mb_strlen($description) > self::DESCRIPTION_MAX) {
                $problems[] = ['The description is longer than 10000 characters.', []];
            }

            $listName = $cell('list');
            $list = $listName !== '' ? ($lists[mb_strtolower($listName)] ?? null) : $firstList;
            $rowNewList = null;
            if ($list === null && $listName !== '' && $options['create_lists'] && mb_strlen($listName) <= 100) {
                $rowNewList = $listName;
            } elseif ($list === null) {
                $problems[] = $listName === '' ? ['The board has no list to put it in.', []] : ['No list called ":name" on this board.', ['name' => $listName]];
            }

            $start = self::date($cell('start_date'));
            $due = self::date($cell('due_date'));
            if ($start === false) {
                $problems[] = ['The start date is not a date (use 2026-01-31 or 31/01/2026).', []];
            }
            if ($due === false) {
                $problems[] = ['The due date is not a date (use 2026-01-31 or 31/01/2026).', []];
            }
            if (is_string($start) && is_string($due) && $start > $due) {
                $problems[] = ['The start date is after the due date.', []];
            }

            $rowLabels = [];
            $rowNewLabels = [];
            foreach (self::split($cell('labels')) as $name) {
                $key = mb_strtolower($name);
                if (isset($labels[$key])) {
                    $rowLabels[] = $labels[$key]->name;
                } elseif ($canLabels && mb_strlen($name) <= 60) {
                    $rowNewLabels[$key] = $name;
                    $rowLabels[] = $name;
                } else {
                    $ignoredLabels[$key] = $name;
                    $notes[] = ['The label ":name" is not on this board and will be left out.', ['name' => $name]];
                }
            }
            $members = [];
            foreach (self::split($cell('members')) as $who) {
                $id = $people[mb_strtolower($who)] ?? null;
                if ($id !== null) {
                    $members[] = $id;
                } else {
                    $notes[] = ['":name" is not someone who can open this board, so they are not added.', ['name' => $who]];
                }
            }

            $dupKey = ($list?->id ?? 'new:' . mb_strtolower($listName)) . '|' . mb_strtolower($title);
            $duplicate = $title !== '' && (isset($existing[$dupKey]) || isset($seen[$dupKey]));
            $seen[$dupKey] = true;
            $status = $problems !== [] ? 'error' : ($duplicate && $options['skip_duplicates'] ? 'skip' : 'ok');
            if ($duplicate && $status !== 'error') {
                $notes[] = [$options['skip_duplicates'] ? 'A card with this title is already in this list: skipped.' : 'A card with this title is already in this list: it will be added again.', []];
            }
            $counts[$status === 'error' ? 'errors' : ($status === 'skip' ? 'skipped' : 'ok')]++;
            // Only rows that will be imported make new lists and labels.
            if ($status === 'ok') {
                if ($rowNewList !== null) {
                    $newLists[mb_strtolower($rowNewList)] = $rowNewList;
                }
                $newLabels += $rowNewLabels;
            }

            $out[] = [
                'line'        => $i + 2,
                'status'      => $status,
                'problems'    => $problems,
                'notes'       => $notes,
                'names'       => ['list' => $listName, 'labels' => $cell('labels'), 'members' => $cell('members')],
                'title'       => $title,
                'description' => $description,
                'list'        => $list?->name ?? $listName,
                'listId'      => $list?->id,
                'start'       => is_string($start) ? $start : null,
                'due'         => is_string($due) ? $due : null,
                'labels'      => $rowLabels,
                'members'     => $members,
                'complete'    => in_array(mb_strtolower($cell('complete')), self::YES, true),
            ];
        }

        return $counts + ['rows' => $out, 'newLists' => array_values($newLists), 'newLabels' => array_values($newLabels), 'ignoredLabels' => array_values($ignoredLabels)];
    }

    /**
     * Imports the good rows in one transaction and closes the import.
     *
     * @param array<string, mixed> $import a pending import from ImportStore
     * @param array<string, int> $map
     * @param array{create_lists: bool, skip_duplicates: bool} $options
     * @return array{cards: int, skipped: int, errors: int, lists: int, labels: int}
     */
    public function run(int $actorId, BoardSummary $board, array $import, array $map, array $options): array
    {
        $this->assertCanImport($actorId, $board);
        $plan = $this->plan($actorId, $board, $import['data']['rows'], $map, $options);
        if ($plan['ok'] === 0) {
            $this->fail('import', 'There is no row to import.');
        }

        $summary = $this->transactions->run(function () use ($actorId, $board, $plan): array {
            $lists = [];
            foreach ($plan['newLists'] as $name) {
                $lists[mb_strtolower($name)] = $this->catalog->addList($actorId, $board->id, $name)->id;
            }
            $labels = [];
            foreach ($this->labels->forBoard($board->id) as $label) {
                $labels[mb_strtolower($label->name)] = $label;
            }
            $colors = Label::COLORS;
            foreach ($plan['newLabels'] as $n => $name) {
                $labels[mb_strtolower($name)] = $this->labels->ensureLabel($actorId, $board->id, $name, $colors[$n % count($colors)]);
            }
            $now = $this->clock->now()->format('Y-m-d H:i:s');
            $count = 0;
            foreach ($plan['rows'] as $row) {
                if ($row['status'] !== 'ok') {
                    continue;
                }
                $listId = $row['listId'] ?? $lists[mb_strtolower((string) $row['list'])];
                try {
                    $card = $this->writer->createCard($actorId, $board->id, $listId, $row['title'], [
                        'description'  => $row['description'] !== '' ? $row['description'] : null,
                        'start_date'   => $row['start'],
                        'due_date'     => $row['due'],
                        'completed_at' => $row['complete'] ? $now : null,
                    ]);
                } catch (ValidationException $e) {
                    throw new ValidationException(new ValidationResult(['import' => 'Row ' . $row['line'] . ': ' . current($e->errors())]));
                }
                foreach ($row['labels'] as $name) {
                    $this->labels->attach($actorId, $card->id, $labels[mb_strtolower($name)]->id);
                }
                foreach ($row['members'] as $memberId) {
                    $this->writer->assign($actorId, $card, $memberId);
                }
                $count++;
            }

            return ['cards' => $count, 'skipped' => $plan['skipped'], 'errors' => $plan['errors'], 'lists' => count($plan['newLists']), 'labels' => count($plan['newLabels'])];
        });

        $this->store->finish($import['id'], $import['publicId'], $board->id, $summary);

        return $summary;
    }

    public function assertCanImport(int $actorId, BoardSummary $board): void
    {
        $this->gate->authorize($actorId, $board->id, Permission::EditCards);
        if ($board->archived) {
            $this->fail('file', 'This board is archived.');
        }
    }

    /** Y-m-d, false when it isn't a date, null when empty. Day-first for slashes (31/01/2026). */
    public static function date(string $value): string|false|null
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = strtr($value, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})(?:[ T].*)?$/', $value, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})(?:[ T].*)?$/', $value, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return false;
        }

        return checkdate($mo, $d, $y) && $y >= 1970 && $y <= 2200 ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : false;
    }

    /** @return list<string> */
    private static function split(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;،|]/u', $value) ?: []), static fn ($v) => $v !== '')));
    }

    /** @return array<string, int> lower-case email and name => user id, for people who can open the board */
    private function people(BoardSummary $board): array
    {
        $out = [];
        foreach ($this->users->findMany(array_keys($this->gate->peopleOn($board->id))) as $user) {
            $out[mb_strtolower($user->email)] = $user->id;
            $out[mb_strtolower($user->name)] ??= $user->id;
        }

        return $out;
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
