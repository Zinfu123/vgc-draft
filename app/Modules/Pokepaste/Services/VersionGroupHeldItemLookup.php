<?php

namespace App\Modules\Pokepaste\Services;

use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Models\VersionGroupHeldItem;

class VersionGroupHeldItemLookup
{
    public function findIdByShowdownLabel(VersionGroup $versionGroup, string $label): ?int
    {
        $needle = mb_strtolower(trim($label));
        if ($needle === '') {
            return null;
        }

        $items = VersionGroupHeldItem::query()
            ->where('version_group_id', $versionGroup->id)
            ->get();

        foreach ($items as $item) {
            $candidates = array_filter([
                $item->display_name_en,
                str_replace('-', ' ', $item->name),
                $item->name,
            ], fn ($v) => $v !== null && $v !== '');

            foreach ($candidates as $candidate) {
                if (mb_strtolower(trim((string) $candidate)) === $needle) {
                    return (int) $item->id;
                }
            }
        }

        return null;
    }
}
