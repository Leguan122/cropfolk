<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_inventories', function (Blueprint $table): void {
            $table->unsignedTinyInteger('water')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('farm_inventories', function (Blueprint $table): void {
            $table->dropColumn('water');
        });
    }
};
