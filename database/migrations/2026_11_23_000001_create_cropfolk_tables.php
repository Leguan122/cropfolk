<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table): void {
            $table->string('code', 12)->primary();
            $table->unsignedBigInteger('coins');
            $table->json('plots');
        });

        Schema::create('players', function (Blueprint $table): void {
            $table->string('farm', 12);
            $table->string('id', 32);
            $table->double('x');
            $table->double('y');
            $table->unsignedBigInteger('seen');
            $table->primary(['farm', 'id']);
            $table->index(['farm', 'seen']);
            $table->foreign('farm')->references('code')->on('farms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
        Schema::dropIfExists('farms');
    }
};
