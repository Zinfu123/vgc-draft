<script setup lang="ts">
import ListWithHeadings from '@/components/ListWithHeadings.vue';
import MatchCard from '@/components/match/MatchCard.vue';
import { ScrollArea } from '@/components/ui/scroll-area';

interface TeamNext {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1: {
        id: number;
        name: string;
        logo: string;
        user: {
            name: string;
        };
    };
    team2: {
        id: number;
        name: string;
        logo: string;
        user: {
            name: string;
        };
    };
}

interface PlayedSets {
    [key: number]: Array<{
        id: number;
        league_id: number;
        pool_id: number;
        round: number;
        team1: {
            id: number;
            name: string;
            logo: string;
            user: {
                name: string;
            };
        };
        team2: {
            id: number;
            name: string;
            logo: string;
            user: {
                name: string;
            };
        };
    }>;
}

interface UpcomingSets {
    [key: number]: Array<{
        id: number;
        league_id: number;
        pool_id: number;
        round: number;
        team1: {
            id: number;
            name: string;
            logo: string;
            user: {
                name: string;
            };
        };
        team2: {
            id: number;
            name: string;
            logo: string;
            user: {
                name: string;
            };
        };
    }>;
}

interface props {
    team_next: TeamNext | null;
    played_sets: PlayedSets;
    upcoming_sets: UpcomingSets;
}

const props = defineProps<props>();
</script>
<template>
    <div class="flex min-h-full w-full flex-col">
        <!-- 3 column wrapper -->
        <div class="mx-auto w-full max-w-6xl grow lg:flex">
            <!-- Left sidebar & main wrapper -->
            <div class="min-h-0 w-full flex-1 xl:flex">
                <div class="border-b border-border px-4 py-6 sm:px-6 xl:w-64 xl:shrink-0 xl:border-r xl:border-b-0 xl:px-6">
                    <!-- Left column area -->
                    <h1 class="text-center text-2xl font-bold">Your Next Set</h1>
                    <template v-if="props.team_next">
                        <div class="overflow-hidden rounded-md border border-border bg-card shadow-sm">
                            <ul role="list" class="divide-y divide-border overflow-hidden bg-card sm:rounded-xl">
                                <li class="relative flex justify-between gap-x-6 px-4 py-5 transition-colors hover:bg-accent sm:px-6">
                                    <MatchCard :sets="props.team_next" :team1="props.team_next.team1" :team2="props.team_next.team2" />
                                </li>
                            </ul>
                        </div>
                    </template>
                    <template v-else>
                        <div class="overflow-hidden rounded-md border border-border bg-card shadow-sm">
                            <div class="px-4 py-5 text-center text-sm text-muted-foreground">No upcoming set scheduled</div>
                        </div>
                    </template>
                </div>
                <div class="flex shrink-0 flex-col border-t border-border px-4 py-6 sm:px-6 lg:w-96 lg:border-t-0 lg:border-l lg:px-6">
                    <!-- middle column area -->
                    <template v-if="props.upcoming_sets">
                        <h1 class="mb-4 text-center text-2xl font-bold">Sets To Be Played</h1>
                        <div class="min-h-0 flex-1">
                            <ScrollArea class="h-full w-full">
                                <ListWithHeadings :set="props.upcoming_sets" />
                            </ScrollArea>
                        </div>
                    </template>
                </div>
            </div>
            <div class="flex shrink-0 flex-col border-t border-border px-4 py-6 sm:px-6 lg:w-96 lg:border-t-0 lg:border-l lg:px-6">
                <!-- Right column area -->
                <template v-if="props.played_sets">
                    <h1 class="mb-4 text-center text-2xl font-bold">Played Sets</h1>
                    <div class="min-h-0 flex-1">
                        <ScrollArea class="h-full w-full">
                            <ListWithHeadings :set="props.played_sets" />
                        </ScrollArea>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
