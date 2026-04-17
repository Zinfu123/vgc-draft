<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import LeagueCarousel from '@/components/league/LeagueCarousel.vue';
import { Card, CardContent } from '@/components/ui/card';
import { isReverbBroadcastClientConfigured } from '@/lib/broadcasting';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useEchoNotification } from '@laravel/echo-vue';
import { AlertTriangle, ChevronDown, ChevronUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface Podium {
    first: string | null;
    second: string | null;
    third: string | null;
}

interface League {
    id: number;
    name: string;
    status: number;
    draft_date: string | null;
    set_start_date: string;
    logo: string | null;
    winner: string | null;
    podium?: Podium;
}

interface OpenLeague {
    id: number;
    name: string;
    draft_date: string | null;
    set_start_date: string;
    logo: string | null;
    winner: null;
    podium?: Podium;
}

interface UserStats {
    gold_medals: number;
    silver_medals: number;
    bronze_medals: number;
    game_wins: number;
    game_losses: number;
    set_wins: number;
    set_losses: number;
    playoff_game_wins: number;
    playoff_game_losses: number;
    playoff_set_wins: number;
    playoff_set_losses: number;
}

function winPct(wins: number, losses: number): string {
    const total = wins + losses;
    if (total === 0) {
        return '—';
    }

    return (wins / total).toLocaleString('en-US', { style: 'percent', minimumFractionDigits: 1 });
}

interface DraftDay {
    league_id: number;
    league_name: string;
    date: string;
}

interface MatchWeekStart {
    league_id: number;
    league_name: string;
    date: string;
}

interface ScheduledMatch {
    set_id: number;
    league_id: number;
    opponent_team_name: string;
    scheduled_at: string;
}

interface CalendarEvents {
    draft_days: DraftDay[];
    match_week_starts: MatchWeekStart[];
    scheduled_matches: ScheduledMatch[];
}

interface Props {
    userName: string;
    userStats: UserStats;
    usersActiveLeagues: League[];
    usersPastLeagues: League[];
    openLeagues: OpenLeague[];
    calendarEvents?: CalendarEvents;
}

const props = defineProps<Props>();

const page = usePage();
const userId = page.props.auth?.user?.id;

const authUser = computed(() => page.props.auth?.user as { id?: number; discord_id?: string | null; discord_avatar_url?: string | null } | undefined);
const needsDiscordReauth = computed(() =>
    !!authUser.value?.discord_id && !authUser.value?.discord_avatar_url,
);

const upcomingEvents = computed(() => {
    if (!props.calendarEvents) {
        return null;
    }
    const now = new Date();
    const cutoff = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);

    const events: Array<{
        type: 'draft' | 'match_week' | 'scheduled_match';
        label: string;
        date: Date;
        set_id?: number;
    }> = [];

    for (const d of props.calendarEvents.draft_days) {
        const date = new Date(d.date);
        if (date >= now && date <= cutoff) {
            events.push({ type: 'draft', label: `${d.league_name} — Draft Day`, date });
        }
    }
    for (const m of props.calendarEvents.match_week_starts) {
        const date = new Date(m.date);
        if (date >= now && date <= cutoff) {
            events.push({ type: 'match_week', label: `${m.league_name} — Match Week Starts`, date });
        }
    }
    for (const s of props.calendarEvents.scheduled_matches) {
        const date = new Date(s.scheduled_at);
        if (date >= now && date <= cutoff) {
            events.push({ type: 'scheduled_match', label: `vs ${s.opponent_team_name}`, date, set_id: s.set_id });
        }
    }

    return events.sort((a, b) => a.date.getTime() - b.date.getTime());
});

const LEAGUES_PER_PAGE = 3;

function sortBySeasonStart<T extends { set_start_date: string }>(leagues: T[]): T[] {
    return [...leagues].sort((a, b) => new Date(a.set_start_date).getTime() - new Date(b.set_start_date).getTime());
}

const sortedActiveLeagues = computed(() => sortBySeasonStart(props.usersActiveLeagues));
const sortedPastLeagues = computed(() => sortBySeasonStart(props.usersPastLeagues));

const activeLeaguesPage = ref(0);
const pastLeaguesPage = ref(0);

const activeLeaguesPageCount = computed(() => Math.ceil(sortedActiveLeagues.value.length / LEAGUES_PER_PAGE));
const pastLeaguesPageCount = computed(() => Math.ceil(sortedPastLeagues.value.length / LEAGUES_PER_PAGE));

const visibleActiveLeagues = computed(() =>
    sortedActiveLeagues.value.slice(activeLeaguesPage.value * LEAGUES_PER_PAGE, (activeLeaguesPage.value + 1) * LEAGUES_PER_PAGE),
);
const visiblePastLeagues = computed(() =>
    sortedPastLeagues.value.slice(pastLeaguesPage.value * LEAGUES_PER_PAGE, (pastLeaguesPage.value + 1) * LEAGUES_PER_PAGE),
);

if (isReverbBroadcastClientConfigured && userId) {
    useEchoNotification(
        `App.Models.User.${userId}`,
        () => {
            router.reload({ only: ['usersActiveLeagues'] });
        },
        'DraftStartedBroadcastNotification',
    );
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <a
            v-if="needsDiscordReauth"
            :href="route('discord.redirect')"
            class="flex items-center justify-center gap-3 bg-red-600 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800"
        >
            <AlertTriangle class="h-5 w-5 shrink-0" />
            <span>Your Discord avatar is missing — click here to re-authenticate with Discord and update your profile.</span>
        </a>
        <div class="flex flex-col gap-8 p-4 pb-10 sm:p-6">
            <section>
                <h2 class="mb-4 text-2xl font-bold">{{ props.userName }}'s Stats</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <Card>
                        <CardContent class="flex flex-col items-center gap-2 pt-6 text-center">
                            <div class="flex items-end gap-4">
                                <div class="flex flex-col items-center gap-1">
                                    <span class="text-2xl font-bold tabular-nums">{{ props.userStats.gold_medals }}</span>
                                    <span class="text-3xl">🥇</span>
                                </div>
                                <div class="flex flex-col items-center gap-1">
                                    <span class="text-2xl font-bold tabular-nums">{{ props.userStats.silver_medals }}</span>
                                    <span class="text-3xl">🥈</span>
                                </div>
                                <div class="flex flex-col items-center gap-1">
                                    <span class="text-2xl font-bold tabular-nums">{{ props.userStats.bronze_medals }}</span>
                                    <span class="text-3xl">🥉</span>
                                </div>
                            </div>
                            <span class="text-sm text-muted-foreground">Medals</span>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="flex flex-col items-center gap-1 pt-6 text-center">
                            <span class="text-4xl font-bold tabular-nums">{{ props.userStats.game_wins }} – {{ props.userStats.game_losses }}</span>
                            <span class="text-sm text-muted-foreground">Game Record</span>
                            <span class="text-xs text-muted-foreground">{{ winPct(props.userStats.game_wins, props.userStats.game_losses) }}</span>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="flex flex-col items-center gap-1 pt-6 text-center">
                            <span class="text-4xl font-bold tabular-nums">{{ props.userStats.set_wins }} – {{ props.userStats.set_losses }}</span>
                            <span class="text-sm text-muted-foreground">Set Record</span>
                            <span class="text-xs text-muted-foreground">{{ winPct(props.userStats.set_wins, props.userStats.set_losses) }}</span>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="flex flex-col items-center gap-1 pt-6 text-center">
                            <span class="text-4xl font-bold tabular-nums">
                                {{ props.userStats.playoff_game_wins }} – {{ props.userStats.playoff_game_losses }}
                            </span>
                            <span class="text-sm text-muted-foreground">Playoff game record</span>
                            <span class="text-xs text-muted-foreground">
                                {{ winPct(props.userStats.playoff_game_wins, props.userStats.playoff_game_losses) }}
                            </span>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="flex flex-col items-center gap-1 pt-6 text-center">
                            <span class="text-4xl font-bold tabular-nums">
                                {{ props.userStats.playoff_set_wins }} – {{ props.userStats.playoff_set_losses }}
                            </span>
                            <span class="text-sm text-muted-foreground">Playoff set record</span>
                            <span class="text-xs text-muted-foreground">
                                {{ winPct(props.userStats.playoff_set_wins, props.userStats.playoff_set_losses) }}
                            </span>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                <section>
                    <h2 class="mb-4 text-2xl font-bold">My Active Leagues</h2>
                    <template v-if="sortedActiveLeagues.length > 0">
                        <div class="grid grid-cols-3 gap-4 *:w-full">
                            <LeagueCarousel :leagues="visibleActiveLeagues" />
                        </div>
                        <div v-if="activeLeaguesPageCount > 1" class="mt-3 flex items-center justify-between">
                            <span class="text-xs text-muted-foreground">{{ activeLeaguesPage + 1 }} / {{ activeLeaguesPageCount }}</span>
                            <div class="flex gap-1">
                                <button
                                    :disabled="activeLeaguesPage === 0"
                                    class="rounded-md border border-border p-1 transition-colors hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40"
                                    aria-label="Previous leagues"
                                    @click="activeLeaguesPage--"
                                >
                                    <ChevronUp class="h-4 w-4" />
                                </button>
                                <button
                                    :disabled="activeLeaguesPage >= activeLeaguesPageCount - 1"
                                    class="rounded-md border border-border p-1 transition-colors hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40"
                                    aria-label="Next leagues"
                                    @click="activeLeaguesPage++"
                                >
                                    <ChevronDown class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </template>
                    <EmptyState v-else message="You are not currently in any active leagues." />
                </section>

                <section>
                    <h2 class="mb-4 text-2xl font-bold">My Past Leagues</h2>
                    <template v-if="sortedPastLeagues.length > 0">
                        <div class="grid grid-cols-3 gap-4 *:w-full">
                            <LeagueCarousel :leagues="visiblePastLeagues" />
                        </div>
                        <div v-if="pastLeaguesPageCount > 1" class="mt-3 flex items-center justify-between">
                            <span class="text-xs text-muted-foreground">{{ pastLeaguesPage + 1 }} / {{ pastLeaguesPageCount }}</span>
                            <div class="flex gap-1">
                                <button
                                    :disabled="pastLeaguesPage === 0"
                                    class="rounded-md border border-border p-1 transition-colors hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40"
                                    aria-label="Previous leagues"
                                    @click="pastLeaguesPage--"
                                >
                                    <ChevronUp class="h-4 w-4" />
                                </button>
                                <button
                                    :disabled="pastLeaguesPage >= pastLeaguesPageCount - 1"
                                    class="rounded-md border border-border p-1 transition-colors hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40"
                                    aria-label="Next leagues"
                                    @click="pastLeaguesPage++"
                                >
                                    <ChevronDown class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </template>
                    <EmptyState v-else message="You have no past leagues." />
                </section>
            </div>

            <section>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-2xl font-bold">Upcoming Events</h2>
                    <Link :href="route('calendar.index')" class="text-primary text-sm font-medium hover:underline">View calendar →</Link>
                </div>
                <div v-if="upcomingEvents === null" class="grid animate-pulse grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="i in 3" :key="i" class="h-16 rounded-lg bg-muted"></div>
                </div>
                <template v-else-if="upcomingEvents.length > 0">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <component
                            :is="event.set_id ? Link : 'div'"
                            v-for="(event, idx) in upcomingEvents"
                            :key="idx"
                            v-bind="event.set_id ? { href: route('sets.show', { set_id: event.set_id }) } : {}"
                            class="border-border bg-card flex items-start gap-3 rounded-lg border p-3 shadow-sm"
                            :class="event.set_id ? 'hover:bg-accent transition-colors cursor-pointer' : ''"
                        >
                            <span
                                class="mt-1 h-3 w-3 shrink-0 rounded-full"
                                :class="{
                                    'bg-purple-500': event.type === 'draft',
                                    'bg-blue-500': event.type === 'match_week',
                                    'bg-green-500': event.type === 'scheduled_match',
                                }"
                            ></span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-foreground truncate">{{ event.label }}</p>
                                <p class="text-muted-foreground mt-0.5 text-xs">
                                    {{
                                        event.date.toLocaleString(undefined, {
                                            weekday: 'short',
                                            month: 'short',
                                            day: 'numeric',
                                            hour: event.type === 'scheduled_match' ? 'numeric' : undefined,
                                            minute: event.type === 'scheduled_match' ? '2-digit' : undefined,
                                        })
                                    }}
                                </p>
                            </div>
                        </component>
                    </div>
                </template>
                <EmptyState v-else message="No events in the next 7 days." />
            </section>

            <section>
                <h2 class="mb-4 text-2xl font-bold">Open Leagues</h2>
                <div v-if="props.openLeagues.length > 0" class="grid grid-cols-[repeat(auto-fill,minmax(18rem,1fr))] gap-4">
                    <LeagueCarousel :leagues="props.openLeagues" />
                </div>
                <EmptyState v-else message="There are no open leagues available to join." />
            </section>
        </div>
    </AppLayout>
</template>
