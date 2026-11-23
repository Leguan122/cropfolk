<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_inventories', function (Blueprint $table): void {
            $table->string('farm', 12);
            $table->string('owner', 32);
            $table->json('contents');
            $table->primary(['farm', 'owner']);
            $table->foreign('farm')->references('code')->on('farms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_inventories');
    }
};
