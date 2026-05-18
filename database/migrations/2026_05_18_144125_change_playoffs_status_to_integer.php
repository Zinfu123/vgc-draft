<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE playoffs ALTER COLUMN status DROP DEFAULT');

        DB::statement("
            ALTER TABLE playoffs
            ALTER COLUMN status TYPE smallint
            USING CASE status
                WHEN 'draft' THEN 0
                WHEN 'active' THEN 1
                WHEN 'completed' THEN 2
                ELSE 0
            END
        ");

        DB::statement('ALTER TABLE playoffs ALTER COLUMN status SET DEFAULT 0');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE playoffs ALTER COLUMN status DROP DEFAULT');

        DB::statement("
            ALTER TABLE playoffs
            ALTER COLUMN status TYPE varchar(24)
            USING CASE status
                WHEN 0 THEN 'draft'
                WHEN 1 THEN 'active'
                WHEN 2 THEN 'completed'
                ELSE 'draft'
            END
        ");

        DB::statement("ALTER TABLE playoffs ALTER COLUMN status SET DEFAULT 'draft'");
    }
};
