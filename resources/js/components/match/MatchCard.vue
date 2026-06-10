<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';

interface Sets {
    id: number;
    league_id: number;
    pool_id: number;
}

interface TeamSide {
    id: number;
    name: string;
    logo: string;
    user: {
        name: string;
    };
}

interface props {
    sets: Sets;
    team1: TeamSide;
    team2: TeamSide | null;
}

const props = defineProps<props>();
</script>

<template>
    <div
        class="relative flex min-w-0 cursor-pointer gap-x-4 rounded-lg border border-border bg-card p-3 shadow-sm transition-colors hover:bg-accent"
        @click="router.visit(`/match/set/${props.sets.id}`)"
    >
        <!-- Invisible full-card link for keyboard / assistive tech -->
        <Link
            :href="`/match/set/${props.sets.id}`"
            class="absolute inset-0 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            aria-label="`${team1.name} vs ${team2 ? team2.name : 'Bye'}`"
            tabindex="0"
            @click.stop
        />

        <!-- Team 1 -->
        <div class="relative z-10 flex min-w-0 flex-1 gap-x-3">
            <img
                class="size-12 flex-none rounded-full bg-muted object-cover ring-1 ring-border"
                :src="team1.logo"
                :alt="`${team1.name} logo`"
            />
            <div class="min-w-0 flex-auto">
                <p class="text-sm font-semibold text-foreground">
                    <Link
                        :href="`/teams/${team1.id}`"
                        class="text-muted-foreground hover:text-foreground transition-colors"
                        @click.stop
                    >
                        {{ team1.name }}
                    </Link>
                </p>
                <p class="mt-1 text-xs text-muted-foreground">{{ team1.user.name }}</p>
            </div>
        </div>

        <!-- VS / Bye -->
        <div class="relative z-10 flex shrink-0 items-center justify-center px-2">
            <p class="text-sm font-semibold text-foreground">{{ team2 ? 'VS' : 'Bye' }}</p>
        </div>

        <!-- Team 2 -->
        <div v-if="team2" class="relative z-10 flex min-w-0 flex-1 gap-x-3">
            <img
                class="size-12 flex-none rounded-full bg-muted object-cover ring-1 ring-border"
                :src="team2.logo"
                :alt="`${team2.name} logo`"
            />
            <div class="flex-auto">
                <p class="text-sm font-semibold text-foreground">
                    <Link
                        :href="`/teams/${team2.id}`"
                        class="text-muted-foreground hover:text-foreground transition-colors"
                        @click.stop
                    >
                        {{ team2.name }}
                    </Link>
                </p>
                <p class="mt-1 text-xs text-muted-foreground">{{ team2.user.name }}</p>
            </div>
        </div>
        <div v-else class="relative z-10 flex min-w-0 flex-1 items-center justify-center">
            <span class="rounded-md border border-dashed border-border px-3 py-2 text-sm text-muted-foreground">Opponent removed — bye</span>
        </div>
    </div>
</template>
