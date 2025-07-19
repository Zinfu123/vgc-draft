<script setup lang="ts">
import { Carousel, CarouselContent, CarouselItem, CarouselNext, CarouselPrevious } from '@/components/ui/carousel';
import { router } from '@inertiajs/vue3';
import TeamCard from './TeamCard.vue';

interface Teams {
    id: number
    name: string;
    logo: string;
    set_wins: number;
    set_losses: number;
    victory_points: number;
}

interface props {
    teams: Teams[];
}

const props = defineProps<props>();

</script>

<template>
<Carousel
            class="relative w-full max-w-4xl mx-auto"
            :opts="{
            align: 'start',
            }"
            >
            <CarouselContent class="flex justify-center items-center">
                <CarouselItem v-for="team in teams" :key="team.id" class="md:basis-1/2 lg:basis-1/3 h-[500px] w-[900px] flex justify-center items-center" @click="router.get(`/teams/${team.id}`)">
                <TeamCard :team="team" />
                </CarouselItem>
                </CarouselContent>
                <CarouselPrevious />
                <CarouselNext />
            </Carousel>
</template>