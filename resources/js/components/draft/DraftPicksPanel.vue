<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Ban, ClipboardList, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface DraftPick {
    id: number;
    league_pokemon: {
        id: number;
        cost: number;
        pokemon: {
            id: number;
            name: string;
            sprite_url: string;
            type1: string;
            type2: string;
        };
    };
}

interface Team {
    id: number;
    name: string;
    logo: string | null;
    draft_picks: DraftPick[];
}

interface BanEntry {
    id: number;
    round_number: number;
    team: { id: number; name: string; logo: string | null } | null;
    pokedex: { id: number; name: string; type1: string; type2: string } | null;
}

type FilterMode = 'all' | 'drafted' | 'banned';

type FlatEntry =
    | { kind: 'pick'; id: number; name: string; type1: string; type2: string; cost: number; teamName: string; teamLogo: string | null }
    | { kind: 'ban'; id: number; name: string; type1: string; type2: string; cost: null; teamName: string; teamLogo: string | null };

const props = defineProps<{ teams: Team[]; bans: BanEntry[] }>();

const search = ref('');
const filterMode = ref<FilterMode>('all');

const allPicks = computed<FlatEntry[]>(() =>
    props.teams.flatMap((team) =>
        team.draft_picks.map((pick) => ({
            kind: 'pick' as const,
            id: pick.id,
            name: pick.league_pokemon.pokemon.name,
            type1: pick.league_pokemon.pokemon.type1,
            type2: pick.league_pokemon.pokemon.type2,
            cost: pick.league_pokemon.cost,
            teamName: team.name,
            teamLogo: team.logo,
        })),
    ),
);

const allBanEntries = computed<FlatEntry[]>(() =>
    props.bans
        .filter((b) => b.pokedex !== null)
        .map((b) => ({
            kind: 'ban' as const,
            id: b.id,
            name: b.pokedex!.name,
            type1: b.pokedex!.type1,
            type2: b.pokedex!.type2,
            cost: null,
            teamName: b.team?.name ?? 'Unknown',
            teamLogo: b.team?.logo ?? null,
        })),
);

const combined = computed<FlatEntry[]>(() => {
    let entries: FlatEntry[] = [];

    if (filterMode.value === 'all' || filterMode.value === 'drafted') {
        entries = entries.concat(allPicks.value);
    }
    if (filterMode.value === 'all' || filterMode.value === 'banned') {
        entries = entries.concat(allBanEntries.value);
    }

    // Picks sorted by cost desc; bans at the end when showing all, or cost desc among picks
    if (filterMode.value === 'all') {
        const picks = entries.filter((e) => e.kind === 'pick').sort((a, b) => (b.cost ?? 0) - (a.cost ?? 0));
        const bans = entries.filter((e) => e.kind === 'ban');
        return [...picks, ...bans];
    }

    if (filterMode.value === 'drafted') {
        return entries.sort((a, b) => (b.cost ?? 0) - (a.cost ?? 0));
    }

    return entries; // banned — already ordered from backend
});

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim();
    if (!q) return combined.value;
    return combined.value.filter(
        (e) => e.name.toLowerCase().includes(q) || e.teamName.toLowerCase().includes(q),
    );
});

const counts = computed(() => ({
    drafted: allPicks.value.length,
    banned: allBanEntries.value.length,
    all: allPicks.value.length + allBanEntries.value.length,
}));

const spriteUrl = (name: string) =>
    `https://raw.githubusercontent.com/Autumnchi/coloured-home-sprites/main/${name}.png`;

const modes: { value: FilterMode; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'drafted', label: 'Drafted' },
    { value: 'banned', label: 'Banned' },
];
</script>

<template>
    <Sheet>
        <SheetTrigger as-child>
            <Button variant="outline" size="sm" class="gap-2">
                <ClipboardList class="size-4" />
                Draft Board
            </Button>
        </SheetTrigger>
        <SheetContent side="right" class="flex w-full flex-col p-0 sm:max-w-lg">
            <SheetHeader class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                <div class="flex items-center justify-between">
                    <SheetTitle>Draft Board</SheetTitle>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ filtered.length }} entries</span>
                </div>
            </SheetHeader>

            <!-- Filter toggle + search -->
            <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
                <!-- Mode tabs -->
                <div class="flex rounded-lg border border-gray-200 p-0.5 dark:border-white/10">
                    <button
                        v-for="mode in modes"
                        :key="mode.value"
                        @click="filterMode = mode.value"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="
                            filterMode === mode.value
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                        "
                    >
                        {{ mode.label }}
                        <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-gray-600 dark:text-gray-300">
                            {{ mode.value === 'all' ? counts.all : mode.value === 'drafted' ? counts.drafted : counts.banned }}
                        </span>
                    </button>
                </div>
                <!-- Search -->
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <Input v-model="search" placeholder="Search by Pokémon or team…" class="pl-9" />
                </div>
            </div>

            <ScrollArea class="flex-1">
                <div v-if="filtered.length === 0" class="py-16 text-center text-sm text-gray-400 dark:text-gray-500">
                    No entries found.
                </div>
                <div v-else class="divide-y divide-gray-100 dark:divide-white/10">
                    <div
                        v-for="entry in filtered"
                        :key="`${entry.kind}-${entry.id}`"
                        class="flex items-center gap-3 px-4 py-2 transition-colors hover:bg-gray-50 dark:hover:bg-white/5"
                        :class="entry.kind === 'ban' ? 'bg-red-50/50 dark:bg-red-900/10' : ''"
                    >
                        <!-- Sprite -->
                        <div class="relative shrink-0">
                            <img
                                :src="spriteUrl(entry.name)"
                                :alt="entry.name"
                                class="size-11 object-contain"
                                :class="entry.kind === 'ban' ? 'opacity-50 grayscale' : ''"
                            />
                            <Ban
                                v-if="entry.kind === 'ban'"
                                class="absolute -bottom-1 -right-1 size-4 rounded-full bg-white text-red-500 dark:bg-gray-900"
                            />
                        </div>

                        <!-- Pokémon info -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-sm font-medium capitalize text-gray-900 dark:text-white">
                                    {{ entry.name }}
                                </p>
                                <span
                                    v-if="entry.kind === 'ban'"
                                    class="shrink-0 rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-600 dark:bg-red-900/40 dark:text-red-400"
                                >
                                    Banned
                                </span>
                            </div>
                            <p class="text-xs capitalize text-gray-500 dark:text-gray-400">
                                {{ entry.type1
                                }}<template v-if="entry.type2 && entry.type2 !== '-'"> / {{ entry.type2 }}</template>
                            </p>
                        </div>

                        <!-- Cost (picks only) -->
                        <span v-if="entry.cost !== null" class="shrink-0 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            {{ entry.cost }}
                        </span>
                        <span v-else class="shrink-0 w-6" />

                        <!-- Team -->
                        <div class="flex shrink-0 items-center gap-2">
                            <img
                                v-if="entry.teamLogo"
                                :src="entry.teamLogo"
                                class="size-6 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                                alt=""
                            />
                            <div v-else class="size-6 shrink-0 rounded-full bg-gray-200 dark:bg-gray-700" />
                            <span class="max-w-[90px] truncate text-xs text-gray-600 dark:text-gray-300">{{ entry.teamName }}</span>
                        </div>
                    </div>
                </div>
            </ScrollArea>
        </SheetContent>
    </Sheet>
</template>
