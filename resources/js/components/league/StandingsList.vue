<script setup lang="ts">
import { router } from '@inertiajs/vue3';

interface Standings {
    [key: string]: {
        id: number;
        name: string;
        logo: string;
        user: {
            name: string;
        };
        victory_points: number;
    };
}

interface props {
    standings: Standings[];
}

const props = defineProps<props>();
</script>
<template>
    <div class="w-full">
        <main class="w-full">
            <header class="flex items-center justify-center border-b border-border py-4 text-center sm:py-6">
                <h1 class="text-base/7 font-semibold text-foreground">Standings</h1>
            </header>
            <div class="flex w-full flex-col items-stretch justify-center gap-8 md:flex-row md:flex-wrap md:items-start md:justify-center md:gap-x-5">
                <div v-for="(standing, key) in standings" :key="key" class="min-w-0 w-full max-w-full md:max-w-md md:shrink-0">
                    <div class="flex flex-col items-center justify-center">
                        <span class="mb-2 text-sm font-medium text-muted-foreground">{{ key }}</span>
                        <div class="w-full overflow-hidden rounded-md border border-border bg-card shadow-sm">
                            <ul role="list" class="divide-y divide-border">
                                <li
                                    v-for="team in standing"
                                    :key="team.id"
                                    class="flex min-h-14 cursor-pointer touch-manipulation items-center justify-between gap-x-4 px-3 py-4 transition-colors hover:bg-accent sm:gap-x-6 sm:py-5"
                                    @click="router.get(`/teams/${team.id}`)"
                                >
                                    <div class="flex min-w-0 gap-x-3 sm:gap-x-4">
                                        <img :src="team.logo" :alt="team.name" class="size-11 shrink-0 rounded-full bg-muted sm:size-12" />
                                        <div class="min-w-0 flex-auto">
                                            <p class="text-sm/6 font-semibold text-foreground">{{ team.name }}</p>
                                            <p class="mt-0.5 truncate text-xs/5 text-muted-foreground sm:mt-1">{{ team.user.name }}</p>
                                            <p class="mt-1 text-xs text-muted-foreground sm:hidden">
                                                <span class="font-medium text-foreground">{{ team.victory_points }}</span> pts
                                            </p>
                                        </div>
                                    </div>
                                    <div class="hidden shrink-0 flex-col items-end sm:flex">
                                        <p class="text-sm/6 font-semibold text-foreground">Points</p>
                                        <p class="tabular-nums">{{ team.victory_points }}</p>
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
