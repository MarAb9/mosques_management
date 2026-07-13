<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Plain-PHP view renderer.
 *
 * Views live in resources/views and are addressed with dot notation:
 * View::make('mosques.index', [...]). A view may be wrapped in a layout;
 * the layout receives the rendered view as $content plus the same data.
 */
final class View
{
    public function __construct(private readonly string $viewsDir)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderFile($this->path($view), $data);

        if ($layout !== null) {
            $data['content'] = $content;
            $content = $this->renderFile($this->path($layout), $data);
        }

        return $content;
    }

    /**
     * Render a partial (component) from within another view.
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $view, array $data = []): string
    {
        return $this->renderFile($this->path($view), $data);
    }

    /** Escape a value for safe HTML output. */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    private function path(string $view): string
    {
        $relative = str_replace('.', '/', $view) . '.php';
        $file = $this->viewsDir . '/' . $relative;

        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$view} ({$file})");
        }

        return $file;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $file, array $data): string
    {
        $view = $this; // available inside templates for partials
        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
