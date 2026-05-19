<script setup lang="ts">
import MatchPrepMatchRow, { type MatchRow } from '@/components/match-prep/MatchPrepMatchRow.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItemType } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

interface LeagueOption {
    id: number;
    name: string;
    status: number;
    team_id: number;
}

interface Props {
    leagues: LeagueOption[];
    selected_league_id: number;
    team_id: number | null;
    matches: MatchRow[];
}

const props = defineProps<Props>();

const selectedLeague = computed<LeagueOption | null>(
    () => props.leagues.find((lg) => lg.id === props.selected_league_id) ?? null,
);

const breadcrumbs = computed<BreadcrumbItemType[]>(() => {
    if (selectedLeague.value) {
        return [
            { title: 'Leagues', href: '/leagues' },
            { title: selectedLeague.value.name, href: route('leagues.dashboard', { league: selectedLeague.value.id }) },
            { title: 'Match prep', href: route('match-prep.index', { league_id: selectedLeague.value.id }) },
        ];
    }

    return [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Match prep', href: '/match-prep' },
    ];
});

function onLeagueChange(event: Event): void {
    const el = event.target as HTMLSelectElement;
    router.get(
        route('match-prep.index'),
        { league_id: el.value },
        { preserveState: false, preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Match prep" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 pb-10 sm:p-6">
            <div class="flex flex-col gap-2">
                <Link
                    v-if="selectedLeague"
                    :href="route('leagues.dashboard', { league: selectedLeague.id })"
                    class="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-sm font-medium transition-colors"
                >
                    <ArrowLeft class="size-4" />
                    Back to {{ selectedLeague.name }}
                </Link>
                <h1 class="text-2xl font-bold tracking-tight">Match prep</h1>
                <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                    Plan brings and gameplans against each opponent’s drafted roster. Opponent match teamsheets (pokepaste) are never shown here.
                </p>
            </div>

            <Card v-if="props.leagues.length > 0">
                <CardContent class="flex flex-wrap items-end gap-4 pt-6">
                    <div class="flex min-w-[12rem] flex-1 flex-col gap-2">
                        <Label for="league_id">League</Label>
                        <select
                            id="league_id"
                            class="border-input bg-background h-11 min-h-11 w-full max-w-md rounded-md border px-3 text-base shadow-xs md:h-9 md:min-h-9 md:text-sm"
                            :value="String(props.selected_league_id)"
                            @change="onLeagueChange"
                        >
                            <option v-for="lg in props.leagues" :key="lg.id" :value="String(lg.id)">
                                {{ lg.name }}
                                <template v-if="lg.status === 0"> (past) </template>
                            </option>
                        </select>
                    </div>
                </CardContent>
            </Card>

            <div v-if="props.leagues.length === 0" class="text-muted-foreground text-sm">
                Join a league to use match prep notes.
            </div>

            <div v-else-if="props.team_id === null" class="text-muted-foreground text-sm">Could not find your team in this league.</div>

            <div v-else-if="props.matches.length === 0" class="text-muted-foreground text-sm">No scheduled matches in this league yet.</div>

            <div v-else class="flex flex-col gap-8">
                <MatchPrepMatchRow
                    v-for="m in props.matches"
                    :key="`${m.set.id}-${m.note.id ?? 'new'}-${m.note.share_uuid ?? ''}`"
                    :row="m"
                />
            </div>
        </div>
    </AppLayout>
</template>
