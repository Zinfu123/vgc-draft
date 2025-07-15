<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';


interface Pokemon {
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
}

interface props {
    pokemon: Pokemon;
}

const props = defineProps<props>();

const type = 'bg-' + props.pokemon.type1.toLowerCase() + 'type';

console.log(type);

</script>
<template>
    <!-- Change Card Background Color -->
    <Card :class="['base-class', 'bg-normaltype', 
    { 
        'bg-rocktype': props.pokemon.type1 === 'Rock', 
        'bg-watertype': props.pokemon.type1 === 'Water', 
        'bg-firetype': props.pokemon.type1 === 'Fire', 
        'bg-grasstype': props.pokemon.type1 === 'Grass', 
        'bg-electrictype': props.pokemon.type1 === 'Electric', 
        'bg-poisontype': props.pokemon.type1 === 'Poison', 
        'bg-groundtype': props.pokemon.type1 === 'Ground', 
        'bg-psychictype': props.pokemon.type1 === 'Psychic', 
        'bg-icetype': props.pokemon.type1 === 'Ice', 
        'bg-dragontype': props.pokemon.type1 === 'Dragon', 
        'bg-fairytype': props.pokemon.type1 === 'Fairy', 
        'bg-darktype': props.pokemon.type1 === 'Dark', 
        'bg-steeltype': props.pokemon.type1 === 'Steel', 
        'bg-flyingtype': props.pokemon.type1 === 'Flying', 
        'bg-fightingtype': props.pokemon.type1 === 'Fighting', 
        'bg-bugtype': props.pokemon.type1 === 'Bug', 
        'bg-ghosttype': props.pokemon.type1 === 'Ghost',  }]">
        <CardHeader>
            <CardTitle :class="`text-center text-2xl font-bold capitalize`">
                {{ props.pokemon.name }}
            </CardTitle>
        </CardHeader>
        <CardContent class="flex flex-col items-center justify-center relative">
            <div class="z-10">
            </div>
            <div class="z-20 flex items-center justify-center">
            <img :src="props.pokemon.sprite_url" :alt="props.pokemon.name" />
                <span class="rounded bg-muted px-2 py-1 text-xs text-center">
                    {{ props.pokemon.type1 }}
                </span>
                <span v-if="props.pokemon.type2" class="rounded bg-muted px-2 py-1 text-xs text-center">
                    {{ props.pokemon.type2 }}
                </span>
            </div>
        </CardContent>
    </Card>
</template>

<style>
</style>