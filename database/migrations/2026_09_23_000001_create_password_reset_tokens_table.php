<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use VtPhp\Database\Migrations\Migration;
use VtPhp\Database\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
