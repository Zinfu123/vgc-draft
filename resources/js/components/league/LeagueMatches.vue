<script setup lang="ts">
import MatchCard from '@/components/match/MatchCard.vue';

interface TeamNext {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1: {
        id: number;
        name: string;
        logo: string;
        coach: {
            name: string;
        }
    }
    team2: {
        id: number;
        name: string;
        logo: string;
        coach: {
            name: string;
        }
    }
}

interface Sets {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1: object;
    team2: object;
}

interface props {
    team_next: TeamNext;
}

const props = defineProps<props>();

</script>
<template>
    <div class = "flex min-h-full flex-col">
<!-- 3 column wrapper -->
    <div class="mx-auto w-full max-w-7xl grow lg:flex xl:px-2">
<!-- Left sidebar & main wrapper -->
<div class="flex-1 xl:flex">
    <div class="border-b border-gray-200 px-4 py-6 sm:px-6 lg:pl-8 xl:w-64 xl:shrink-0 xl:border-r xl:border-b-0 xl:pl-6 dark:border-white/10">
          <!-- Left column area -->
          <h1 class="text-2xl font-bold">Your Next Match</h1>
          <ul>
            <li v-if="props.team_next">
                <MatchCard :sets="props.team_next" :team1="props.team_next.team1" :team2="props.team_next.team2"/>
            </li>
            </ul>
    </div>
    <div class="px-4 py-6 sm:px-6 lg:pl-8 xl:flex-1 xl:pl-6">
          <!-- Main area -->
          <h1 class="text-2xl font-bold">Upcoming Matches</h1>
        </div>
</div>

<div class="shrink-0 border-t border-gray-200 px-4 py-6 sm:px-6 lg:w-96 lg:border-t-0 lg:border-l lg:pr-8 xl:pr-6 dark:border-white/10">
        <!-- Right column area -->
        <h1 class="text-2xl font-bold">Previous Matches</h1>
      </div>
    </div>
    </div> 
  </template>
