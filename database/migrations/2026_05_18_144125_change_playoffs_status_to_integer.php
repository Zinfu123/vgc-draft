<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! $this->statusIsString()) {
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

    /**
     * Reversing the legacy string conversion is not supported once applied.
     */
    public function down(): void {}

    private function statusIsString(): bool
    {
        $column = collect(Schema::getColumns('playoffs'))
            ->firstWhere('name', 'status');

        if ($column === null) {
            return false;
        }

        $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));

        return in_array($type, ['varchar', 'character varying', 'text', 'string', 'bpchar'], true);
    }
};
