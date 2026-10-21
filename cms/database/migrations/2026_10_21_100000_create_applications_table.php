<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();
            $table->string('request_key', 64)->unique();
            $table->string('name', 80);
            $table->string('phone', 25);
            $table->string('language', 60);
            $table->text('comment')->nullable();
            $table->string('preferences', 250)->nullable();
            $table->string('page', 100);
            $table->string('status', 20)->default('new')->index();
            $table->timestamp('consented_at');
            $table->timestamp('notified_at')->nullable();
            $table->unsignedTinyInteger('notification_attempts')->default(0);
            $table->timestamp('notification_attempted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
