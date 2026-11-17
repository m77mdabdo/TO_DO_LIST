<?php

declare(strict_types=1);

namespace App\Modules\Integration\Application;

use App\Modules\Integration\Contract\IntegrationType;

/** The kinds of ready-made integration on offer. Add one with $container->extend(IntegrationTypes::class, ...). */
final class IntegrationTypes
{
    /** @var array<string, IntegrationType> */
    private array $types = [];

    public function add(IntegrationType $type): void
    {
        $this->types[$type->key()] = $type;
    }

    public function find(string $key): ?IntegrationType
    {
        return $this->types[$key] ?? null;
    }

    /** @return list<IntegrationType> */
    public function all(): array
    {
        return array_values($this->types);
    }
}
