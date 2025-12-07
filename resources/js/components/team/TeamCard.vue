<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';

interface Teams {
    id: number;
    name: string;
    logo: string | null;
    set_wins: number;
    set_losses: number;
    victory_points: number;
    coach: string;
}

interface props {
    team: Teams;
}

const props = defineProps<props>();
</script>
<template>
    <Card class="flex aspect-square w-[300px] cursor-pointer overflow-hidden bg-blue-500/10 hover:bg-blue-600/50">
        <CardContent class="flex h-full w-full flex-col items-center justify-between gap-1 p-3">
            <div class="flex w-full flex-col items-center px-2 text-center text-base font-bold break-words">
                {{ team.name }}
            </div>
            <div class="flex w-full flex-col items-center px-2 text-center text-sm font-bold break-words">Coach: {{ team.coach }}</div>
            <div class="mx-auto flex flex-shrink-0 flex-col items-center" v-if="team.logo">
                <img :src="team.logo" alt="team logo" class="h-16 w-16 rounded-full border-2 border-gray-300 object-cover" />
            </div>
            <div class="mx-auto flex flex-col items-center text-sm font-bold">
                Record:
                <div class="flex flex-row items-center gap-2 text-sm font-bold">
                    <div class="flex flex-row items-center text-sm font-bold text-green-500">
                        {{ team.set_wins }}
                    </div>
                    <div class="flex flex-row items-center text-sm font-bold">-</div>
                    <div class="flex flex-row items-center text-sm font-bold text-red-500">
                        {{ team.set_losses }}
                    </div>
                </div>
            </div>
            <div class="mx-auto flex flex-col items-center text-sm font-bold">
                <div class="flex flex-row items-center gap-2">
                    Points:
                    <div class="flex flex-row items-center text-sm font-bold text-orange-500">
                        {{ team.victory_points }}
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
