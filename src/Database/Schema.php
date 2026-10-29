<?php

declare(strict_types=1);

namespace VtPhp\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Builder;

/**
 * Laravel-style schema builder for migrations, backed by the booted
 * Eloquent connection:
 *
 *     Schema::create('posts', function (Blueprint $table): void {
 *         $table->id();
 *         $table->string('title');
 *         $table->timestamps();
 *     });
 *
 * @method static void create(string $table, \Closure $callback)
 * @method static void table(string $table, \Closure $callback)
 * @method static void drop(string $table)
 * @method static void dropIfExists(string $table)
 * @method static void rename(string $from, string $to)
 * @method static bool hasTable(string $table)
 * @method static bool hasColumn(string $table, string $column)
 * @method static bool hasColumns(string $table, array<int, string> $columns)
 */
final class Schema
{
    public static function connection(?string $name = null): Builder
    {
        return Capsule::schema($name);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return Capsule::schema()->{$method}(...$arguments);
    }
}
