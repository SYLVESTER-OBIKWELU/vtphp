<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use VtPhp\Database\Schema;
use VtPhp\Tests\Support\FeatureTestCase;

final class SchemaTest extends FeatureTestCase
{
    public function test_the_shipped_migrations_create_laravel_shaped_tables(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['id', 'name', 'email', 'email_verified_at', 'password', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('password_reset_tokens', ['email', 'token', 'created_at']));

        Capsule::table('users')->insert(['name' => 'Ada', 'email' => 'ada@example.com']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        Capsule::table('users')->insert(['name' => 'Ada 2', 'email' => 'ada@example.com']); // email is unique
    }

    public function test_create_alter_rename_and_drop(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
        $this->assertTrue(Schema::hasTable('posts'));

        Schema::table('posts', function (Blueprint $table): void {
            $table->text('body')->nullable();
        });
        $this->assertTrue(Schema::hasColumn('posts', 'body'));

        Schema::rename('posts', 'articles');
        $this->assertFalse(Schema::hasTable('posts'));
        $this->assertTrue(Schema::connection()->hasTable('articles'));

        Schema::dropIfExists('articles');
        Schema::dropIfExists('articles'); // Idempotent.
        $this->assertFalse(Schema::hasTable('articles'));
    }

    public function test_rolling_back_the_shipped_migrations_drops_their_tables(): void
    {
        $this->forge(['command' => 'migrate:rollback']);

        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
    }
}
