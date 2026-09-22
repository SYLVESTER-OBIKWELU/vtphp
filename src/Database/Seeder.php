<?php

declare(strict_types=1);

namespace VtPhp\Database;

abstract class Seeder
{
    abstract public function run(): void;

    /**
     * @param class-string<Seeder> $seeder
     */
    protected function call(string $seeder): void
    {
        (new $seeder())->run();
    }
}
