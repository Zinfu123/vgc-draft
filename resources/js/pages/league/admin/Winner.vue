<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import CommissionerSubNav from '@/components/league/CommissionerSubNav.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import { Button } from '@/components/ui/button';
import { Head, useForm } from '@inertiajs/vue3';
import { Trophy } from 'lucide-vue-next';
import { computed } from 'vue';

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
    name: string;
    coach: string;
    user_id: number;
}

interface StandingTeam {
    id: number;
    name: string;
    user_id: number;
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

// League statuses
const LEAGUE_STATUS_REGULAR_SEASON = 4;
const LEAGUE_STATUS_COMPLETED = 1;

const props = defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    standings: StandingTeam[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
}>();

const topTeam = computed(() => props.standings[0] ?? null);

const isRegularSeason = computed(() => props.league.status === LEAGUE_STATUS_REGULAR_SEASON);
const isCompleted = computed(() => props.league.status === LEAGUE_STATUS_COMPLETED);

const finalizeForm = useForm({});
const handleFinalize = () => {
    finalizeForm.post(route('leagues.finalize', { league: props.league.id }));
};
</script>

<template>
    <LeagueDetailLayout
        :league="league"
        section="commissioner"
        :teams="teams"
        :draft="draft"
        :adminFlag="adminFlag"
        :matchConfig="matchConfig"
    >
        <Head :title="`Finalize League · ${league.name}`" />

        <div class="flex flex-col gap-8">
            <CommissionerSubNav :league="league" />

            <!-- League already completed -->
            <section v-if="isCompleted" class="flex flex-col gap-6">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">League Completed</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">This league has been finalized.</p>
                </div>
                <div class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <Trophy class="size-4 shrink-0" />
                    <span>The league has been completed and results are recorded.</span>
                </div>
            </section>

            <!-- Playoffs enabled — winner auto-set when playoffs close -->
            <section v-else-if="league.playoffs_enabled" class="flex flex-col gap-6">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">Finalize League</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        This league uses playoffs to determine the champion.
                    </p>
                </div>
                <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                    The league winner will be automatically set when playoffs are closed via the
                    <strong>Playoffs</strong> admin panel. No action required here.
                </div>
            </section>

            <!-- Regular season, no playoffs — commissioner can finalize -->
            <section v-else-if="isRegularSeason" class="flex flex-col gap-6">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">Finalize Regular Season</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        End the regular season and immortalise the standings. The top team by victory points becomes the champion.
                    </p>
                </div>

                <div v-if="topTeam" class="flex items-center gap-4 rounded-lg border border-border bg-muted/30 px-4 py-3">
                    <Trophy class="size-5 shrink-0 text-amber-500" />
                    <div>
                        <p class="text-sm font-medium">Champion will be: <span class="font-semibold">{{ topTeam.name }}</span></p>
                        <p class="text-xs text-muted-foreground">
                            {{ topTeam.set_wins }}W – {{ topTeam.set_losses }}L · {{ topTeam.victory_points }} VP
                        </p>
                    </div>
                </div>

                <div v-if="standings.length > 1" class="overflow-hidden rounded-lg border border-border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border bg-muted/50">
                                <th class="px-4 py-2 text-left font-medium text-muted-foreground">Rank</th>
                                <th class="px-4 py-2 text-left font-medium text-muted-foreground">Team</th>
                                <th class="px-4 py-2 text-right font-medium text-muted-foreground">W</th>
                                <th class="px-4 py-2 text-right font-medium text-muted-foreground">L</th>
                                <th class="px-4 py-2 text-right font-medium text-muted-foreground">VP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(team, index) in standings"
                                :key="team.id"
                                class="border-b border-border last:border-0"
                                :class="index === 0 ? 'bg-amber-50/50 dark:bg-amber-950/20' : ''"
                            >
                                <td class="px-4 py-2 text-muted-foreground">{{ index + 1 }}</td>
                                <td class="px-4 py-2 font-medium">{{ team.name }}</td>
                                <td class="px-4 py-2 text-right">{{ team.set_wins }}</td>
                                <td class="px-4 py-2 text-right">{{ team.set_losses }}</td>
                                <td class="px-4 py-2 text-right font-semibold">{{ team.victory_points }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                    This action is permanent. Make sure all matches have been submitted before finalizing.
                </div>

                <div class="flex pt-2">
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="finalizeForm.processing || !topTeam"
                        @click="handleFinalize"
                    >
                        Finalize Regular Season
                    </Button>
                </div>
            </section>

            <!-- Not yet in regular season -->
            <section v-else class="flex flex-col gap-6">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">Finalize League</h2>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                    Finalization will be available once the league is in the Regular Season phase.
                </div>
            </section>
        </div>
    </LeagueDetailLayout>
</template>
