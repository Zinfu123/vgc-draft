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
    [key: number]: {
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
    };
}

interface UpcomingSets {
    [key: number]: {
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
    };
}

interface props {
    team_next: TeamNext;
    played_sets: PlayedSets;
    upcoming_sets: UpcomingSets;
}

const props = defineProps<props>();
</script>
<template>
    <div class="flex min-h-full flex-col">
        <!-- 3 column wrapper -->
        <div class="mx-auto w-full max-w-6xl grow lg:flex xl:px-2">
            <!-- Left sidebar & main wrapper -->
            <div class="w-full flex-1 xl:flex">
                <div
                    class="border-b border-gray-200 px-4 py-6 sm:px-6 lg:pl-8 xl:w-64 xl:shrink-0 xl:border-r xl:border-b-0 xl:pl-4 dark:border-white/10"
                >
                    <!-- Left column area -->
                    <h1 class="text-2xl font-bold">Your Next Set</h1>
                    <div
                        v-if="props.team_next"
                        class="overflow-hidden rounded-md bg-white shadow-sm dark:bg-gray-800/50 dark:shadow-none dark:outline dark:-outline-offset-0 dark:outline-white/10"
                    >
                        <ul
                            v-if="props.team_next"
                            role="list"
                            class="divide-y divide-gray-100 overflow-hidden bg-white shadow-xs outline-1 outline-gray-900/5 sm:rounded-xl dark:divide-white/5 dark:bg-gray-800/50 dark:shadow-none dark:outline-white/10 dark:sm:-outline-offset-1"
                        >
                            <li class="relative flex justify-between gap-x-6 px-4 py-5 hover:bg-gray-50 sm:px-6 dark:hover:bg-white/2.5">
                                <MatchCard :sets="props.team_next" :team1="props.team_next.team1" :team2="props.team_next.team2" />
                            </li>
                        </ul>
                    </div>
                </div>
                <div
                    class="shrink-0 border-t border-gray-200 px-4 py-6 sm:px-6 lg:w-96 lg:border-t-0 lg:border-l lg:pr-8 xl:pr-6 dark:border-white/10"
                >
                    <!-- middle column area -->
                    <template v-if="props.upcoming_sets">
                        <h1 class="text-2xl font-bold">Sets To Be Played</h1>
                        <ScrollArea class="h-96 w-full">
                            <ListWithHeadings :set="props.upcoming_sets" />
                        </ScrollArea>
                    </template>
                </div>
            </div>
            <div class="shrink-0 border-t border-gray-200 px-4 py-6 sm:px-6 lg:w-96 lg:border-t-0 lg:border-l lg:pr-8 xl:pr-6 dark:border-white/10">
                <!-- Right column area -->
                <template v-if="props.played_sets">
                    <h1 class="text-2xl font-bold">Played Sets</h1>
                    <ScrollArea class="h-96 w-full">
                        <ListWithHeadings :set="props.played_sets" />
                    </ScrollArea>
                </template>
            </div>
        </div>
    </div>
</template>
