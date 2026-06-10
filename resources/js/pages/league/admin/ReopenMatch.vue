<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import CommissionerSubNav from '@/components/league/CommissionerSubNav.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import { Button } from '@/components/ui/button';
import { type AppPageProps } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
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

const page = usePage<AppPageProps>();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const reopenForm = useForm({ match_reference: '' });

const handleSubmit = () => {
    reopenForm.post(route('leagues.admin.reopen-match.store', { league: props.league.id }), {
        preserveScroll: true,
        onSuccess: () => reopenForm.reset('match_reference'),
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
        <Head :title="`Reopen Match · ${league.name}`" />

        <div class="flex flex-col gap-8">
            <CommissionerSubNav :league="league" />

            <section class="flex flex-col gap-6">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">Reopen a Match</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Paste a full match URL or the numeric set ID to reverse the result, restore standings, and let coaches submit again.
                        Only completed matches in this league can be reopened.
                    </p>
                </div>

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

                <form class="flex max-w-xl flex-col gap-4" @submit.prevent="handleSubmit">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium" for="match_reference">Match link or set ID</label>
                        <textarea
                            id="match_reference"
                            v-model="reopenForm.match_reference"
                            rows="3"
                            placeholder="e.g. https://yoursite.test/match/set/12 or 12"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring dark:bg-background"
                        />
                        <p v-if="reopenForm.errors.match_reference" class="text-sm text-destructive">{{ reopenForm.errors.match_reference }}</p>
                        <p v-if="reopenForm.errors.set_id" class="text-sm text-destructive">{{ reopenForm.errors.set_id }}</p>
                    </div>
                    <div class="flex pt-2">
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="reopenForm.processing || reopenForm.match_reference.trim() === ''"
                        >
                            Reopen Match
                        </Button>
                    </div>
                </form>
            </section>
        </div>
    </LeagueDetailLayout>
</template>
