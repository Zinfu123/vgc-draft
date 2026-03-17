<script setup lang="ts">
import { ScrollArea } from '@/components/ui/scroll-area';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Ban, Users } from 'lucide-vue-next';
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
    <Sheet>
        <SheetTrigger as-child>
            <Button variant="outline" size="sm" class="gap-2">
                <Users class="size-4" />
                Teams
            </Button>
        </SheetTrigger>
        <SheetContent side="right" class="flex w-full flex-col p-0 sm:max-w-md">
            <SheetHeader class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                <SheetTitle>Draft Teams</SheetTitle>
            </SheetHeader>
            <ScrollArea class="flex-1">
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    <div v-for="team in teams" :key="team.id" class="p-4">
                        <!-- Team header -->
                        <div class="mb-3 flex items-center gap-3">
                            <img
                                v-if="team.logo"
                                :src="team.logo"
                                class="size-9 shrink-0 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                                alt=""
                            />
                            <div v-else class="size-9 shrink-0 rounded-full bg-gray-200 dark:bg-gray-700" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ team.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ team.draft_points }} pts remaining</p>
                            </div>
                            <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">{{ teamEntryCount[team.id] }} total</span>
                        </div>

                        <p v-if="teamEntryCount[team.id] === 0" class="py-2 text-center text-xs text-gray-400 dark:text-gray-500">
                            No picks or bans yet
                        </p>

                        <div v-else class="flex flex-col gap-0.5">
                            <!-- Draft picks — round asc -->
                            <template v-if="team.draft_picks.length > 0">
                                <div class="mb-1 px-2 pt-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Picks
                                </div>
                                <div
                                    v-for="pick in sortedPicks(team.draft_picks)"
                                    :key="pick.id"
                                    class="flex items-center gap-3 rounded-lg px-2 py-1 transition-colors hover:bg-gray-50 dark:hover:bg-white/5"
                                >
                                    <img
                                        :src="spriteUrl(pick.league_pokemon.pokemon.name)"
                                        :alt="pick.league_pokemon.pokemon.name"
                                        class="size-10 shrink-0 object-contain"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium capitalize text-gray-900 dark:text-white">
                                            {{ pick.league_pokemon.pokemon.name }}
                                        </p>
                                        <p class="text-xs capitalize text-gray-500 dark:text-gray-400">
                                            {{ pick.league_pokemon.pokemon.type1
                                            }}<template
                                                v-if="pick.league_pokemon.pokemon.type2 && pick.league_pokemon.pokemon.type2 !== '-'"
                                                > / {{ pick.league_pokemon.pokemon.type2 }}</template
                                            >
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ pick.league_pokemon.cost }}</span>
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500">R{{ pick.round_number }}</p>
                                    </div>
                                </div>
                            </template>

                            <!-- Bans — round asc -->
                            <template v-if="teamBans(team.id).length > 0">
                                <div
                                    class="mb-1 mt-2 px-2 pt-1 text-[10px] font-semibold uppercase tracking-wider text-red-400 dark:text-red-500"
                                    :class="team.draft_picks.length > 0 ? 'border-t border-gray-100 dark:border-white/10' : ''"
                                >
                                    Bans
                                </div>
                                <div
                                    v-for="ban in teamBans(team.id)"
                                    :key="ban.id"
                                    class="flex items-center gap-3 rounded-lg px-2 py-1 transition-colors hover:bg-red-50/50 dark:hover:bg-red-900/10"
                                >
                                    <div class="relative shrink-0">
                                        <img
                                            :src="spriteUrl(ban.pokedex!.name)"
                                            :alt="ban.pokedex!.name"
                                            class="size-10 object-contain opacity-50 grayscale"
                                        />
                                        <Ban class="absolute -bottom-1 -right-1 size-3.5 rounded-full bg-white text-red-500 dark:bg-gray-900" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium capitalize text-gray-500 dark:text-gray-400">
                                            {{ ban.pokedex!.name }}
                                        </p>
                                        <p class="text-xs capitalize text-gray-400 dark:text-gray-500">
                                            {{ ban.pokedex!.type1
                                            }}<template v-if="ban.pokedex!.type2 && ban.pokedex!.type2 !== '-'"> / {{ ban.pokedex!.type2 }}</template>
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-500 dark:bg-red-900/30 dark:text-red-400">Banned</span>
                                        <p class="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500">R{{ ban.round_number }}</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </ScrollArea>
        </SheetContent>
    </Sheet>
</template>
