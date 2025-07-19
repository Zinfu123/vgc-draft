<script setup lang="ts">
import { Carousel, CarouselContent, CarouselItem, CarouselNext, CarouselPrevious } from '@/components/ui/carousel';
import { Card, CardContent } from '@/components/ui/card';
import { router } from '@inertiajs/vue3';

interface Leagues {
    id: number;
    name: string;
    draft_date: string;
    set_start_date: string;
    logo: string;
}


interface props {
    leagues: Leagues[];
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
            <CarouselContent class="mt-10 w-[800px]">
                <CarouselItem v-for="league in leagues" :key="league.id" class="md:basis-1/2 lg:basis-1/3" @click="router.get(`/leagues/${league.id}`)">
                <Card class="flex aspect-square items-center hover:bg-black/50 cursor-pointer" :style="{ backgroundImage: `url(${league.logo})`, backgroundSize: 'cover', backgroundPosition: 'center'}" style="transparency">
                <CardHeader>  
                    <CardTitle>
                        {{ league.name}}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div>
                        draft date: {{ league.draft_date }}
                    </div>
                    <div>
                        start date: {{ league.set_start_date }}
                    </div>
                </CardContent>
                </Card>
                </CarouselItem>
                </CarouselContent>
                <CarouselPrevious />
                <CarouselNext />
            </Carousel>
</template>