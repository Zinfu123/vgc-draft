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
            <div class="flex flex-row items-center justify-center gap-x-5">
                <div v-for="(standing, key) in standings" :key="key">
                    <div class="flex flex-col items-center justify-center">
                        Pool: {{ key }}
                        <div class="overflow-hidden rounded-md border border-border bg-card shadow-sm">
                            <ul role="list" class="divide-y divide-border">
                                <li
                                    v-for="team in standing"
                                    :key="team.id"
                                    class="flex cursor-pointer justify-between gap-x-6 px-3 py-5 transition-colors hover:bg-accent"
                                    @click="router.get(`/teams/${team.id}`)"
                                >
                                    <div class="flex min-w-0 gap-x-4">
                                        <img :src="team.logo" :alt="team.name" class="size-12 flex-none rounded-full bg-muted" />
                                        <div class="min-w-0 flex-auto">
                                            <p class="text-sm/6 font-semibold text-foreground">{{ team.name }}</p>
                                            <p class="mt-1 truncate text-xs/5 text-muted-foreground">{{ team.user.name }}</p>
                                        </div>
                                    </div>
                                    <div class="hidden shrink-0 sm:flex sm:flex-col sm:items-end">
                                        <div class="min-w-0 flex-auto">
                                            <p class="text-sm/6 font-semibold text-foreground">Points</p>
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
