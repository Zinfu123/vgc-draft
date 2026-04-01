<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import DraftBoardInline from '@/components/draft/DraftBoardInline.vue';
import DraftTeamsInline from '@/components/draft/DraftTeamsInline.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import { Button } from '@/components/ui/button';
import Tabs from '@/components/ui/tabs/Tabs.vue';
import TabsContent from '@/components/ui/tabs/TabsContent.vue';
import TabsList from '@/components/ui/tabs/TabsList.vue';
import TabsTrigger from '@/components/ui/tabs/TabsTrigger.vue';
import { useMobileLayout } from '@/composables/useMobileLayout';
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList, ScrollText, Swords, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
    league_owner: number;
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

interface DraftPickRecap {
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

interface DraftRecapTeam {
    id: number;
    name: string;
    logo: string | null;
    draft_points: number;
    draft_picks: DraftPickRecap[];
}

interface DraftRecapBan {
    id: number;
    round_number: number;
    team: { id: number; name: string; logo: string | null } | null;
    pokedex: { id: number; name: string; type1: string; type2: string } | null;
}

const props = defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
    draft_recap_teams: DraftRecapTeam[] | null;
    draft_recap_bans: DraftRecapBan[] | null;
}>();

const { isMobile } = useMobileLayout();

const isDraftCompleted = computed(() => props.draft !== null && props.draft.status === 0);
const isDraftLive = computed(() => props.draft !== null && (props.draft.status === 1 || props.draft.status === 2));

const recapTeams = computed(() => props.draft_recap_teams ?? []);
const recapBans = computed(() => props.draft_recap_bans ?? []);
</script>

<template>
    <LeagueDetailLayout :league="league" section="draft" :teams="teams" :draft="draft" :adminFlag="adminFlag" :matchConfig="matchConfig">
        <Head :title="`Draft · ${league.name}`" />

        <div class="relative">
            <div class="pointer-events-none absolute inset-0 overflow-hidden select-none" aria-hidden="true">
                <div class="absolute -top-16 right-0 h-64 w-64 rounded-full bg-violet-500/10 blur-3xl dark:bg-violet-500/15" />
                <div class="absolute top-1/3 -left-16 h-56 w-56 rounded-full bg-emerald-500/10 blur-3xl dark:bg-emerald-500/12" />
            </div>

            <div class="relative z-10 flex flex-col gap-6">
                <!-- Completed recap -->
                <template v-if="isDraftCompleted">
                    <div class="space-y-2">
                        <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                            <ScrollText class="size-3.5 opacity-70" aria-hidden="true" />
                            Recap
                        </p>
                        <h2 class="text-balance text-2xl font-bold tracking-tight sm:text-3xl">Draft results</h2>
                        <p class="max-w-2xl text-sm text-muted-foreground sm:text-base">
                            Final picks and bans for this league. Use the board for a flat list or switch to by-team view for
                            each roster.
                        </p>
                    </div>

                    <Tabs v-if="isMobile" default-value="board" class="w-full gap-4">
                        <TabsList class="grid h-auto w-full grid-cols-2 gap-1 p-1">
                            <TabsTrigger value="board" class="touch-manipulation gap-2 text-xs sm:text-sm">
                                <ClipboardList class="size-3.5 shrink-0" />
                                Board
                            </TabsTrigger>
                            <TabsTrigger value="teams" class="touch-manipulation gap-2 text-xs sm:text-sm">
                                <Users class="size-3.5 shrink-0" />
                                Teams
                            </TabsTrigger>
                        </TabsList>
                        <TabsContent value="board" class="mt-0 focus-visible:outline-none">
                            <DraftBoardInline :teams="recapTeams" :bans="recapBans" />
                        </TabsContent>
                        <TabsContent value="teams" class="mt-0 focus-visible:outline-none">
                            <DraftTeamsInline :teams="recapTeams" :bans="recapBans" />
                        </TabsContent>
                    </Tabs>

                    <div v-else class="grid grid-cols-1 items-start gap-6 xl:grid-cols-2">
                        <DraftBoardInline :teams="recapTeams" :bans="recapBans" />
                        <DraftTeamsInline :teams="recapTeams" :bans="recapBans" />
                    </div>
                </template>

                <!-- Live draft CTA -->
                <template v-else-if="isDraftLive">
                    <div class="space-y-2">
                        <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                            <Swords class="size-3.5 opacity-70" aria-hidden="true" />
                            Live
                        </p>
                        <h2 class="text-balance text-2xl font-bold tracking-tight sm:text-3xl">Draft in progress</h2>
                        <p class="max-w-xl text-sm text-muted-foreground sm:text-base">
                            Open the draft room for picks, bans, and the full Pokémon board.
                        </p>
                    </div>
                    <div
                        class="rounded-2xl border border-border/80 bg-gradient-to-b from-muted/30 via-card/60 to-card p-8 text-center shadow-sm backdrop-blur-sm dark:from-muted/20 dark:via-card/40 sm:p-10"
                    >
                        <Button size="lg" as-child>
                            <Link :href="route('draft.detail', { league_id: league.id })">Enter draft room</Link>
                        </Button>
                    </div>
                </template>

                <!-- No draft yet -->
                <template v-else>
                    <div class="space-y-2">
                        <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                            <ClipboardList class="size-3.5 opacity-70" aria-hidden="true" />
                            Draft
                        </p>
                        <h2 class="text-balance text-2xl font-bold tracking-tight sm:text-3xl">No active draft</h2>
                        <p class="max-w-xl text-sm text-muted-foreground sm:text-base">
                            When your league starts a draft, you’ll open it from here. League admins configure the draft from
                            league settings.
                        </p>
                    </div>
                </template>
            </div>
        </div>
    </LeagueDetailLayout>
</template>
