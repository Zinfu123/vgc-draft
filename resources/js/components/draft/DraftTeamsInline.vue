<script setup lang="ts">
import { ScrollArea } from '@/components/ui/scroll-area';
import { Ban } from 'lucide-vue-next';
import { computed } from 'vue';

interface DraftPick {
    id: number;
    round_number: number;
    pick_number: number;
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
    draft_points: number;
    logo: string | null;
    draft_picks: DraftPick[];
}

interface BanEntry {
    id: number;
    round_number: number;
    team: { id: number; name: string; logo: string | null } | null;
    pokedex: { id: number; name: string; type1: string; type2: string } | null;
}

const props = defineProps<{ teams: Team[]; bans: BanEntry[] }>();

const teamBans = (teamId: number) =>
    props.bans
        .filter((b) => b.team?.id === teamId && b.pokedex !== null)
        .sort((a, b) => a.round_number - b.round_number);

const sortedPicks = (picks: DraftPick[]) =>
    [...picks].sort((a, b) => a.round_number - b.round_number || a.pick_number - b.pick_number);

const teamEntryCount = computed(() =>
    Object.fromEntries(
        props.teams.map((t) => [t.id, t.draft_picks.length + teamBans(t.id).length]),
    ),
);

const spriteUrl = (name: string) =>
    `https://raw.githubusercontent.com/Autumnchi/coloured-home-sprites/main/${name}.png`;
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-b from-muted/25 via-card/50 to-card shadow-sm backdrop-blur-sm dark:from-muted/15 dark:via-card/30">
        <div class="border-b border-border/70 px-4 py-4 sm:px-5">
            <h3 class="text-sm font-bold tracking-tight text-foreground">By team</h3>
            <p class="mt-1 text-xs text-muted-foreground">Picks and bans grouped by roster</p>
        </div>
        <ScrollArea class="min-h-[min(50vh,22rem)] flex-1 sm:min-h-[min(60vh,28rem)]">
            <div class="divide-y divide-border/80 px-2 pb-4 pt-2 sm:px-3">
                <div v-for="team in teams" :key="team.id" class="rounded-xl px-2 py-4 sm:px-3">
                    <div class="mb-3 flex items-center gap-3">
                        <img
                            v-if="team.logo"
                            :src="team.logo"
                            class="size-10 shrink-0 rounded-full bg-muted object-cover ring-1 ring-border/60"
                            alt=""
                        />
                        <div v-else class="size-10 shrink-0 rounded-full bg-muted ring-1 ring-border/60" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-foreground">{{ team.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ team.draft_points }} pts after draft</p>
                        </div>
                        <span class="shrink-0 text-xs tabular-nums text-muted-foreground">{{ teamEntryCount[team.id] }} total</span>
                    </div>

                    <p v-if="teamEntryCount[team.id] === 0" class="py-3 text-center text-xs text-muted-foreground">
                        No picks or bans
                    </p>

                    <div v-else class="flex flex-col gap-0.5">
                        <template v-if="team.draft_picks.length > 0">
                            <div class="mb-1 px-2 pt-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                Picks
                            </div>
                            <div
                                v-for="pick in sortedPicks(team.draft_picks)"
                                :key="pick.id"
                                class="flex items-center gap-3 rounded-lg px-2 py-1.5 transition-colors hover:bg-accent/35"
                            >
                                <img
                                    :src="spriteUrl(pick.league_pokemon.pokemon.name)"
                                    :alt="pick.league_pokemon.pokemon.name"
                                    class="size-10 shrink-0 object-contain"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium capitalize text-foreground">
                                        {{ pick.league_pokemon.pokemon.name }}
                                    </p>
                                    <p class="text-xs capitalize text-muted-foreground">
                                        {{ pick.league_pokemon.pokemon.type1
                                        }}<template
                                            v-if="pick.league_pokemon.pokemon.type2 && pick.league_pokemon.pokemon.type2 !== '-'"
                                            > / {{ pick.league_pokemon.pokemon.type2 }}</template
                                        >
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="text-xs font-semibold tabular-nums text-foreground">{{
                                        pick.league_pokemon.cost
                                    }}</span>
                                    <p class="text-[10px] text-muted-foreground">R{{ pick.round_number }}</p>
                                </div>
                            </div>
                        </template>

                        <template v-if="teamBans(team.id).length > 0">
                            <div
                                class="mb-1 mt-3 px-2 pt-1 text-[10px] font-semibold uppercase tracking-wider text-destructive"
                                :class="team.draft_picks.length > 0 ? 'border-t border-border/60 pt-3' : ''"
                            >
                                Bans
                            </div>
                            <div
                                v-for="ban in teamBans(team.id)"
                                :key="ban.id"
                                class="flex items-center gap-3 rounded-lg px-2 py-1.5 transition-colors hover:bg-destructive/10"
                            >
                                <div class="relative shrink-0">
                                    <img
                                        :src="spriteUrl(ban.pokedex!.name)"
                                        :alt="ban.pokedex!.name"
                                        class="size-10 object-contain opacity-50 grayscale"
                                    />
                                    <Ban class="absolute -bottom-1 -right-1 size-3.5 rounded-full bg-card text-destructive" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium capitalize text-muted-foreground">
                                        {{ ban.pokedex!.name }}
                                    </p>
                                    <p class="text-xs capitalize text-muted-foreground">
                                        {{ ban.pokedex!.type1
                                        }}<template v-if="ban.pokedex!.type2 && ban.pokedex!.type2 !== '-'">
                                            / {{ ban.pokedex!.type2 }}</template
                                        >
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span
                                        class="rounded-md bg-destructive/15 px-1.5 py-0.5 text-[10px] font-semibold text-destructive"
                                        >Banned</span
                                    >
                                    <p class="mt-0.5 text-[10px] text-muted-foreground">R{{ ban.round_number }}</p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </ScrollArea>
    </div>
</template>
