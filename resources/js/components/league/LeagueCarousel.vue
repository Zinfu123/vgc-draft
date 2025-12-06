<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Carousel, CarouselContent, CarouselItem, CarouselNext, CarouselPrevious } from '@/components/ui/carousel';
import { router } from '@inertiajs/vue3';

interface Leagues {
    id: number;
    name: string;
    draft_date: string;
    set_start_date: string;
    logo: string | null;
}

interface props {
    leagues: Leagues[];
}

const props = defineProps<props>();
</script>

<template>
    <Carousel
        class="relative mx-auto w-full max-w-4xl"
        :opts="{
            align: 'start',
        }"
    >
        <CarouselContent class="mt-10 w-[800px]">
            <CarouselItem v-for="league in leagues" :key="league.id" class="md:basis-1/2 lg:basis-1/3" @click="router.get(`/leagues/${league.id}`)">
                <Card class="w-full text-center">
                    <CardHeader>
                        <CardTitle>
                            {{ league.name }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div>Draft Date: {{ league.draft_date }}</div>
                        <div>Start Date: {{ league.set_start_date }}</div>
                        <div v-if="league.logo !== null">
                            <img :src="league.logo" alt="League Logo" class="h-40 w-40" />
                        </div>
                    </CardContent>
                </Card>
            </CarouselItem>
        </CarouselContent>
        <CarouselPrevious />
        <CarouselNext />
    </Carousel>
</template>
