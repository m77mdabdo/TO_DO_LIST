<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract;

/**
 * What one person sees on the card tiles of one board. Each part can be
 * hidden; the card title always shows. Saved per person and board, so it
 * never changes the board for anyone else.
 */
final class CardDisplay
{
    /** part => label shown in the settings form */
    public const PARTS = [
        'labels'      => 'Labels',
        'dates'       => 'Dates',
        'members'     => 'Members',
        'description' => 'Description icon',
        'badges'      => 'Checklist, comment and attachment counts',
    ];

    /** @param array<string, bool> $shown */
    private function __construct(private array $shown)
    {
    }

    public static function all(): self
    {
        return new self(array_fill_keys(array_keys(self::PARTS), true));
    }

    /** From stored JSON; unknown keys are dropped, missing ones are shown. */
    public static function fromJson(?string $json): self
    {
        $data = json_decode((string) $json, true);
        $shown = array_fill_keys(array_keys(self::PARTS), true);
        foreach (is_array($data) ? $data : [] as $part => $on) {
            if (isset($shown[$part])) {
                $shown[$part] = (bool) $on;
            }
        }

        return new self($shown);
    }

    /** @param list<string> $parts the parts to show */
    public static function showing(array $parts): self
    {
        $shown = array_fill_keys(array_keys(self::PARTS), false);
        foreach ($parts as $part) {
            if (isset($shown[$part])) {
                $shown[$part] = true;
            }
        }

        return new self($shown);
    }

    public function shows(string $part): bool
    {
        return $this->shown[$part] ?? true;
    }

    public function isDefault(): bool
    {
        return !in_array(false, $this->shown, true);
    }

    public function toJson(): string
    {
        return (string) json_encode($this->shown);
    }
}
