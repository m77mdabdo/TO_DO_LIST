<?php

declare(strict_types=1);

namespace App\Support;

use App\I18n\Translator;
use DateTimeImmutable;

final class TimeAgo
{
    public function __construct(private Clock $clock, private ?Translator $translator = null)
    {
    }

    public function format(?DateTimeImmutable $moment): string
    {
        if ($moment === null) {
            return '';
        }

        $seconds = $this->clock->now()->getTimestamp() - $moment->getTimestamp();

        [$text, $n] = match (true) {
            $seconds < 60     => ['just now', 0],
            $seconds < 3600   => [':nm ago', intdiv($seconds, 60)],
            $seconds < 86400  => [':nh ago', intdiv($seconds, 3600)],
            $seconds < 604800 => [':nd ago', intdiv($seconds, 86400)],
            default           => [$moment->format('Y-m-d'), 0],
        };

        return $this->translator?->t($text, ['n' => $n]) ?? str_replace(':n', (string) $n, $text);
    }
}
