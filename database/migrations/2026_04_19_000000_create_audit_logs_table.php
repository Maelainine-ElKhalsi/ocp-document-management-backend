<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->string('action');

            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');

            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('axe_id')->nullable()->constrained('axes')->nullOnDelete();
            $table->foreignId('dossier_id')->nullable()->constrained('dossiers')->nullOnDelete();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
