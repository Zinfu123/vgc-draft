<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface StatRow {
    pokedex_id: number;
    name: string | null;
    nationaldex_id: number | null;
    draft_pick_count: number;
    draft_ban_count: number;
    match_bring_count: number;
    game_wins: number;
    game_losses: number;
    pick_rate: number;
    ban_rate: number;
    bring_rate: number;
    game_win_rate: number | null;
}

interface Charts {
    top_pick_labels: string[];
    top_pick_values: number[];
    top_ban_labels: string[];
    top_ban_values: number[];
    top_bring_labels: string[];
    top_bring_values: number[];
    top_win_labels: string[];
    top_win_values: number[];
}

const props = defineProps<{
    meta: {
        total_picks: number;
        total_bans: number;
        total_bring_units: number;
        rebuilt_at: string | null;
    };
    rows: StatRow[];
    charts: Charts;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Usage stats', href: '/usage-stats' },
];

function barWidth(values: number[], i: number): string {
    const v = values[i] ?? 0;
    const max = Math.max(...values, 0.0001);
    return `${Math.min(100, (v / max) * 100)}%`;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pokémon usage stats" />
        <div class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <h1 class="mb-2 text-2xl font-bold">Global Pokémon usage</h1>
            <p class="text-muted-foreground mb-6 text-sm">
                Pick rate, ban rate, how often species were brought to completed matches (pool + playoffs), and game win rate from those matches.
                <span v-if="meta.rebuilt_at" class="block mt-1">Last rebuilt: {{ meta.rebuilt_at }}</span>
            </p>

            <div class="mb-10 grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-border bg-card p-4">
                    <p class="text-muted-foreground text-xs font-medium uppercase">Total draft picks</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ meta.total_picks }}</p>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <p class="text-muted-foreground text-xs font-medium uppercase">Total bans</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ meta.total_bans }}</p>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <p class="text-muted-foreground text-xs font-medium uppercase">Bring appearances</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ meta.total_bring_units }}</p>
                </div>
            </div>

            <div class="mb-10 grid gap-8 lg:grid-cols-2">
                <div>
                    <h2 class="mb-3 text-base font-semibold">Top pick rate (% of all picks)</h2>
                    <div class="flex flex-col gap-2">
                        <div v-for="(label, i) in charts.top_pick_labels" :key="'p' + i" class="flex items-center gap-2 text-sm">
                            <span class="w-32 shrink-0 truncate" :title="label">{{ label }}</span>
                            <div class="h-2 flex-1 rounded-full bg-muted">
                                <div class="bg-primary h-2 rounded-full" :style="{ width: barWidth(charts.top_pick_values, i) }" />
                            </div>
                            <span class="text-muted-foreground w-14 shrink-0 text-right tabular-nums">{{ charts.top_pick_values[i] }}%</span>
                        </div>
                    </div>
                </div>
                <div>
                    <h2 class="mb-3 text-base font-semibold">Top ban rate (% of all bans)</h2>
                    <div class="flex flex-col gap-2">
                        <div v-for="(label, i) in charts.top_ban_labels" :key="'b' + i" class="flex items-center gap-2 text-sm">
                            <span class="w-32 shrink-0 truncate" :title="label">{{ label }}</span>
                            <div class="h-2 flex-1 rounded-full bg-muted">
                                <div class="bg-amber-500 h-2 rounded-full dark:bg-amber-400" :style="{ width: barWidth(charts.top_ban_values, i) }" />
                            </div>
                            <span class="text-muted-foreground w-14 shrink-0 text-right tabular-nums">{{ charts.top_ban_values[i] }}%</span>
                        </div>
                    </div>
                </div>
                <div>
                    <h2 class="mb-3 text-base font-semibold">Top bring rate (% of bring slots)</h2>
                    <div class="flex flex-col gap-2">
                        <div v-for="(label, i) in charts.top_bring_labels" :key="'br' + i" class="flex items-center gap-2 text-sm">
                            <span class="w-32 shrink-0 truncate" :title="label">{{ label }}</span>
                            <div class="h-2 flex-1 rounded-full bg-muted">
                                <div class="bg-emerald-600 h-2 rounded-full dark:bg-emerald-500" :style="{ width: barWidth(charts.top_bring_values, i) }" />
                            </div>
                            <span class="text-muted-foreground w-14 shrink-0 text-right tabular-nums">{{ charts.top_bring_values[i] }}%</span>
                        </div>
                    </div>
                </div>
                <div>
                    <h2 class="mb-3 text-base font-semibold">Game win % (min 5 games)</h2>
                    <div class="flex flex-col gap-2">
                        <div v-for="(label, i) in charts.top_win_labels" :key="'w' + i" class="flex items-center gap-2 text-sm">
                            <span class="w-32 shrink-0 truncate" :title="label">{{ label }}</span>
                            <div class="h-2 flex-1 rounded-full bg-muted">
                                <div class="bg-violet-600 h-2 rounded-full dark:bg-violet-500" :style="{ width: barWidth(charts.top_win_values, i) }" />
                            </div>
                            <span class="text-muted-foreground w-14 shrink-0 text-right tabular-nums">{{ charts.top_win_values[i] }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <h2 class="mb-3 text-base font-semibold">Table (top 500 by picks)</h2>
            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-muted/50">
                        <tr>
                            <th class="px-3 py-2 font-medium">Pokémon</th>
                            <th class="px-3 py-2 font-medium">Dex</th>
                            <th class="px-3 py-2 font-medium">Picks</th>
                            <th class="px-3 py-2 font-medium">Pick %</th>
                            <th class="px-3 py-2 font-medium">Bans</th>
                            <th class="px-3 py-2 font-medium">Ban %</th>
                            <th class="px-3 py-2 font-medium">Bring</th>
                            <th class="px-3 py-2 font-medium">Bring %</th>
                            <th class="px-3 py-2 font-medium">G W</th>
                            <th class="px-3 py-2 font-medium">G L</th>
                            <th class="px-3 py-2 font-medium">Win %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.pokedex_id" class="border-b border-border/80">
                            <td class="px-3 py-2">{{ r.name ?? '—' }}</td>
                            <td class="text-muted-foreground px-3 py-2 tabular-nums">{{ r.nationaldex_id ?? '—' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ r.draft_pick_count }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ (r.pick_rate * 100).toFixed(2) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ r.draft_ban_count }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ (r.ban_rate * 100).toFixed(2) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ r.match_bring_count }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ (r.bring_rate * 100).toFixed(2) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ r.game_wins }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ r.game_losses }}</td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ r.game_win_rate !== null ? (r.game_win_rate * 100).toFixed(1) : '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
