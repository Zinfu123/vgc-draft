<script setup lang="ts">
import LeagueCarousel from '@/components/league/LeagueCarousel.vue';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

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
    draft_date: string;
    set_start_date: string;
    logo: string | null;
    winner: string | null;
    podium?: Podium;
}

interface OpenLeague {
    id: number;
    name: string;
    draft_date: string;
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

interface Props {
    userName: string;
    userStats: UserStats;
    usersActiveLeagues: League[];
    usersPastLeagues: League[];
    openLeagues: OpenLeague[];
}

const props = defineProps<Props>();
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
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
                    <div v-if="props.usersActiveLeagues.length > 0" class="grid grid-cols-[repeat(auto-fill,minmax(18rem,1fr))] gap-4">
                        <LeagueCarousel :leagues="props.usersActiveLeagues" />
                    </div>
                    <p v-else class="text-muted-foreground">You are not currently in any active leagues.</p>
                </section>

                <section>
                    <h2 class="mb-4 text-2xl font-bold">My Past Leagues</h2>
                    <div v-if="props.usersPastLeagues.length > 0" class="grid grid-cols-[repeat(auto-fill,minmax(18rem,1fr))] gap-4">
                        <LeagueCarousel :leagues="props.usersPastLeagues" />
                    </div>
                    <p v-else class="text-muted-foreground">You have no past leagues.</p>
                </section>
            </div>

            <section>
                <h2 class="mb-4 text-2xl font-bold">Open Leagues</h2>
                <div v-if="props.openLeagues.length > 0" class="grid grid-cols-[repeat(auto-fill,minmax(18rem,1fr))] gap-4">
                    <LeagueCarousel :leagues="props.openLeagues" />
                </div>
                <p v-else class="text-muted-foreground">There are no open leagues available to join.</p>
            </section>
        </div>
    </AppLayout>
</template>
