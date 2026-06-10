<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_prep_notes', function (Blueprint $table) {
            $table->json('bring_six_slots')->after('set_id');
        });

        $emptySix = json_encode([null, null, null, null, null, null]);

        DB::table('match_prep_notes')->orderBy('id')->chunk(100, function ($rows) use ($emptySix): void {
            foreach ($rows as $row) {
                $plan1 = self::truncatePlanSlots($row->plan_1_slots);
                $plan2 = self::truncatePlanSlots($row->plan_2_slots);
                $plan3 = self::truncatePlanSlots($row->plan_3_slots);

                DB::table('match_prep_notes')->where('id', $row->id)->update([
                    'bring_six_slots' => $emptySix,
                    'plan_1_slots' => json_encode($plan1),
                    'plan_2_slots' => json_encode($plan2),
                    'plan_3_slots' => json_encode($plan3),
                ]);
            }
        });
    }

    /**
     * @return list<int|null>
     */
    private static function truncatePlanSlots(?string $json): array
    {
        $arr = $json !== null ? json_decode($json, true) : null;
        if (! is_array($arr)) {
            return [null, null, null, null];
        }
        $out = [];
        for ($i = 0; $i < 4; $i++) {
            $v = $arr[$i] ?? null;
            $out[] = is_numeric($v) ? (int) $v : null;
        }

        return $out;
    }

    public function down(): void
    {
        Schema::table('match_prep_notes', function (Blueprint $table) {
            $table->dropColumn('bring_six_slots');
        });
    }
};
