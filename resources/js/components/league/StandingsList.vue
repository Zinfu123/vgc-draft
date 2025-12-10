<script setup lang="ts">
import { router } from '@inertiajs/vue3';

interface Standings {
    [key: number]: {
        id: number;
        name: string;
        logo: string;
        user: {
            name: string;
        };
        victory_points: number;
    };
};

interface props {
    standings: Standings[];
}

const props = defineProps<props>();
</script>
<template>
    <div>
        <main class="w-full">
            <header
                class="flex items-center justify-center border-b border-gray-200 px-4 py-4 text-center sm:px-6 sm:py-6 lg:px-8 dark:border-white/5"
            >
                <h1 class="text-base/7 font-semibold text-gray-900 dark:text-white">Standings</h1>
            </header>
            <div class="flex flex-row items-center justify-center gap-x-5">
                <div v-for="(standing, key) in standings" :key="key">
                    <div class="flex flex-col items-center justify-center">
                        Pool: {{ key }}
                        <div
                            class="overflow-hidden rounded-md bg-white shadow-sm dark:bg-gray-800/50 dark:shadow-none dark:outline dark:-outline-offset-0 dark:outline-white/10"
                        >
                            <ul role="list" class="divide-y divide-gray-200 dark:divide-white/10">
                                <li
                                    v-for="team in standing"
                                    :key="team.id"
                                    class="flex cursor-pointer justify-between gap-x-6 px-3 py-5 hover:bg-gray-100 dark:hover:bg-gray-800/50"
                                    @click="router.get(`/teams/${team.id}`)"
                                >
                                    <div class="flex min-w-0 gap-x-4">
                                        <img
                                            :src="team.logo"
                                            :alt="team.name"
                                            class="size-12 flex-none rounded-full bg-gray-50 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                                        />
                                        <div class="min-w-0 flex-auto">
                                            <p class="text-sm/6 font-semibold text-gray-900 dark:text-white">{{ team.name }}</p>
                                            <p class="mt-1 truncate text-xs/5 text-gray-500 dark:text-gray-400">{{ team.user.name }}</p>
                                        </div>
                                    </div>
                                    <div class="hidden shrink-0 sm:flex sm:flex-col sm:items-end">
                                        <div class="min-w-0 flex-auto">
                                            <p class="text-sm/6 font-semibold text-gray-900 dark:text-white">Points</p>
                                            <p>{{ team.victory_points }}</p>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
