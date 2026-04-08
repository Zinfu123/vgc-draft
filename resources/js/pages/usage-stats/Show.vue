<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import { computed } from 'vue';

interface Pokemon {
    pokedex_id: number;
    name: string;
    sprite_url: string | null;
    type1: string;
    type2: string | null;
}

interface Stat {
    draft_pick_count: number;
    draft_ban_count: number;
    match_bring_count: number;
    game_wins: number;
    game_losses: number;
    ko_count: number;
}

interface GameEntry {
    set_id: number;
    round: number;
    game_number: number;
    team1_name: string | null;
    team2_name: string | null;
    team1_score: number | null;
    team2_score: number | null;
    won_game: boolean;
    ko_count: number;
    replay_url: string | null;
}

const props = defineProps<{
    pokemon: Pokemon;
    stat: Stat | null;
    games: GameEntry[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Usage stats', href: route('usage-stats.index') },
    { title: props.pokemon.name ?? 'Pokémon', href: '#' },
];

const gameWinRate = computed(() => {
    if (!props.stat) return null;
    const played = props.stat.game_wins + props.stat.game_losses;
    return played > 0 ? props.stat.game_wins / played : null;
});

function typeStyle(type1: string, type2: string | null): string {
    const t1 = type1.toLowerCase();
    const t2 = type2?.toLowerCase();
    const hasType2 = t2 && t2 !== '-' && t2 !== '';
    if (hasType2) {
        return `background-image: linear-gradient(90deg, var(--${t1}type) 50%, var(--${t2}type) 50%)`;
    }
    return `background-color: var(--${t1}type)`;
}

function typeBadgeStyle(type: string): string {
    return `background-color: var(--${type.toLowerCase()}type)`;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${pokemon.name} — Usage Stats`" />

        <div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">

            <!-- Header card -->
            <div class="bg-card border-border mb-8 overflow-hidden rounded-xl border">
                <!-- Type gradient banner -->
                <div
                    class="h-2 w-full"
                    :style="typeStyle(pokemon.type1, pokemon.type2)"
                />
                <div class="flex items-center gap-5 px-6 py-5">
                    <img
                        v-if="pokemon.sprite_url"
                        :src="pokemon.sprite_url"
                        :alt="pokemon.name"
                        class="h-20 w-20 shrink-0 object-contain"
                    />
                    <div v-else class="h-20 w-20 shrink-0" />
                    <div class="flex flex-col gap-2">
                        <h1 class="text-2xl font-bold">{{ pokemon.name }}</h1>
                        <div class="flex gap-1.5">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize text-white"
                                :style="typeBadgeStyle(pokemon.type1)"
                            >{{ pokemon.type1 }}</span>
                            <span
                                v-if="pokemon.type2 && pokemon.type2 !== '-'"
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize text-white"
                                :style="typeBadgeStyle(pokemon.type2)"
                            >{{ pokemon.type2 }}</span>
                        </div>
                    </div>
                    <div class="ml-auto">
                        <Link
                            :href="route('usage-stats.index')"
                            class="text-muted-foreground hover:text-foreground text-sm transition-colors"
                        >
                            ← Back to all stats
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Stat summary cards -->
            <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-card border-border rounded-xl border p-5">
                    <p class="text-muted-foreground text-xs font-medium uppercase tracking-wider">Draft picks</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums">{{ stat?.draft_pick_count ?? 0 }}</p>
                </div>
                <div class="bg-card border-border rounded-xl border p-5">
                    <p class="text-muted-foreground text-xs font-medium uppercase tracking-wider">Bring appearances</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums">{{ stat?.match_bring_count ?? 0 }}</p>
                </div>
                <div class="bg-card border-border rounded-xl border p-5">
                    <p class="text-muted-foreground text-xs font-medium uppercase tracking-wider">Game record</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums">
                        <span class="text-green-700 dark:text-green-400">{{ stat?.game_wins ?? 0 }}</span>
                        <span class="text-muted-foreground mx-1 text-xl">–</span>
                        <span class="text-destructive">{{ stat?.game_losses ?? 0 }}</span>
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs tabular-nums">
                        {{ gameWinRate !== null ? (gameWinRate * 100).toFixed(1) + '% win rate' : 'No games yet' }}
                    </p>
                </div>
                <div class="bg-card border-border rounded-xl border p-5">
                    <p class="text-muted-foreground text-xs font-medium uppercase tracking-wider">Total KOs</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-rose-600 dark:text-rose-400">{{ stat?.ko_count ?? 0 }}</p>
                </div>
            </div>

            <!-- Games table -->
            <div class="bg-card border-border rounded-xl border">
                <div class="border-border border-b px-5 py-4">
                    <h2 class="font-semibold">Games from replays</h2>
                    <p class="text-muted-foreground mt-0.5 text-xs">Every game where {{ pokemon.name }} was brought, parsed from Showdown replays.</p>
                </div>

                <div v-if="games.length === 0" class="text-muted-foreground px-5 py-10 text-center text-sm">
                    No replay game data found for {{ pokemon.name }}. Replays must be submitted and usage stats rebuilt.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-border border-b">
                            <tr>
                                <th class="text-muted-foreground px-4 py-2.5 text-xs font-medium whitespace-nowrap">Round</th>
                                <th class="text-muted-foreground px-4 py-2.5 text-xs font-medium whitespace-nowrap">Match</th>
                                <th class="text-muted-foreground px-4 py-2.5 text-xs font-medium whitespace-nowrap">Game</th>
                                <th class="text-muted-foreground px-4 py-2.5 text-xs font-medium whitespace-nowrap">Score</th>
                                <th class="text-muted-foreground px-4 py-2.5 text-xs font-medium whitespace-nowrap">Result</th>
                                <th class="text-muted-foreground px-4 py-2.5 text-xs font-medium whitespace-nowrap">KOs</th>
                                <th class="text-muted-foreground px-4 py-2.5 text-xs font-medium whitespace-nowrap">Replay</th>
                            </tr>
                        </thead>
                        <tbody class="divide-border divide-y">
                            <tr
                                v-for="(g, i) in games"
                                :key="i"
                                class="hover:bg-muted/40 transition-colors"
                            >
                                <!-- Round -->
                                <td class="text-muted-foreground px-4 py-2.5 tabular-nums">
                                    R{{ g.round }}
                                </td>

                                <!-- Match -->
                                <td class="px-4 py-2.5">
                                    <Link
                                        :href="route('sets.show', { set_id: g.set_id })"
                                        class="hover:text-primary whitespace-nowrap font-medium transition-colors"
                                    >
                                        {{ g.team1_name ?? '?' }} vs {{ g.team2_name ?? '?' }}
                                    </Link>
                                </td>

                                <!-- Game number -->
                                <td class="text-muted-foreground px-4 py-2.5 tabular-nums">
                                    G{{ g.game_number }}
                                </td>

                                <!-- Series score -->
                                <td class="text-muted-foreground px-4 py-2.5 tabular-nums whitespace-nowrap">
                                    <span v-if="g.team1_score !== null && g.team2_score !== null">
                                        {{ g.team1_score }}–{{ g.team2_score }}
                                    </span>
                                    <span v-else>—</span>
                                </td>

                                <!-- Win/Loss badge -->
                                <td class="px-4 py-2.5">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="g.won_game
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                            : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'"
                                    >
                                        {{ g.won_game ? 'W' : 'L' }}
                                    </span>
                                </td>

                                <!-- KOs scored in this game -->
                                <td class="px-4 py-2.5 tabular-nums">
                                    <span v-if="g.ko_count > 0" class="font-medium text-rose-600 dark:text-rose-400">{{ g.ko_count }}</span>
                                    <span v-else class="text-muted-foreground">0</span>
                                </td>

                                <!-- Replay link -->
                                <td class="px-4 py-2.5">
                                    <a
                                        v-if="g.replay_url"
                                        :href="g.replay_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-primary inline-flex items-center gap-1 text-sm hover:underline"
                                    >
                                        Watch <ExternalLink class="size-3" />
                                    </a>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
