<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id');
            $table->string('channel');
            $table->string('destination');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->json('message');
            $table->json('metadata')->nullable(); // ✅ NOUVEAU
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('session_id');
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index('channel');
            $table->index('destination');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
