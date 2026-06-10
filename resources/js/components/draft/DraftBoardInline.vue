<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Ban, Search } from 'lucide-vue-next';
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

    if (filterMode.value === 'all') {
        const picks = entries.filter((e) => e.kind === 'pick').sort((a, b) => (b.cost ?? 0) - (a.cost ?? 0));
        const bans = entries.filter((e) => e.kind === 'ban');

        return [...picks, ...bans];
    }

    if (filterMode.value === 'drafted') {
        return entries.sort((a, b) => (b.cost ?? 0) - (a.cost ?? 0));
    }

    return entries;
});

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim();
    if (!q) {
        return combined.value;
    }

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
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-b from-muted/25 via-card/50 to-card shadow-sm backdrop-blur-sm dark:from-muted/15 dark:via-card/30">
        <div class="border-b border-border/70 px-4 py-4 sm:px-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-bold tracking-tight text-foreground">Draft board</h3>
                <span class="text-xs text-muted-foreground tabular-nums">{{ filtered.length }} entries</span>
            </div>
            <div class="mt-3 flex flex-col gap-3">
                <div class="flex rounded-lg border border-border/80 bg-muted/30 p-0.5 dark:bg-muted/20">
                    <button
                        v-for="mode in modes"
                        :key="mode.value"
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-2 py-2 text-xs font-medium transition-colors sm:px-3"
                        :class="
                            filterMode === mode.value
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="filterMode = mode.value"
                    >
                        {{ mode.label }}
                        <span
                            class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground"
                        >
                            {{
                                mode.value === 'all' ? counts.all : mode.value === 'drafted' ? counts.drafted : counts.banned
                            }}
                        </span>
                    </button>
                </div>
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search by Pokémon or team…" class="pl-9" />
                </div>
            </div>
        </div>
        <ScrollArea class="min-h-[min(50vh,22rem)] flex-1 sm:min-h-[min(60vh,28rem)]">
            <div v-if="filtered.length === 0" class="py-14 text-center text-sm text-muted-foreground">No entries found.</div>
            <div v-else class="divide-y divide-border/80">
                <div
                    v-for="entry in filtered"
                    :key="`${entry.kind}-${entry.id}`"
                    class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-accent/35 sm:px-5"
                    :class="entry.kind === 'ban' ? 'bg-destructive/5 dark:bg-destructive/10' : ''"
                >
                    <div class="relative shrink-0">
                        <img
                            :src="spriteUrl(entry.name)"
                            :alt="entry.name"
                            class="size-11 object-contain"
                            :class="entry.kind === 'ban' ? 'opacity-50 grayscale' : ''"
                        />
                        <Ban
                            v-if="entry.kind === 'ban'"
                            class="absolute -bottom-1 -right-1 size-4 rounded-full bg-card text-destructive"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-medium capitalize text-foreground">
                                {{ entry.name }}
                            </p>
                            <span
                                v-if="entry.kind === 'ban'"
                                class="shrink-0 rounded-md bg-destructive/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-destructive"
                            >
                                Banned
                            </span>
                        </div>
                        <p class="text-xs capitalize text-muted-foreground">
                            {{ entry.type1
                            }}<template v-if="entry.type2 && entry.type2 !== '-'"> / {{ entry.type2 }}</template>
                        </p>
                    </div>
                    <span v-if="entry.cost !== null" class="shrink-0 text-sm font-semibold tabular-nums text-foreground">
                        {{ entry.cost }}
                    </span>
                    <span v-else class="shrink-0 w-6" />
                    <div class="flex shrink-0 items-center gap-2">
                        <img
                            v-if="entry.teamLogo"
                            :src="entry.teamLogo"
                            class="size-6 rounded-full bg-muted object-cover ring-1 ring-border/60"
                            alt=""
                        />
                        <div v-else class="size-6 shrink-0 rounded-full bg-muted ring-1 ring-border/60" />
                        <span class="max-w-[90px] truncate text-xs text-muted-foreground">{{ entry.teamName }}</span>
                    </div>
                </div>
            </div>
        </ScrollArea>
    </div>
</template>
