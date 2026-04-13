<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import CommissionerSubNav from '@/components/league/CommissionerSubNav.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, useForm } from '@inertiajs/vue3';
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
    trade_deadline_at: string | null;
}

interface Team {
    id: number;
    name: string;
    coach: string;
    user_id: number;
    trades: number;
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
}>();

const tradesForm = useForm({ trades: 0 });

const handleTradesSubmit = () => {
    tradesForm.post(route('leagues.trades.set-team-trades', { league: props.league.id }));
};

function toDatetimeLocalValue(utcStr: string | null | undefined): string {
    if (!utcStr) return '';
    const d = new Date(utcStr);
    if (isNaN(d.getTime())) return '';
    const offset = d.getTimezoneOffset() * 60000;
    return new Date(d.getTime() - offset).toISOString().slice(0, 16);
}

const deadlineForm = useForm({
    trade_deadline_at: toDatetimeLocalValue(props.league.trade_deadline_at),
});

const deadlinePassed = computed(() => {
    if (!props.league.trade_deadline_at) return false;
    return new Date(props.league.trade_deadline_at) <= new Date();
});

const handleDeadlineSubmit = () => {
    deadlineForm
        .transform((data) => ({
            trade_deadline_at: data.trade_deadline_at ? new Date(data.trade_deadline_at).toISOString() : null,
        }))
        .patch(route('leagues.trade-deadline.update', { league: props.league.id }), {
            preserveScroll: true,
        });
};

const clearDeadline = () => {
    deadlineForm.trade_deadline_at = '';
    deadlineForm
        .transform(() => ({ trade_deadline_at: null }))
        .patch(route('leagues.trade-deadline.update', { league: props.league.id }), {
            preserveScroll: true,
        });
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
        <Head :title="`Trade Slots · ${league.name}`" />

        <div class="flex flex-col gap-8">
            <CommissionerSubNav :league="league" />

            <section class="flex flex-col gap-6">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">Trade Slots</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Set the number of trades for all teams. Each trade slot is consumed per Pokémon received in an accepted trade.
                    </p>
                </div>

                <div class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border bg-muted/40 dark:bg-muted/20">
                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Team</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Coach</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">Remaining</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="team in teams" :key="team.id" class="transition-colors hover:bg-muted/20">
                                <td class="px-4 py-2.5 font-medium text-foreground">{{ team.name }}</td>
                                <td class="px-4 py-2.5 text-muted-foreground">{{ team.coach }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <span class="inline-flex size-7 items-center justify-center rounded-md bg-muted text-sm font-semibold tabular-nums">
                                        {{ team.trades ?? 0 }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form class="flex items-end gap-3" @submit.prevent="handleTradesSubmit">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium" for="trades">Set trades for all teams</label>
                        <Input id="trades" v-model="tradesForm.trades" type="number" min="0" class="w-32" />
                        <p v-if="tradesForm.errors.trades" class="text-sm text-destructive">{{ tradesForm.errors.trades }}</p>
                    </div>
                    <Button type="submit" :disabled="tradesForm.processing">Apply to All Teams</Button>
                </form>
            </section>

            <!-- Trade Deadline -->
            <section class="flex flex-col gap-6">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">Trade Deadline</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Once this date and time passes, all pending trades are automatically cancelled and no new trades can be submitted.
                    </p>
                </div>

                <div
                    v-if="deadlinePassed && league.trade_deadline_at"
                    class="flex items-center gap-2 rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive dark:border-destructive/60 dark:bg-destructive/20"
                >
                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    The trade deadline has passed. Trades are locked.
                </div>

                <form class="flex flex-wrap items-end gap-3" @submit.prevent="handleDeadlineSubmit">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium" for="trade_deadline_at">Deadline date &amp; time</label>
                        <Input
                            id="trade_deadline_at"
                            v-model="deadlineForm.trade_deadline_at"
                            type="datetime-local"
                            class="w-56"
                        />
                        <p v-if="deadlineForm.errors.trade_deadline_at" class="text-sm text-destructive">{{ deadlineForm.errors.trade_deadline_at }}</p>
                    </div>
                    <Button type="submit" :disabled="deadlineForm.processing">Save Deadline</Button>
                    <Button
                        v-if="league.trade_deadline_at"
                        type="button"
                        variant="outline"
                        :disabled="deadlineForm.processing"
                        @click="clearDeadline"
                    >
                        Clear Deadline
                    </Button>
                </form>
            </section>
        </div>
    </LeagueDetailLayout>
</template>
