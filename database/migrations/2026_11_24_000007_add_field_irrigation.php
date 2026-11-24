<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->unsignedTinyInteger('irrigation_mask')->default(0);
            $table->unsignedTinyInteger('irrigation_water')->default(0);
            $table->unsignedBigInteger('irrigation_updated_at')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->dropColumn(['irrigation_mask', 'irrigation_water', 'irrigation_updated_at']);
        });
    }
};
