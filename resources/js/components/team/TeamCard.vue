<script setup lang="ts">
import { Card, CardContent } from "@/components/ui/card";

interface Teams {
    id: number
    name: string;
    logo: string;
    set_wins: number;
    set_losses: number;
    victory_points: number;
}

interface props {
    team: Teams;
}

const props = defineProps<props>();

</script>
<template>
<Card class="flex aspect-square items-center hover:bg-black/50 cursor-pointer">
    <CardContent>
        <div class="flex flex-col items-center text-2xl font-bold">
            {{ team.name}}
        </div>
        <div class="flex flex-col items-center text-2xl font-bold">
            Coach: {{ team.coach }}
        </div>
        <div class="flex flex-col items-center mx-auto" v-if="team.logo !== null">
            <img :src="team.logo" alt="team logo" class="w-45 h-45">
        </div>
        <div class="flex flex-col items-center mx-auto font-bold text-2xl">
            Record:
            <div class="flex flex-row items-center gap-8 text-2xl font-bold">
                <div class="flex flex-row items-center gap-8 text-2xl font-bold text-green-500">
                    {{ team.set_wins }}
                </div>
                <div class="flex flex-row items-center gap-8 text-2xl font-bold">
                    -
                </div>
                <div class="flex flex-row items-center gap-8 text-2xl font-bold text-red-500">
                    {{ team.set_losses }}
                </div>
            </div>
        </div>
        <div class="flex flex-col items-center mx-auto text-2xl font-bold">
            <div class="flex flex-row items-center gap-4">
                Points:
                <div class="flex flex-row items-center text-2xl font-bold text-orange-500">
                    {{ team.victory_points }}
                </div>
            </div>
        </div>
    </CardContent>
    </Card>
</template>