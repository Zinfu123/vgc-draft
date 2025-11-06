<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';




interface Pokemon {
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
    league: Array<{
        pivot: {
            cost: number;
        };
    }>;
}

interface props {
    pokemon: Pokemon;
    cost?: number;
}

const props = defineProps<props>();


function typeoutput() {
    const type1 = props.pokemon.type1.toLowerCase();
    const type2 = props.pokemon.type2.toLowerCase();
    if (type2 !== '-' && type2 !== undefined && type2 !== null && type2 !== '') {
        const pokemontypestyle = `background-image: linear-gradient(60deg, var(--${type1}type) 50%, var(--${type2}type) 50%)`
        return pokemontypestyle
    }
    else {
        const pokemontypestyle = `background-color: var(--${type1}type)`
        return pokemontypestyle
    }
}

</script>
<template>
    <Card :style="typeoutput()" class="w-[200px] h-[200px]">
        <CardHeader>
            <CardTitle :class="`text-center text-md font-bold capitalize text-white `">
                <span class="rounded bg-gray-800/85 dark:bg-muted/85 px-2 py-1">
                        {{ props.pokemon.name }}
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent class="flex flex-col items-center justify-center relative text-white">
            <div class="flex items-center justify-center flex-row">
                <img :src="props.pokemon.sprite_url" :alt="props.pokemon.name" class="w-[100px] h-[100px]"/>
                <div class="flex flex-col text-black-300">
                    <span class="rounded bg-gray-800/85 dark:bg-muted/85 w-15 h-7 mt-auto text-center capitalize mb-1">
                        {{ props.pokemon.type1 }}
                    </span>
                    <span v-if="props.pokemon.type2 !== '-' && props.pokemon.type2 !== undefined && props.pokemon.type2 !== null && props.pokemon.type2 !== ''" class="rounded bg-gray-800/85 dark:bg-muted/85 w-15 h-7 text-center capitalize ">
                        {{ props.pokemon.type2 }}
                    </span>
                </div>
            </div>
            <div class="flex flex-row">
                <span class="rounded bg-gray-800/85 dark:bg-muted/85 px-1 text-sm text-center" v-if="props.cost !== undefined || props.pokemon?.league?.[0]?.pivot?.cost !== undefined">
                    Cost: {{ props.pokemon?.league?.[0]?.pivot?.cost || props.cost?.cost}}
                </span>
            </div>
        </CardContent>
    </Card>
</template>

<style>
</style>