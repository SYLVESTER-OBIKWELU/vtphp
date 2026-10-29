<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use VtPhp\Database\Migrations\Migration;
use VtPhp\Database\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('name');
            $table->string('token', 64)->unique(); // SHA-256 of the secret
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
