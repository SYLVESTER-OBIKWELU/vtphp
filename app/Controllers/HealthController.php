<?php

declare(strict_types=1);

namespace App\Controllers;

final class HealthController
{
    /**
     * @return array{status: string, version: string}
     */
    public function index(): array
    {
        return ['status' => 'ok', 'version' => '0.1.0'];
    }
}
