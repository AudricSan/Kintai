<?php

declare(strict_types=1);

namespace kintai\UI;

use RuntimeException;

final class ViewRenderer
{
    private string $basePath;

    /** @var array<string, mixed> Shared data available in all views */
    private array $shared = [];

    /** @var array<string, string> Layout overrides (e.g. 'layout.app' → 'layout.mobile') */
    private array $layoutOverrides = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Remplace un layout par un autre (ex : mobile détecte mobile → layout.app → layout.mobile).
     */
    public function setLayoutOverride(string $from, string $to): void
    {
        $this->layoutOverrides[$from] = $to;
    }

    /**
     * Render a view template and return the HTML string.
     *
     * @param string $view Dot-notation view name (e.g. "auth.login" -> auth/login.php)
     * @param array<string, mixed> $data Variables available in the view
     * @param string|null $layout Layout file (e.g. "layout.app" -> layout/app.php). Null for no layout.
     */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderPartial($view, $data);

        if ($layout !== null) {
            $effectiveLayout = $this->layoutOverrides[$layout] ?? $layout;
            $content = $this->renderPartial($effectiveLayout, array_merge($data, ['content' => $content]));
        }

        return $content;
    }

    /**
     * Render a single template file without layout.
     */
    public function renderPartial(string $view, array $data = []): string
    {
        $file = $this->resolvePath($view);

        if (!file_exists($file)) {
            throw new RuntimeException("View [{$view}] not found at [{$file}].");
        }

        $allData = array_merge($this->shared, $data);

        ob_start();
        (static function (string $_file, array $_data) {
            extract($_data, EXTR_SKIP);
            include $_file;
        })($file, $allData);

        return ob_get_clean();
    }

    private function resolvePath(string $view): string
    {
        $relative = str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        return $this->basePath . DIRECTORY_SEPARATOR . $relative;
    }
}
