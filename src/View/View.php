<?php

declare(strict_types=1);

namespace App\View;

use RuntimeException;
use Throwable;

/**
 * Renders plain PHP templates from views/.
 *
 * Inside a template every key of $data is a variable, $view is this
 * renderer (for partials) and anything shared with share() is there too.
 */
final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private string $templateDir)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * A page: the template, wrapped in a layout.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], string $layout = 'layout'): string
    {
        $content = $this->partial($template, $data);

        return $this->partial($layout, ['content' => $content] + $data);
    }

    /**
     * A template on its own, without the layout.
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        $file = $this->templateDir . '/' . $template . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("View [$template] not found.");
        }

        $level = ob_get_level();
        ob_start();

        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data + $this->shared + ['view' => $this]);
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
