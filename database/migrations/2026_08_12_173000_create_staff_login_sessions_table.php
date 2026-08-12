<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_login_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('logged_in_at');
            $table->timestamp('logged_out_at')->nullable();
            $table->string('method', 20);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 191)->nullable();
            $table->string('session_key', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'logged_out_at']);
            $table->index('logged_in_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_login_sessions');
    }
};
