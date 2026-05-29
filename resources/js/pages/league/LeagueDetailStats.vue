<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
    league_owner: number;
    status: number;
    playoffs_enabled: boolean;
}

interface Team {
    id: number;
    league_id: number;
    name: string;
    coach: string;
    logo: string | null;
    set_wins: number;
    set_losses: number;
    victory_points: number;
}

interface Draft {
    id: number | null;
    round_number: number;
    pick_number: number;
    status: number;
}

interface MatchConfig {
    id: number;
    league_id: number;
    number_of_pools: number;
    frequency_type: number;
    frequency_value: number;
    status: number;
}

interface KillLeader {
    pokedex_id: number;
    name: string | null;
    sprite_url: string | null;
    type1: string | null;
    type2: string | null;
    coach: string | null;
    kills: number;
    deaths: number;
    differential: number;
    gp: number;
    games_brought: number;
    avg_ko_per_game: number | null;
    damage: number;
}

const props = defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
    killLeaders: KillLeader[] | null;
}>();

// — Kill leaders table sorting —
type SortKey = 'kills' | 'deaths' | 'differential' | 'gp' | 'games_brought' | 'avg_ko_per_game' | 'damage';
const sortKey = ref<SortKey>('kills');
const sortDir = ref<'asc' | 'desc'>('desc');

function setSort(key: SortKey): void {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc';
    } else {
        sortKey.value = key;
        sortDir.value = 'desc';
    }
}

const sortedLeaders = computed<KillLeader[]>(() => {
    if (!props.killLeaders) return [];
    return [...props.killLeaders].sort((a, b) => {
        const av = a[sortKey.value];
        const bv = b[sortKey.value];
        if (av == null && bv == null) return 0;
        if (av == null) return 1;
        if (bv == null) return -1;
        if (av === bv) return 0;
        const cmp = av < bv ? -1 : 1;
        return sortDir.value === 'asc' ? cmp : -cmp;
    });
});

const topThree = computed<KillLeader[]>(() => {
    if (!props.killLeaders) return [];
    return [...props.killLeaders].sort((a, b) => b.kills - a.kills).slice(0, 3);
});

// Top 5 for damage leaderboard
const topFiveDamage = computed<KillLeader[]>(() => {
    if (!props.killLeaders) return [];
    return [...props.killLeaders].sort((a, b) => b.damage - a.damage).filter((p) => p.damage > 0).slice(0, 5);
});

const hasAnyKills = computed(() => props.killLeaders !== null && props.killLeaders.some((p) => p.kills > 0));
const hasAnyDamage = computed(() => props.killLeaders !== null && props.killLeaders.some((p) => p.damage > 0));

const cardThemes = [
    { bg: 'bg-amber-500 dark:bg-amber-600', badge: 'bg-amber-300/40 dark:bg-amber-300/30', rank: '#1' },
    { bg: 'bg-slate-400 dark:bg-slate-500', badge: 'bg-slate-200/40 dark:bg-slate-200/30', rank: '#2' },
    { bg: 'bg-amber-700 dark:bg-amber-800', badge: 'bg-amber-500/40 dark:bg-amber-500/30', rank: '#3' },
];

function spriteUrl(pokemon: KillLeader): string {
    if (pokemon.sprite_url) return pokemon.sprite_url;
    return `https://raw.githubusercontent.com/Autumnchi/coloured-home-sprites/main/${pokemon.name}.png`;
}

function differentialClass(diff: number): string {
    if (diff > 0) return 'text-green-300';
    if (diff < 0) return 'text-red-300';
    return 'text-white/70';
}

function formatAvgKoPerGame(value: number | null): string {
    if (value == null) return '—';
    return value.toFixed(2);
}
</script>

<template>
    <LeagueDetailLayout :league="league" section="stats" :teams="teams" :draft="draft" :adminFlag="adminFlag" :matchConfig="matchConfig">
        <Head :title="`${league.name} – Stats`" />

        <!-- Loading skeleton -->
        <template v-if="killLeaders === null">
            <div class="mb-8">
                <div class="mb-4 h-6 w-48 animate-pulse rounded bg-muted"></div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div v-for="i in 3" :key="i" class="h-56 animate-pulse rounded-xl bg-muted"></div>
                </div>
            </div>
            <div class="h-64 animate-pulse rounded-xl bg-muted"></div>
        </template>

        <!-- No data yet -->
        <template v-else-if="killLeaders.length === 0 || !hasAnyKills">
            <div class="flex flex-col items-center justify-center py-20 text-center text-muted-foreground">
                <p class="text-lg font-semibold">No battle data yet</p>
                <p class="mt-1 text-sm">Stats will appear once replays have been processed for league matches.</p>
            </div>
        </template>

        <template v-else>
            <!-- ── Kill Leaders ── -->
            <section class="mb-10">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-widest text-muted-foreground">League Kill Leaders</h2>

                <!-- Top 3 cards -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div
                        v-for="(pokemon, index) in topThree"
                        :key="pokemon.pokedex_id"
                        :class="[cardThemes[index].bg, 'relative overflow-hidden rounded-xl p-4 text-white shadow-lg']"
                    >
                        <!-- Rank badge + coach -->
                        <div class="flex items-center justify-between">
                            <span :class="[cardThemes[index].badge, 'rounded px-2 py-0.5 text-xs font-bold uppercase']">
                                {{ cardThemes[index].rank }}
                            </span>
                            <span class="truncate text-xs font-medium uppercase opacity-80">{{ pokemon.coach ?? '—' }}</span>
                        </div>

                        <!-- Sprite -->
                        <div class="flex justify-end">
                            <img
                                v-if="pokemon.sprite_url || pokemon.name"
                                :src="spriteUrl(pokemon)"
                                :alt="pokemon.name ?? ''"
                                class="-mb-4 -mr-2 h-36 w-36 object-contain drop-shadow-lg"
                                loading="lazy"
                            />
                        </div>

                        <!-- Name + type -->
                        <div class="-mt-2 mb-3">
                            <p class="text-xl font-black uppercase leading-tight tracking-wide">{{ pokemon.name ?? '?' }}</p>
                            <p v-if="pokemon.type1" class="mt-0.5 text-xs font-medium capitalize opacity-75">
                                {{ pokemon.type1 }}{{ pokemon.type2 && pokemon.type2 !== '-' ? ' / ' + pokemon.type2 : '' }}
                            </p>
                        </div>

                        <!-- Stats row -->
                        <div :class="[cardThemes[index].badge, 'grid grid-cols-5 gap-1 rounded-lg p-2 text-center']">
                            <div>
                                <p class="text-base font-bold">× {{ pokemon.kills }}</p>
                                <p class="text-[10px] uppercase opacity-70">Kills</p>
                            </div>
                            <div>
                                <p class="text-base font-bold">{{ pokemon.deaths }}</p>
                                <p class="text-[10px] uppercase opacity-70">Deaths</p>
                            </div>
                            <div>
                                <p :class="['text-base font-bold', differentialClass(pokemon.differential)]">
                                    {{ pokemon.differential > 0 ? '+' : '' }}{{ pokemon.differential }}
                                </p>
                                <p class="text-[10px] uppercase opacity-70">+/-</p>
                            </div>
                            <div>
                                <p class="text-base font-bold">{{ pokemon.games_brought }}</p>
                                <p class="text-[10px] uppercase opacity-70">Brought</p>
                            </div>
                            <div>
                                <p class="text-base font-bold">{{ formatAvgKoPerGame(pokemon.avg_ko_per_game) }}</p>
                                <p class="text-[10px] uppercase opacity-70">KO/G</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Full kill leaders table -->
                <div class="mt-6 overflow-x-auto rounded-xl border border-border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border bg-muted/50 text-xs uppercase text-muted-foreground">
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Pokemon</th>
                                <th class="px-3 py-2 text-left">Coach</th>
                                <th class="cursor-pointer px-3 py-2 text-right hover:text-foreground" @click="setSort('kills')">
                                    K {{ sortKey === 'kills' ? (sortDir === 'desc' ? '↓' : '↑') : '' }}
                                </th>
                                <th class="cursor-pointer px-3 py-2 text-right hover:text-foreground" @click="setSort('deaths')">
                                    D {{ sortKey === 'deaths' ? (sortDir === 'desc' ? '↓' : '↑') : '' }}
                                </th>
                                <th class="cursor-pointer px-3 py-2 text-right hover:text-foreground" @click="setSort('differential')">
                                    +/- {{ sortKey === 'differential' ? (sortDir === 'desc' ? '↓' : '↑') : '' }}
                                </th>
                                <th class="cursor-pointer px-3 py-2 text-right hover:text-foreground" @click="setSort('games_brought')">
                                    Brought {{ sortKey === 'games_brought' ? (sortDir === 'desc' ? '↓' : '↑') : '' }}
                                </th>
                                <th class="cursor-pointer px-3 py-2 text-right hover:text-foreground" @click="setSort('avg_ko_per_game')">
                                    KO/G {{ sortKey === 'avg_ko_per_game' ? (sortDir === 'desc' ? '↓' : '↑') : '' }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(pokemon, i) in sortedLeaders"
                                :key="pokemon.pokedex_id"
                                class="border-b border-border/50 transition-colors hover:bg-muted/30"
                            >
                                <td class="px-3 py-2 text-muted-foreground">{{ i + 1 }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <img
                                            v-if="pokemon.sprite_url || pokemon.name"
                                            :src="spriteUrl(pokemon)"
                                            :alt="pokemon.name ?? ''"
                                            class="h-8 w-8 object-contain"
                                            loading="lazy"
                                        />
                                        <span class="font-medium capitalize">{{ pokemon.name ?? '?' }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-muted-foreground">{{ pokemon.coach ?? '—' }}</td>
                                <td class="px-3 py-2 text-right font-medium">{{ pokemon.kills }}</td>
                                <td class="px-3 py-2 text-right font-medium">{{ pokemon.deaths }}</td>
                                <td
                                    class="px-3 py-2 text-right font-bold"
                                    :class="{
                                        'text-green-500 dark:text-green-400': pokemon.differential > 0,
                                        'text-red-500 dark:text-red-400': pokemon.differential < 0,
                                    }"
                                >
                                    {{ pokemon.differential > 0 ? '+' : '' }}{{ pokemon.differential }}
                                </td>
                                <td class="px-3 py-2 text-right text-muted-foreground">{{ pokemon.games_brought }}</td>
                                <td class="px-3 py-2 text-right font-medium">{{ formatAvgKoPerGame(pokemon.avg_ko_per_game) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ── League Damage ── -->
            <section v-if="hasAnyDamage" class="mb-10">
                <h2 class="mb-4 text-sm font-bold uppercase tracking-widest text-muted-foreground">League Damage</h2>

                <div class="overflow-hidden rounded-xl bg-rose-900 dark:bg-rose-950 text-white shadow-lg">
                    <div class="flex items-stretch">
                        <!-- Leader info -->
                        <div class="flex min-w-0 flex-1 flex-col justify-between p-5">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-rose-300">⚔ League Damage</p>
                            <div class="mt-2">
                                <p class="text-2xl font-black uppercase tracking-wide">{{ topFiveDamage[0]?.name ?? '—' }}</p>
                                <p class="text-xs text-rose-300">{{ topFiveDamage[0]?.coach ?? '' }}</p>
                            </div>
                            <p class="mt-3 text-3xl font-black">
                                {{ topFiveDamage[0]?.damage.toLocaleString() }}
                                <span class="text-base font-medium text-rose-300">DMG</span>
                            </p>
                        </div>

                        <!-- Leader sprite -->
                        <div class="relative flex-shrink-0 self-end">
                            <img
                                v-if="topFiveDamage[0]"
                                :src="spriteUrl(topFiveDamage[0])"
                                :alt="topFiveDamage[0].name ?? ''"
                                class="h-32 w-32 object-contain drop-shadow-lg"
                                loading="lazy"
                            />
                        </div>

                        <!-- 2nd–5th place -->
                        <div v-if="topFiveDamage.length > 1" class="flex flex-col justify-center gap-3 bg-rose-950/50 p-4">
                            <div
                                v-for="(pokemon, i) in topFiveDamage.slice(1)"
                                :key="pokemon.pokedex_id"
                                class="flex items-center gap-2"
                            >
                                <div class="relative flex-shrink-0">
                                    <img
                                        :src="spriteUrl(pokemon)"
                                        :alt="pokemon.name ?? ''"
                                        class="h-10 w-10 object-contain"
                                        loading="lazy"
                                    />
                                    <span class="absolute -top-1 -left-1 rounded bg-rose-700 px-0.5 text-[9px] font-bold">#{{ i + 2 }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold capitalize leading-tight">{{ pokemon.name }}</p>
                                    <p class="text-[10px] text-rose-300">{{ pokemon.damage.toLocaleString() }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </LeagueDetailLayout>
</template>
