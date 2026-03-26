<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Sets {
    id: number;
    league_id: number;
    pool_id: number;
}

interface Team1 {
    id: number;
    name: string;
    logo: string;
    user: {
        name: string;
    };
}

interface Team2 {
    id: number;
    name: string;
    logo: string;
    user: {
        name: string;
    };
}

interface props {
    sets: Sets;
    team1: Team1;
    team2: Team2 | null;
}

const props = defineProps<props>();
</script>
<template>
    <Link :href="`/match/set/${props.sets.id}`">
        <div class="flex min-w-0 gap-x-4">
            <img
                class="size-12 flex-none rounded-full bg-gray-50 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                :src="team1.logo"
                alt="team logo"
            />
            <div class="min-w-0 flex-auto">
                <p class="text-sm/6 font-semibold text-gray-900 dark:text-white">
                    <Link :href="`/teams/${team1.id}`" class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        {{ team1.name }}
                    </Link>
                </p>
                <p class="mt-1 flex text-xs/5 text-gray-500 dark:text-gray-400">
                    {{ team1.user.name }}
                </p>
            </div>
        </div>
        <div class="flex items-center justify-center">
            <div class="flex items-center justify-center">
                <p class="text-sm leading-6 font-semibold text-gray-900 dark:text-white">{{ team2 ? 'VS' : 'Bye' }}</p>
            </div>
        </div>
        <div v-if="team2" class="flex min-w-0 gap-x-4">
            <img
                class="size-12 flex-none rounded-full bg-gray-50 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                :src="team2.logo"
                alt="team logo"
            />
            <div class="flex-auto">
                <p class="text-sm leading-6 font-semibold text-gray-900 dark:text-white">
                    <Link :href="`/teams/${team2.id}`" class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        {{ team2.name }}
                    </Link>
                </p>
                <p class="mt-1 flex text-xs/5 text-gray-500 dark:text-gray-400">
                    {{ team2.user.name }}
                </p>
            </div>
        </div>
        <div v-else class="flex min-w-0 flex-1 items-center justify-center gap-x-2 text-sm text-muted-foreground">
            <span class="rounded-md border border-dashed border-border px-3 py-2">Opponent removed — bye</span>
        </div>
    </Link>
</template>
