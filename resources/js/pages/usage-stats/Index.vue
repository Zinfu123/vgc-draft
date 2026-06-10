<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, ChevronsUpDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface StatRow {
    pokedex_id: number;
    name: string | null;
    nationaldex_id: number | null;
    sprite_url: string | null;
    draft_pick_count: number;
    draft_ban_count: number;
    match_bring_count: number;
    game_wins: number;
    game_losses: number;
    ko_count: number;
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
    top_ko_labels: string[];
    top_ko_values: number[];
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

// — Sorting —
type SortKey = keyof StatRow;
const sortKey = ref<SortKey>('draft_pick_count');
const sortDir = ref<'asc' | 'desc'>('desc');

function setSort(key: SortKey): void {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc';
    } else {
        sortKey.value = key;
        sortDir.value = 'desc';
    }
}

const sortedRows = computed(() => {
    return [...props.rows].sort((a, b) => {
        const av = a[sortKey.value] ?? -Infinity;
        const bv = b[sortKey.value] ?? -Infinity;
        if (av === bv) return 0;
        const cmp = av < bv ? -1 : 1;
        return sortDir.value === 'asc' ? cmp : -cmp;
    });
});

// — Charts —
function barWidth(values: number[], i: number): string {
    const v = values[i] ?? 0;
    const max = Math.max(...values, 0.0001);
    return `${Math.min(100, (v / max) * 100)}%`;
}

const charts = computed(() => [
    { title: 'Top pick rate', key: 'pick', labels: props.charts.top_pick_labels, values: props.charts.top_pick_values, unit: '%', color: 'bg-primary' },
    { title: 'Top ban rate', key: 'ban', labels: props.charts.top_ban_labels, values: props.charts.top_ban_values, unit: '%', color: 'bg-amber-500 dark:bg-amber-400' },
    { title: 'Top bring rate', key: 'bring', labels: props.charts.top_bring_labels, values: props.charts.top_bring_values, unit: '%', color: 'bg-emerald-600 dark:bg-emerald-500' },
    { title: 'Game win % (min 5 games)', key: 'win', labels: props.charts.top_win_labels, values: props.charts.top_win_values, unit: '%', color: 'bg-violet-600 dark:bg-violet-500' },
    { title: 'Most knockouts', key: 'ko', labels: props.charts.top_ko_labels, values: props.charts.top_ko_values, unit: '', color: 'bg-rose-500 dark:bg-rose-400' },
]);

// — Table columns —
const columns: { label: string; key: SortKey; format?: (r: StatRow) => string }[] = [
    { label: 'Pokémon', key: 'name' },
    { label: 'Picks', key: 'draft_pick_count' },
    { label: 'Pick %', key: 'pick_rate', format: (r) => (r.pick_rate * 100).toFixed(2) },
    { label: 'Bans', key: 'draft_ban_count' },
    { label: 'Ban %', key: 'ban_rate', format: (r) => (r.ban_rate * 100).toFixed(2) },
    { label: 'Bring', key: 'match_bring_count' },
    { label: 'Bring %', key: 'bring_rate', format: (r) => (r.bring_rate * 100).toFixed(2) },
    { label: 'W', key: 'game_wins' },
    { label: 'L', key: 'game_losses' },
    { label: 'Win %', key: 'game_win_rate', format: (r) => r.game_win_rate !== null ? (r.game_win_rate * 100).toFixed(1) : '—' },
    { label: 'KOs', key: 'ko_count' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Usage stats" />

        <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

            <!-- Header -->
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">Pokémon usage stats</h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Pick, ban, bring, and game performance across all league matches.
                        <span v-if="meta.rebuilt_at" class="block">Last rebuilt: {{ new Date(meta.rebuilt_at).toLocaleString() }}</span>
                    </p>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="mb-10 grid gap-4 sm:grid-cols-3">
                <div class="bg-card border-border rounded-xl border p-5">
                    <p class="text-muted-foreground text-xs font-medium uppercase tracking-wider">Total draft picks</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums">{{ meta.total_picks.toLocaleString() }}</p>
                </div>
                <div class="bg-card border-border rounded-xl border p-5">
                    <p class="text-muted-foreground text-xs font-medium uppercase tracking-wider">Total bans</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums">{{ meta.total_bans.toLocaleString() }}</p>
                </div>
                <div class="bg-card border-border rounded-xl border p-5">
                    <p class="text-muted-foreground text-xs font-medium uppercase tracking-wider">Bring appearances</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums">{{ meta.total_bring_units.toLocaleString() }}</p>
                </div>
            </div>

            <!-- Charts: 2-col grid, KO chart full width if present -->
            <div class="mb-10 grid gap-6 sm:grid-cols-2">
                <div
                    v-for="chart in charts"
                    :key="chart.key"
                    class="bg-card border-border rounded-xl border p-5"
                    :class="{ 'sm:col-span-2': chart.key === 'ko' && charts[4].labels.length === 0 ? false : chart.key === 'ko' }"
                >
                    <h2 class="mb-4 text-sm font-semibold">{{ chart.title }}</h2>
                    <div v-if="chart.labels.length === 0" class="text-muted-foreground text-sm italic">No data yet</div>
                    <div v-else class="flex flex-col gap-2">
                        <div v-for="(label, i) in chart.labels" :key="i" class="flex items-center gap-2 text-sm">
                            <span class="w-28 shrink-0 truncate" :title="label">{{ label }}</span>
                            <div class="h-2 min-w-0 flex-1 rounded-full bg-muted">
                                <div class="h-2 rounded-full transition-all" :class="chart.color" :style="{ width: barWidth(chart.values, i) }" />
                            </div>
                            <span class="text-muted-foreground w-14 shrink-0 text-right tabular-nums text-xs">
                                {{ chart.values[i] }}{{ chart.unit }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-card border-border rounded-xl border">
                <div class="border-border border-b px-5 py-4">
                    <h2 class="font-semibold">All Pokémon <span class="text-muted-foreground text-sm font-normal">(top 500 by picks)</span></h2>
                    <p class="text-muted-foreground mt-0.5 text-xs">Click any column header to sort.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-border border-b">
                            <tr>
                                <th
                                    v-for="col in columns"
                                    :key="col.key"
                                    class="text-muted-foreground hover:text-foreground cursor-pointer select-none px-3 py-2.5 text-xs font-medium whitespace-nowrap transition-colors"
                                    @click="setSort(col.key)"
                                >
                                    <div class="flex items-center gap-1">
                                        {{ col.label }}
                                        <ChevronUp v-if="sortKey === col.key && sortDir === 'asc'" class="size-3" />
                                        <ChevronDown v-else-if="sortKey === col.key && sortDir === 'desc'" class="size-3" />
                                        <ChevronsUpDown v-else class="text-muted-foreground/50 size-3" />
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-border divide-y">
                            <tr
                                v-for="r in sortedRows"
                                :key="r.pokedex_id"
                                class="hover:bg-muted/40 transition-colors"
                            >
                                <!-- Pokémon name + sprite -->
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <img
                                            v-if="r.sprite_url"
                                            :src="r.sprite_url"
                                            :alt="r.name ?? ''"
                                            class="h-8 w-8 shrink-0 object-contain"
                                            loading="lazy"
                                        />
                                        <div v-else class="h-8 w-8 shrink-0" />
                                        <span class="font-medium whitespace-nowrap">{{ r.name ?? '—' }}</span>
                                    </div>
                                </td>
                                <!-- Picks -->
                                <td class="px-3 py-2 tabular-nums">{{ r.draft_pick_count }}</td>
                                <!-- Pick % -->
                                <td class="text-muted-foreground px-3 py-2 tabular-nums">{{ (r.pick_rate * 100).toFixed(2) }}</td>
                                <!-- Bans -->
                                <td class="px-3 py-2 tabular-nums">{{ r.draft_ban_count }}</td>
                                <!-- Ban % -->
                                <td class="text-muted-foreground px-3 py-2 tabular-nums">{{ (r.ban_rate * 100).toFixed(2) }}</td>
                                <!-- Bring -->
                                <td class="px-3 py-2 tabular-nums">{{ r.match_bring_count }}</td>
                                <!-- Bring % -->
                                <td class="text-muted-foreground px-3 py-2 tabular-nums">{{ (r.bring_rate * 100).toFixed(2) }}</td>
                                <!-- W -->
                                <td class="px-3 py-2 tabular-nums text-green-700 dark:text-green-400">{{ r.game_wins }}</td>
                                <!-- L -->
                                <td class="text-destructive px-3 py-2 tabular-nums">{{ r.game_losses }}</td>
                                <!-- Win % -->
                                <td class="px-3 py-2 tabular-nums font-medium">
                                    {{ r.game_win_rate !== null ? (r.game_win_rate * 100).toFixed(1) + '%' : '—' }}
                                </td>
                                <!-- KOs -->
                                <td class="px-3 py-2 tabular-nums">
                                    <span v-if="r.ko_count > 0" class="text-rose-600 dark:text-rose-400 font-medium">{{ r.ko_count }}</span>
                                    <span v-else class="text-muted-foreground">0</span>
                                </td>
                            </tr>
                            <tr v-if="sortedRows.length === 0">
                                <td :colspan="columns.length" class="text-muted-foreground px-3 py-8 text-center text-sm">
                                    No data yet. Run the usage stats rebuild to populate.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
