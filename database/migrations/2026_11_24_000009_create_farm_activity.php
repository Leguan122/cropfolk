<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_activity', function (Blueprint $table): void {
            $table->id();
            $table->string('farm', 12);
            $table->string('player', 24)->nullable();
            $table->string('action', 24);
            $table->unsignedBigInteger('occurred_at');
            $table->unsignedBigInteger('coins_after');
            $table->json('details');
            $table->foreign('farm')->references('code')->on('farms')->cascadeOnDelete();
            $table->index(['farm', 'occurred_at']);
            $table->index(['player', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_activity');
    }
};
