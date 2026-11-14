<?php

declare(strict_types=1);

namespace App\Modules\Board\Domain;

/**
 * Ready-made starting points for a new board: lists and labels. Names are
 * in English and translated when the board is created, so an Arabic user
 * gets Arabic lists.
 */
final class BuiltInTemplates
{
    /** @var array<string, array{name: string, description: string, color: string, lists: list<string>, labels: list<array{0: string, 1: string}>}> */
    private const TEMPLATES = [
        'kanban' => [
            'name'        => 'Simple Kanban',
            'description' => 'To do, doing and done.',
            'color'       => 'violet',
            'lists'       => ['To do', 'Doing', 'Done'],
            'labels'      => [['Urgent', 'red'], ['Waiting', 'yellow']],
        ],
        'software' => [
            'name'        => 'Software sprint',
            'description' => 'Backlog to release, with bugs and features apart.',
            'color'       => 'sky',
            'lists'       => ['Backlog', 'Ready', 'In progress', 'Review', 'Done'],
            'labels'      => [['Bug', 'red'], ['Feature', 'blue'], ['Chore', 'slate'], ['Blocked', 'orange']],
        ],
        'marketing' => [
            'name'        => 'Marketing campaign',
            'description' => 'From ideas to published content.',
            'color'       => 'coral',
            'lists'       => ['Ideas', 'Planned', 'Writing', 'Design', 'Published'],
            'labels'      => [['Social', 'pink'], ['Email', 'blue'], ['Blog', 'green'], ['Ads', 'orange']],
        ],
        'hiring' => [
            'name'        => 'Hiring pipeline',
            'description' => 'Track candidates from first contact to offer.',
            'color'       => 'mint',
            'lists'       => ['Applied', 'Phone screen', 'Interview', 'Offer', 'Hired'],
            'labels'      => [['Senior', 'purple'], ['Junior', 'sky'], ['Referral', 'green']],
        ],
        'weekly' => [
            'name'        => 'Weekly planner',
            'description' => 'A list for each working day.',
            'color'       => 'amber',
            'lists'       => ['This week', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Done'],
            'labels'      => [['Personal', 'green'], ['Work', 'blue']],
        ],
    ];

    /** @return array<string, array{name: string, description: string, color: string, lists: list<string>, labels: list<array{0: string, 1: string}>}> */
    public static function all(): array
    {
        return self::TEMPLATES;
    }

    /** @return array{name: string, description: string, color: string, lists: list<string>, labels: list<array{0: string, 1: string}>}|null */
    public static function get(string $key): ?array
    {
        return self::TEMPLATES[$key] ?? null;
    }
}
