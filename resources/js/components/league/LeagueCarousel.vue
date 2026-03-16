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
        class="w-full cursor-pointer text-center transition-colors hover:bg-accent"
        @click="router.get(`/leagues/${league.id}`)"
    >
        <CardHeader>
            <CardTitle>
                {{ league.name }}
            </CardTitle>
        </CardHeader>
        <CardContent class="flex flex-col items-center justify-center gap-4">
            <div v-if="league.winner !== null">
                <p>Winner</p>
                <p>{{ league.winner }}</p>
            </div>
            <div v-if="league.winner === null">
                <p>Draft Date</p>
                <p>{{ league.draft_date }}</p>
            </div>
            <div>
                <p>Start Date</p>
                <p>{{ league.set_start_date }}</p>
            </div>
            <div v-if="league.logo !== null">
                <img :src="league.logo" alt="League Logo" class="h-40 w-40" />
            </div>
        </CardContent>
    </Card>
</template>
