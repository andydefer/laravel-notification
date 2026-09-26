<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fcm_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Longueur limitée à 191 pour permettre l'index unique composite en utf8mb4
            $table->string('notifiable_type', 191);
            $table->string('notifiable_id', 191);

            $table->uuid('device_id');

            $table->string('token', 512);

            $table->string('platform')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id'], 'fcm_devices_notifiable_index');

            $table->unique(
                ['notifiable_type', 'notifiable_id', 'device_id'],
                'fcm_devices_owner_device_unique',
            );

            $table->unique('token', 'fcm_devices_token_unique');

            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fcm_devices');
    }
};
