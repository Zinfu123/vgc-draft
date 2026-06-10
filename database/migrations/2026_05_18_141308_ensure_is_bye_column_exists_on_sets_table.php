<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sets', 'is_bye')) {
            return;
        }

        Schema::table('sets', function (Blueprint $table): void {
            $table->boolean('is_bye')->default(false);
        });
    }

    /**
     * This migration only corrects production schema drift; reversing it could
     * remove a column the application depends on.
     */
    public function down(): void {}
};
