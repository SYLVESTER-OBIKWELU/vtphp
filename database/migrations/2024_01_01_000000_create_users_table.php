<?php

use Core\Migration;
use Core\Schema;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('users');
    }
}
