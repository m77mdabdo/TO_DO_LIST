<?php

declare(strict_types=1);

namespace App\Shared\Automation;

use InvalidArgumentException;

/**
 * The actions automations can run. Modules add theirs when they register
 * (see Container::extend in each module), so the Automation module never
 * needs to know how a card is moved or a label attached.
 */
final class AutomationActions
{
    /** @var array<string, ActionType> */
    private array $types = [];

    public function add(ActionType $type): void
    {
        if (!in_array($type->param, ActionType::PARAMS, true) || !in_array($type->target, ['card', 'list'], true)) {
            throw new InvalidArgumentException("Unknown parameter or target for action {$type->key}.");
        }

        $this->types[$type->key] = $type;
    }

    public function get(string $key): ?ActionType
    {
        return $this->types[$key] ?? null;
    }

    /** @return array<string, ActionType> in the order modules added them */
    public function all(): array
    {
        return $this->types;
    }
}
