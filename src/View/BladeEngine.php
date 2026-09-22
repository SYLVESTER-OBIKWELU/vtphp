<?php

declare(strict_types=1);

namespace VtPhp\View;

use eftec\bladeone\BladeOne;
use VtPhp\Config\Repository;

/**
 * Thin wrapper around the standalone eftec/bladeone compiler so Blade
 * templates can be rendered without pulling in the full Illuminate view stack.
 */
final class BladeEngine
{
    private BladeOne $blade;

    public function __construct(Repository $config)
    {
        /** @var array<int, string> $paths */
        $paths = (array) $config->get('view.paths', []);
        $compiled = (string) $config->get('view.compiled');

        @mkdir($compiled, 0777, true);

        // BladeOne's docblock claims string|null, but its implementation accepts
        // an array of template paths (see BladeOne::__construct()).
        // @phpstan-ignore argument.type
        $this->blade = new BladeOne($paths, $compiled, BladeOne::MODE_AUTO);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        return $this->blade->run($view, $data);
    }
}
