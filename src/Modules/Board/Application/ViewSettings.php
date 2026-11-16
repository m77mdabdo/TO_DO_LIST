<?php

declare(strict_types=1);

namespace App\Modules\Board\Application;

use App\Modules\Board\Contract\DashboardLayout;
use App\Modules\Board\Contract\TableLayout;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use PDO;

/**
 * Per-person settings of a board's table and dashboard. Saving changes only
 * the saver's own view; other members keep theirs. The caller checks that
 * the person may see the board.
 */
final class ViewSettings
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function table(int $userId, int $boardId): TableLayout
    {
        $data = $this->load($userId, $boardId, 'table');

        return $data === null ? new TableLayout() : TableLayout::fromArray($data);
    }

    /**
     * @param array<mixed> $input columns (list, in order), sort, dir (asc|desc), group
     */
    public function saveTable(int $userId, int $boardId, array $input): TableLayout
    {
        $columns = is_array($input['columns'] ?? null) ? $input['columns'] : [];
        $errors = [];
        foreach ($columns as $key) {
            if (!is_string($key) || !TableLayout::isColumn($key)) {
                $errors['columns'] = 'Choose columns of this table.';
            }
        }
        if (count($columns) > TableLayout::MAX_COLUMNS) {
            $errors['columns'] = 'Choose columns of this table.';
        }
        $sort = (string) ($input['sort'] ?? 'list');
        $group = (string) ($input['group'] ?? '');
        if (!isset(TableLayout::SORTS[$sort])) {
            $errors['sort'] = 'Choose how to sort the rows.';
        }
        if (!isset(TableLayout::GROUPS[$group])) {
            $errors['group'] = 'Choose how to group the rows.';
        }
        if ($errors !== []) {
            throw new ValidationException(new ValidationResult($errors));
        }

        $layout = new TableLayout(array_values(array_unique(array_map('strval', $columns))), $sort, ($input['dir'] ?? '') === 'desc', $group, true);
        $this->store($userId, $boardId, 'table', $layout->toArray());

        return $layout;
    }

    public function dashboard(int $userId, int $boardId): DashboardLayout
    {
        $data = $this->load($userId, $boardId, 'dashboard');

        return $data === null ? DashboardLayout::default() : DashboardLayout::fromArray($data);
    }

    /** Applies one change: add, remove, up or down a widget, or reset to every widget. */
    public function changeDashboard(int $userId, int $boardId, string $action, string $widget): DashboardLayout
    {
        if ($action === 'reset') {
            $this->forget($userId, $boardId, 'dashboard');

            return DashboardLayout::default();
        }
        if (!isset(DashboardLayout::WIDGETS[$widget]) || !in_array($action, ['add', 'remove', 'up', 'down'], true)) {
            throw new ValidationException(new ValidationResult(['widget' => 'Choose a widget.']));
        }
        $widgets = $this->dashboard($userId, $boardId)->widgets;
        $i = array_search($widget, $widgets, true);
        if ($action === 'add' && $i === false) {
            $widgets[] = $widget;
        } elseif ($action === 'remove' && $i !== false) {
            array_splice($widgets, $i, 1);
        } elseif ($i !== false && ($action === 'up' || $action === 'down')) {
            $j = $action === 'up' ? $i - 1 : $i + 1;
            if (isset($widgets[$j])) {
                [$widgets[$i], $widgets[$j]] = [$widgets[$j], $widgets[$i]];
            }
        }
        $this->store($userId, $boardId, 'dashboard', ['widgets' => array_values($widgets)]);

        return new DashboardLayout(array_values($widgets), true);
    }

    public function forget(int $userId, int $boardId, string $view): void
    {
        $this->db->prepare('DELETE FROM view_settings WHERE user_id = ? AND board_id = ? AND view = ?')->execute([$userId, $boardId, $view]);
    }

    /** @return array<mixed>|null */
    private function load(int $userId, int $boardId, string $view): ?array
    {
        $stmt = $this->db->prepare('SELECT settings FROM view_settings WHERE user_id = ? AND board_id = ? AND view = ?');
        $stmt->execute([$userId, $boardId, $view]);
        $json = $stmt->fetchColumn();
        $data = $json === false ? null : json_decode((string) $json, true);

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $settings */
    private function store(int $userId, int $boardId, string $view, array $settings): void
    {
        $this->forget($userId, $boardId, $view);
        $this->db->prepare('INSERT INTO view_settings (user_id, board_id, view, settings, updated_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$userId, $boardId, $view, json_encode($settings, JSON_UNESCAPED_UNICODE) ?: '{}', $this->clock->now()->format('Y-m-d H:i:s')]);
    }
}
