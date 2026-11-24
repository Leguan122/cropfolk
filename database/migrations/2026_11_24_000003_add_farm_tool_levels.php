<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->unsignedTinyInteger('hoe_level')->default(1);
            $table->unsignedTinyInteger('can_level')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->dropColumn(['hoe_level', 'can_level']);
        });
    }
};
