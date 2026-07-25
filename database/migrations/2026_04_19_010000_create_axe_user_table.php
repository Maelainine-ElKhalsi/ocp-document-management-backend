<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('axe_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('axe_id')->constrained('axes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['axe_id', 'user_id']);
            $table->index(['user_id', 'axe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('axe_user');
    }
};
