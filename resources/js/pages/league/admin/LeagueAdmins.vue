<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import CommissionerSubNav from '@/components/league/CommissionerSubNav.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface FlashProps {
    flash?: { success?: string | null };
}

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
    admin_flag: number;
    trades: number;
    pick_position: number | null;
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

const props = defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
    isLeagueOwner: boolean;
    isLeagueAdmin: boolean;
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props as FlashProps).flash?.success ?? null);

const processingTeamId = ref<number | null>(null);
const dropDialogOpen = ref(false);
const teamToDrop = ref<Team | null>(null);
const dropForm = useForm({ team_id: 0 });

function openDropDialog(team: Team): void {
    if (!props.isLeagueAdmin) return;
    teamToDrop.value = team;
    dropDialogOpen.value = true;
}

function confirmDropTeam(): void {
    if (!teamToDrop.value) return;
    dropForm.team_id = teamToDrop.value.id;
    dropForm.post(route('leagues.admin.drop-team', { league: props.league.id }), {
        preserveScroll: true,
        onSuccess: () => {
            dropDialogOpen.value = false;
            teamToDrop.value = null;
        },
        onFinish: () => dropForm.reset(),
    });
}

function setTeamAdmin(team: Team, checked: boolean): void {
    if (!props.isLeagueOwner) return;
    processingTeamId.value = team.id;
    router.patch(
        route('leagues.admin.team-admin.update', { league: props.league.id }),
        { team_id: team.id, admin_flag: checked },
        { preserveScroll: true, onFinish: () => (processingTeamId.value = null) },
    );
}

const roleLabel = (team: Team): string => {
    if ((team.user_id && team.user_id === props.league.league_owner) || team.admin_flag === 2) return 'Commissioner';
    if (team.admin_flag === 1) return 'Co-admin';
    return 'Coach';
};

const roleBadgeClass = (team: Team): string => {
    const label = roleLabel(team);
    if (label === 'Commissioner') return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
    if (label === 'Co-admin') return 'bg-primary/10 text-primary dark:bg-primary/20';
    return 'bg-muted text-muted-foreground';
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
        <Head :title="`Commissioner · ${league.name}`" />

        <div class="flex flex-col gap-8">
            <CommissionerSubNav :league="league" />

            <!-- Flash -->
            <div
                v-if="flashSuccess"
                class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950/40 dark:text-green-300"
                role="status"
            >
                <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                {{ flashSuccess }}
            </div>

            <!-- ─── User Management ─── -->
            <section class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
                        <div>
                            <h2 class="text-xl font-semibold">User Management</h2>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                Grant co-admin access to coaches so they can use admin settings and tools.
                            </p>
                        </div>
                        <p v-if="!isLeagueOwner" class="text-xs text-muted-foreground">
                            Only the league owner can change roles.
                        </p>
                    </div>

                    <div v-if="teams.length === 0" class="rounded-lg border border-dashed border-border bg-muted/20 p-10 text-center">
                        <p class="text-sm text-muted-foreground">No teams in this league yet.</p>
                    </div>

                    <div v-else class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border bg-muted/40 dark:bg-muted/20">
                                    <th class="w-10 px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        No.
                                    </th>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        Coach
                                    </th>
                                    <th class="hidden px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground sm:table-cell">
                                        Team
                                    </th>
                                    <th class="hidden px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-muted-foreground md:table-cell">
                                        Trade Pts.
                                    </th>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        Role
                                    </th>
                                    <th class="w-16 px-3 py-2.5" />
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="(team, index) in teams"
                                    :key="team.id"
                                    class="transition-colors hover:bg-muted/20"
                                >
                                    <!-- No. -->
                                    <td class="px-3 py-3 text-center text-xs font-mono text-muted-foreground">
                                        {{ index + 1 }}
                                    </td>

                                    <!-- Coach / Team (stacked on mobile) -->
                                    <td class="px-3 py-3">
                                        <p class="font-medium text-foreground">{{ team.coach }}</p>
                                        <p class="mt-0.5 text-xs text-muted-foreground sm:hidden">{{ team.name }}</p>
                                    </td>

                                    <!-- Team -->
                                    <td class="hidden px-3 py-3 sm:table-cell">
                                        <p class="text-foreground">{{ team.name }}</p>
                                    </td>

                                    <!-- Trade Pts. -->
                                    <td class="hidden px-3 py-3 text-center md:table-cell">
                                        <span class="inline-flex size-7 items-center justify-center rounded-md bg-muted text-sm font-semibold tabular-nums text-foreground">
                                            {{ team.trades ?? 0 }}
                                        </span>
                                    </td>

                                    <!-- Role -->
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-2">
                                            <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', roleBadgeClass(team)]">
                                                {{ roleLabel(team) }}
                                            </span>
                                            <div v-if="isLeagueOwner && roleLabel(team) !== 'Commissioner'" class="flex items-center gap-1.5">
                                                <Checkbox
                                                    :id="`admin-${team.id}`"
                                                    :checked="team.admin_flag === 1"
                                                    :disabled="processingTeamId === team.id"
                                                    @update:checked="setTeamAdmin(team, $event)"
                                                />
                                                <label
                                                    :for="`admin-${team.id}`"
                                                    class="hidden cursor-pointer select-none text-xs text-muted-foreground lg:block"
                                                    :class="{ 'opacity-50': processingTeamId === team.id }"
                                                >
                                                    Co-admin
                                                </label>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Drop -->
                                    <td class="px-3 py-3 text-right">
                                        <button
                                            v-if="isLeagueAdmin"
                                            type="button"
                                            :disabled="dropForm.processing"
                                            class="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground/50 transition-colors hover:bg-destructive/10 hover:text-destructive disabled:pointer-events-none disabled:opacity-50"
                                            :title="`Drop ${team.name}`"
                                            @click="openDropDialog(team)"
                                        >
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                            </svg>
                                            <span class="sr-only">Drop {{ team.name }}</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
            </section>
        </div>

        <!-- Drop team dialog -->
        <Dialog v-model:open="dropDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Drop team from league?</DialogTitle>
                    <DialogDescription>
                        <template v-if="teamToDrop">
                            Removing <strong class="text-foreground">{{ teamToDrop.name }}</strong> will return their Pokémon to
                            the pool, convert their scheduled sets to byes, and may reset playoffs. This cannot be undone.
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" @click="dropDialogOpen = false">Cancel</Button>
                    <Button type="button" variant="destructive" :disabled="dropForm.processing" @click="confirmDropTeam">
                        Drop team
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </LeagueDetailLayout>
</template>
