<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Longueur limitée à 191 pour permettre l'index unique composite en utf8mb4
            $table->string('notifiable_type', 191);
            $table->string('notifiable_id', 191);

            $table->string('endpoint', 500);

            $table->string('p256dh');
            $table->string('auth');

            $table->string('browser')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id'], 'web_push_subscriptions_notifiable_index');

            $table->unique('endpoint', 'web_push_subscriptions_endpoint_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_push_subscriptions');
    }
};
