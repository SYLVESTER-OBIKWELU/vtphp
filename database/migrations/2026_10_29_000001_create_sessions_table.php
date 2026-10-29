<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use VtPhp\Database\Migrations\Migration;
use VtPhp\Database\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create((string) config('session.table', 'sessions'), function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('session.table', 'sessions'));
    }
};
