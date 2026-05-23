<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->schemaIsUpToDate()) {
            return;
        }

        match (Schema::getConnection()->getDriverName()) {
            'pgsql' => $this->upgradePostgres(),
            default => $this->upgradeDefault(),
        };
    }

    /**
     * This migration only corrects production schema drift; reversing it could
     * remove columns the application depends on.
     */
    public function down(): void {}

    private function schemaIsUpToDate(): bool
    {
        return Schema::hasColumn('trades', 'counterparty')
            && $this->targetTeamIdIsNullable();
    }

    private function targetTeamIdIsNullable(): bool
    {
        $column = collect(Schema::getColumns('trades'))
            ->firstWhere('name', 'target_team_id');

        return $column !== null && ($column['nullable'] ?? false);
    }

    private function upgradePostgres(): void
    {
        if (! $this->targetTeamIdIsNullable()) {
            DB::statement('ALTER TABLE trades DROP CONSTRAINT IF EXISTS trades_target_team_id_foreign');
            DB::statement('ALTER TABLE trades ALTER COLUMN target_team_id DROP NOT NULL');
        }

        if (! Schema::hasColumn('trades', 'counterparty')) {
            DB::statement("ALTER TABLE trades ADD COLUMN counterparty VARCHAR(32) NOT NULL DEFAULT 'team'");
        }

        if (! $this->hasTargetTeamForeignKey()) {
            DB::statement('
                ALTER TABLE trades
                ADD CONSTRAINT trades_target_team_id_foreign
                FOREIGN KEY (target_team_id) REFERENCES teams(id) ON DELETE SET NULL
            ');
        }
    }

    private function hasTargetTeamForeignKey(): bool
    {
        $result = DB::selectOne("
            SELECT 1
            FROM information_schema.table_constraints
            WHERE table_schema = current_schema()
              AND table_name = 'trades'
              AND constraint_name = 'trades_target_team_id_foreign'
              AND constraint_type = 'FOREIGN KEY'
        ");

        return $result !== null;
    }

    private function upgradeDefault(): void
    {
        try {
            Schema::table('trades', function (Blueprint $table): void {
                $table->dropForeign(['target_team_id']);
            });
        } catch (\Throwable) {
            // Foreign key already dropped or never existed.
        }

        Schema::table('trades', function (Blueprint $table): void {
            if (! $this->targetTeamIdIsNullable()) {
                $table->foreignId('target_team_id')->nullable()->change();
            }

            if (! Schema::hasColumn('trades', 'counterparty')) {
                $table->string('counterparty', 32)->default('team');
            }
        });

        try {
            Schema::table('trades', function (Blueprint $table): void {
                $table->foreign('target_team_id')->references('id')->on('teams')->nullOnDelete();
            });
        } catch (\Throwable) {
            // Foreign key already exists.
        }
    }
};
