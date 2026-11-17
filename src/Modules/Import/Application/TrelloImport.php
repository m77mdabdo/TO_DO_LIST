<?php

declare(strict_types=1);

namespace App\Modules\Import\Application;

use App\Database\Transactions;
use App\Http\UploadedFile;
use App\Modules\Attachment\Contract\AttachmentFiles;
use App\Modules\Board\Contract\BoardCatalog;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Checklist\Contract\ChecklistWriter;
use App\Modules\Discussion\Contract\CommentWriter;
use App\Modules\Card\Contract\CardWriter;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Label\Contract\Label;
use App\Modules\Label\Contract\LabelCatalog;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceSummary;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use JsonException;

/**
 * A board exported from Trello (Board menu > Print, export and share >
 * Export as JSON) as a new board of the current workspace: lists, cards,
 * labels, dates, checklists, comments (with who wrote them and when),
 * members matched by name, and attachments as links. Archived lists and
 * cards are left out. Nothing is fetched from Trello. A board that was
 * imported before is only imported again when the person says so.
 */
final class TrelloImport
{
    public const MAX_BYTES = 20 * 1024 * 1024;
    public const MAX_CARDS = 5000;

    /** Trello's label colors in ours. */
    private const COLORS = [
        'green' => 'green', 'yellow' => 'yellow', 'orange' => 'orange', 'red' => 'red', 'purple' => 'purple',
        'blue' => 'blue', 'sky' => 'sky', 'lime' => 'green', 'pink' => 'pink', 'black' => 'slate',
    ];

    public function __construct(
        private ImportStore $store,
        private WorkspaceDirectory $workspaces,
        private BoardCatalog $catalog,
        private CardWriter $writer,
        private LabelCatalog $labels,
        private ChecklistWriter $checklists,
        private CommentWriter $comments,
        private AttachmentFiles $files,
        private UserDirectory $users,
        private Transactions $transactions,
        private \App\Modules\Access\Contract\BoardMembers $boardMembers,
        private \App\Support\Clock $clock,
    ) {
    }

    public function upload(int $actorId, WorkspaceSummary $workspace, ?UploadedFile $file): string
    {
        $this->assertCanCreate($workspace);
        if ($file === null || $file->error !== UPLOAD_ERR_OK || !is_file($file->tmpPath)) {
            $this->fail('file', 'Choose the JSON file exported from Trello.');
        }
        if ($file->size > self::MAX_BYTES) {
            $this->fail('file', 'The file is larger than 20 MB.');
        }
        try {
            $json = json_decode((string) file_get_contents($file->tmpPath), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->fail('file', 'This is not a Trello JSON export.');
        }
        $board = is_array($json) ? self::normalize($json) : null;
        if ($board === null) {
            $this->fail('file', 'This is not a Trello JSON export.');
        }
        if (count($board['cards']) > self::MAX_CARDS) {
            $this->fail('file', 'The board has more than 5000 cards.');
        }

        return $this->store->create($actorId, $workspace->id, null, 'trello', $file->clientName, $board['sourceId'], $board);
    }

    /**
     * Only what is imported, in plain arrays; null when it isn't a Trello board.
     *
     * @param array<mixed> $json
     * @return array<string, mixed>|null
     */
    public static function normalize(array $json): ?array
    {
        if (!is_string($json['name'] ?? null) || !is_array($json['lists'] ?? null) || !is_array($json['cards'] ?? null)) {
            return null;
        }
        $str = static fn (mixed $v, int $max): string => is_string($v) ? mb_substr(trim($v), 0, $max) : '';
        $byPos = static fn (array $a, array $b): int => ((float) ($a['pos'] ?? 0)) <=> ((float) ($b['pos'] ?? 0));

        $people = [];
        foreach (is_array($json['members'] ?? null) ? $json['members'] : [] as $m) {
            if (is_array($m) && is_string($m['id'] ?? null)) {
                $people[$m['id']] = $str($m['fullName'] ?? $m['username'] ?? '', 100);
            }
        }
        $labels = [];
        foreach (is_array($json['labels'] ?? null) ? $json['labels'] : [] as $l) {
            if (is_array($l) && is_string($l['id'] ?? null)) {
                $color = self::COLORS[(string) ($l['color'] ?? '')] ?? 'slate';
                $labels[$l['id']] = ['name' => $str($l['name'] ?? '', 60) ?: ucfirst((string) ($l['color'] ?? 'label')), 'color' => $color];
            }
        }
        $lists = array_values(array_filter($json['lists'], static fn ($l) => is_array($l) && is_string($l['id'] ?? null)));
        usort($lists, $byPos);
        $openLists = [];
        $skippedLists = 0;
        foreach ($lists as $l) {
            if (!empty($l['closed'])) {
                $skippedLists++;
                continue;
            }
            $openLists[$l['id']] = ['id' => $l['id'], 'name' => $str($l['name'] ?? '', 100) ?: 'List'];
        }

        $checklists = [];
        foreach (is_array($json['checklists'] ?? null) ? $json['checklists'] : [] as $c) {
            if (!is_array($c) || !is_string($c['idCard'] ?? null)) {
                continue;
            }
            $items = array_values(array_filter(is_array($c['checkItems'] ?? null) ? $c['checkItems'] : [], 'is_array'));
            usort($items, $byPos);
            $checklists[$c['idCard']][] = [
                'title' => $str($c['name'] ?? '', 100) ?: 'Checklist',
                'items' => array_values(array_filter(array_map(static fn (array $i) => [
                    'text' => $str($i['name'] ?? '', 500),
                    'done' => ($i['state'] ?? '') === 'complete',
                ], $items), static fn ($i) => $i['text'] !== '')),
            ];
        }
        $comments = [];
        foreach (is_array($json['actions'] ?? null) ? $json['actions'] : [] as $a) {
            if (is_array($a) && ($a['type'] ?? '') === 'commentCard' && is_string($a['data']['card']['id'] ?? null) && is_string($a['data']['text'] ?? null)) {
                $comments[$a['data']['card']['id']][] = [
                    'author' => $str($a['memberCreator']['fullName'] ?? $a['memberCreator']['username'] ?? '', 100),
                    'date'   => substr($str($a['date'] ?? '', 30), 0, 10),
                    'text'   => $str($a['data']['text'], 4500),
                ];
            }
        }

        $cards = array_values(array_filter($json['cards'], static fn ($c) => is_array($c) && is_string($c['id'] ?? null)));
        usort($cards, $byPos);
        $out = [];
        $skippedCards = 0;
        foreach ($cards as $c) {
            if (!empty($c['closed']) || !isset($openLists[(string) ($c['idList'] ?? '')])) {
                $skippedCards++;
                continue;
            }
            $links = [];
            foreach (is_array($c['attachments'] ?? null) ? $c['attachments'] : [] as $att) {
                $url = is_array($att) ? $str($att['url'] ?? '', 2000) : '';
                if (preg_match('#^https?://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false) {
                    $links[] = ['url' => $url, 'name' => $str($att['name'] ?? '', 150), 'upload' => !empty($att['isUpload'])];
                }
            }
            $out[] = [
                'list'        => (string) $c['idList'],
                'title'       => $str($c['name'] ?? '', 200) ?: 'Card',
                'description' => $str($c['desc'] ?? '', 10000),
                'start'       => self::day($c['start'] ?? null),
                'due'         => self::day($c['due'] ?? null),
                'complete'    => !empty($c['dueComplete']),
                'labels'      => array_values(array_filter(array_map('strval', is_array($c['idLabels'] ?? null) ? $c['idLabels'] : []), static fn ($id) => isset($labels[$id]))),
                'members'     => array_values(array_filter(array_map(static fn ($id) => $people[(string) $id] ?? '', is_array($c['idMembers'] ?? null) ? $c['idMembers'] : []))),
                'checklists'  => $checklists[$c['id']] ?? [],
                'comments'    => array_reverse($comments[$c['id']] ?? []),
                'links'       => array_slice($links, 0, 20),
            ];
        }

        return [
            'sourceId'     => substr(hash('sha256', 'trello:' . (string) ($json['id'] ?? $json['name'])), 0, 64),
            'name'         => $str($json['name'], 100) ?: 'Trello board',
            'lists'        => array_values($openLists),
            'labels'       => $labels,
            'members'      => array_values(array_unique(array_filter($people))),
            'cards'        => $out,
            'skippedLists' => $skippedLists,
            'skippedCards' => $skippedCards,
        ];
    }

    /**
     * Counts and the people found, for the preview.
     *
     * @param array<string, mixed> $import
     * @return array{name: string, lists: int, cards: int, labels: int, checklists: int, items: int, comments: int, links: int, uploads: int, skippedLists: int, skippedCards: int, matched: list<string>, unmatched: list<string>, sample: list<array<string, mixed>>, importedAt: ?string}
     */
    public function preview(array $import): array
    {
        $data = $import['data'];
        $matched = $this->matchPeople($import['workspaceId'], $data['members']);
        $sum = static fn (callable $f): int => array_sum(array_map($f, $data['cards']));

        return [
            'name'         => $data['name'],
            'lists'        => count($data['lists']),
            'cards'        => count($data['cards']),
            'labels'       => count($data['labels']),
            'checklists'   => $sum(static fn ($c) => count($c['checklists'])),
            'items'        => $sum(static fn ($c) => array_sum(array_map(static fn ($l) => count($l['items']), $c['checklists']))),
            'comments'     => $sum(static fn ($c) => count($c['comments'])),
            'links'        => $sum(static fn ($c) => count($c['links'])),
            'uploads'      => $sum(static fn ($c) => count(array_filter($c['links'], static fn ($l) => $l['upload']))),
            'skippedLists' => (int) $data['skippedLists'],
            'skippedCards' => (int) $data['skippedCards'],
            'matched'      => array_keys($matched),
            'unmatched'    => array_values(array_diff($data['members'], array_keys($matched))),
            'sample'       => array_map(fn (array $list) => [
                'name'  => $list['name'],
                'cards' => array_slice(array_values(array_filter($data['cards'], static fn ($c) => $c['list'] === $list['id'])), 0, 5),
                'total' => count(array_filter($data['cards'], static fn ($c) => $c['list'] === $list['id'])),
            ], array_slice($data['lists'], 0, 12)),
            'importedAt'   => $this->store->alreadyImported($import['workspaceId'], 'trello', $import['sourceKey']),
        ];
    }

    /**
     * Builds the board in one transaction. Returns it.
     *
     * @param array<string, mixed> $import
     */
    public function run(int $actorId, array $import, string $name, BoardVisibility $visibility, bool $again): BoardSummary
    {
        $workspace = $this->workspaceOf($actorId, $import['workspaceId']) ?? $this->fail('import', 'You are no longer in this workspace.');
        $this->assertCanCreate($workspace);
        if (!$again && $this->store->alreadyImported($workspace->id, 'trello', $import['sourceKey']) !== null) {
            $this->fail('again', 'This Trello board was imported before. Tick the box to import it again as another board.');
        }
        $data = $import['data'];
        $name = trim($name) !== '' ? trim($name) : $data['name'];
        $people = $this->matchPeople($workspace->id, $data['members']);

        $board = $this->transactions->run(function () use ($actorId, $workspace, $data, $name, $visibility, $people): BoardSummary {
            $board = $this->catalog->createBoard($actorId, $workspace->id, $name, $visibility);
            // The Trello board's members who were found here become members of the new board.
            foreach (array_unique(array_values($people)) as $userId) {
                if ($userId !== $actorId && $this->boardMembers->role($board->id, $userId) === null) {
                    $this->boardMembers->add($board->id, $userId, \App\Modules\Access\Contract\BoardRole::Member);
                }
            }
            $now = $this->clock->now()->format('Y-m-d H:i:s');
            $lists = [];
            foreach ($data['lists'] as $list) {
                $lists[$list['id']] = $this->catalog->addList($actorId, $board->id, $list['name'])->id;
            }
            $labels = [];
            foreach ($data['labels'] as $id => $label) {
                $color = in_array($label['color'], Label::COLORS, true) ? $label['color'] : 'slate';
                $labels[$id] = $this->labels->ensureLabel($actorId, $board->id, $label['name'], $color)->id;
            }
            foreach ($data['cards'] as $c) {
                $start = $c['start'] !== null && $c['due'] !== null && $c['start'] > $c['due'] ? null : $c['start'];
                $card = $this->writer->createCard($actorId, $board->id, $lists[$c['list']], $c['title'], [
                    'description'  => $c['description'] !== '' ? $c['description'] : null,
                    'start_date'   => $start,
                    'due_date'     => $c['due'],
                    'completed_at' => $c['complete'] ? $now : null,
                ]);
                foreach (array_unique($c['labels']) as $labelId) {
                    $this->labels->attach($actorId, $card->id, $labels[$labelId]);
                }
                foreach ($c['members'] as $person) {
                    if (isset($people[$person])) {
                        $this->writer->assign($actorId, $card, $people[$person]);
                    }
                }
                foreach ($c['checklists'] as $list) {
                    $checklistId = $this->checklists->addChecklist($actorId, $card, $list['title']);
                    if ($list['items'] === []) {
                        continue;
                    }
                    foreach (array_chunk($list['items'], 200) as $chunk) {
                        $this->checklists->addItem($actorId, $checklistId, implode("\n", array_map(static fn ($i) => str_replace("\n", ' ', $i['text']), $chunk)));
                    }
                    $done = array_values(array_map(static fn ($i) => $i['done'], $list['items']));
                    foreach ($this->checklists->forCard($card->id) as $checklist) {
                        if ($checklist->id === $checklistId) {
                            foreach (array_values($checklist->items) as $n => $item) {
                                if ($done[$n] ?? false) {
                                    $this->checklists->toggleItem($actorId, $item->id);
                                }
                            }
                        }
                    }
                }
                foreach ($c['comments'] as $comment) {
                    // Who wrote it and when, from Trello; "@" is broken up so nobody here is notified.
                    $text = '**' . ($comment['author'] !== '' ? $comment['author'] : 'Trello') . '** · ' . $comment['date'] . " (Trello)\n\n" . str_replace('@', "@\u{200B}", $comment['text']);
                    $this->comments->post($actorId, $card, mb_substr($text, 0, 5000));
                }
                foreach ($c['links'] as $link) {
                    $this->files->addLink($actorId, $card, $link['url'], $link['name']);
                }
            }

            return $board;
        });

        $this->store->finish($import['id'], $import['publicId'], $board->id, ['cards' => count($data['cards']), 'lists' => count($data['lists'])]);

        return $board;
    }

    public function assertCanCreate(WorkspaceSummary $workspace): void
    {
        if (!$workspace->role->canCreateBoards()) {
            $this->fail('file', 'You can\'t create boards in this workspace.');
        }
    }

    /**
     * Trello names matched (exactly, ignoring case) to people of the workspace.
     *
     * @param list<string> $names
     * @return array<string, int> Trello name => user id
     */
    private function matchPeople(int $workspaceId, array $names): array
    {
        $byName = [];
        foreach ($this->users->findMany(array_map(static fn ($m) => $m['userId'], $this->workspaces->members($workspaceId))) as $user) {
            $byName[mb_strtolower($user->name)][] = $user->id;
        }
        $out = [];
        foreach ($names as $name) {
            $ids = $byName[mb_strtolower($name)] ?? [];
            if (count($ids) === 1) {
                $out[$name] = $ids[0];
            }
        }

        return $out;
    }

    private function workspaceOf(int $userId, int $workspaceId): ?WorkspaceSummary
    {
        foreach ($this->workspaces->workspacesOf($userId) as $workspace) {
            if ($workspace->id === $workspaceId) {
                return $workspace;
            }
        }

        return null;
    }

    private static function day(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m) === 1 ? $m[1] : null;
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
