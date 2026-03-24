<?php

declare(strict_types=1);

namespace App\View;

/** Inline SVG icons, so the app needs no icon font or extra requests. */
final class Icons
{
    private const PATHS = [
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>',
        'edit'     => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>',
        'right'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'left'     => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'check'    => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'moon'     => '<path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5Z"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'logout'   => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/>',
        'flag'     => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        'tag'      => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9-9-9Z"/><circle cx="8" cy="8" r="1.5"/>',
        'sparkle'  => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'fire'     => '<path d="M12 21a7 7 0 0 0 7-7c0-4-3-6-4-9-1 2-2 3-3 3 0-2-1-4-3-5 0 4-4 6-4 11a7 7 0 0 0 7 7Z"/>',
        'broom'    => '<path d="M14 4 9 13M6 14h7l1 7H5l1-7ZM8 17v4M11 17v4"/>',
        'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
        'keyboard' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10"/>',
    ];

    public static function svg(string $name, string $class = ''): string
    {
        return '<svg class="icon ' . htmlspecialchars($class, ENT_QUOTES) . '" viewBox="0 0 24 24" aria-hidden="true" '
            . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . (self::PATHS[$name] ?? '') . '</svg>';
    }
}
