<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { router } from '@inertiajs/vue3';

interface Leagues {
    id: number;
    name: string;
    draft_date: string;
    set_start_date: string;
    logo: string | null;
    winner: string | null;
}

interface props {
    leagues: Leagues[];
}

const props = defineProps<props>();
</script>

<template>
    <Card
        v-for="league in leagues"
        :key="league.id"
        class="w-64 shrink-0 cursor-pointer overflow-hidden text-center transition-colors hover:bg-accent"
        @click="router.get(`/leagues/${league.id}`)"
    >
        <CardHeader>
            <CardTitle>
                {{ league.name }}
            </CardTitle>
        </CardHeader>
        <CardContent class="flex items-center gap-4">
            <img v-if="league.logo !== null" :src="league.logo" alt="League Logo" class="h-20 w-20 shrink-0 object-contain" />
            <div class="flex flex-col gap-2 text-left">
                <div v-if="league.winner !== null">
                    <p class="text-xs text-muted-foreground">Winner</p>
                    <p class="text-sm font-medium">{{ league.winner }}</p>
                </div>
                <div v-if="league.winner === null">
                    <p class="text-xs text-muted-foreground">Draft Date</p>
                    <p class="text-sm font-medium">{{ league.draft_date }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Start Date</p>
                    <p class="text-sm font-medium">{{ league.set_start_date }}</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
