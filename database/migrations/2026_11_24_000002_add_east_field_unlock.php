<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->boolean('east_open')->default(false);
        });
        DB::table('farms')->update(['east_open' => true]);
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->dropColumn('east_open');
        });
    }
};
