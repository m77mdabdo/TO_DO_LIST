<?php

declare(strict_types=1);

namespace App\Task;

enum Status: string
{
    case Todo  = 'todo';
    case Doing = 'doing';
    case Done  = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo  => 'To do',
            self::Doing => 'Doing',
            self::Done  => 'Done',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Todo  => 'Everything waiting for you',
            self::Doing => 'What you are on right now',
            self::Done  => 'Nice work, keep it going',
        };
    }

    public function previous(): ?self
    {
        return match ($this) {
            self::Todo  => null,
            self::Doing => self::Todo,
            self::Done  => self::Doing,
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Todo  => self::Doing,
            self::Doing => self::Done,
            self::Done  => null,
        };
    }

    /** Label on the button that moves a card into this status. */
    public function actionLabel(): string
    {
        return match ($this) {
            self::Todo  => 'Back',
            self::Doing => 'Start',
            self::Done  => 'Done',
        };
    }
}
