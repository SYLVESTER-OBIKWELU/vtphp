<?php

declare(strict_types=1);

namespace App\Http\Controllers;

final class HealthController
{
    /**
     * Check that the API is running.
     *
     * @return array{status: string, version: string}
     */
    public function index(): array
    {
        return ['status' => 'ok', 'version' => '0.1.0'];
    }
}
