<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';




interface Pokemon {
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
    cost: number;
}

interface Cost {
    cost: number;
}

interface props {
    pokemon: Pokemon;
    cost?: number;
}

const props = defineProps<props>();


function typeoutput() {
    const type1 = props.pokemon.type1.toLowerCase();
    const type2 = props.pokemon.type2?.toLowerCase();
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
    <Card :style="typeoutput()" class="grid grid-cols-6 grid-rows-8 outline-1 outline-gray-200 w-[200px] h-[200px]">
        <CardHeader class="col-start-1 row-start-1 col-span-6 row-span-1">
            <CardTitle :class="`col-start-1 row-start-1 col-span-4 row-span-1 text-center text-sm font-bold capitalize text-white `">
                <span class="col-start-1 row-start-1 col-span-5 row-span-1 rounded bg-gray-800/85 dark:bg-muted/85">
                        {{ props.pokemon.name }}
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent class="col-start-1 row-start-1 col-span-6 row-span-8 grid grid-cols-6 grid-rows-8 subgrid">
                <img :src="'https://raw.githubusercontent.com/Autumnchi/coloured-home-sprites/main/'+props.pokemon.name+'.png'" :alt="props.pokemon.name" class="col-start-1 row-start-3 col-span-4"/>
                    <span class="col-start-5 row-start-4 col-span-2 rounded bg-gray-800/85 dark:bg-muted/85 text-sm text-center self-center capitalize">
                        {{ props.pokemon.type1 }}
                    </span>
                    <span v-if="props.pokemon.type2 !== '-' && props.pokemon.type2 !== undefined && props.pokemon.type2 !== null && props.pokemon.type2 !== ''" class="col-start-5 row-start-5 col-span-2 rounded bg-gray-800/85 dark:bg-muted/85 text-sm text-center self-center capitalize">
                        {{ props.pokemon.type2 }}
                    </span>
                <span class="col-start-3 row-start-8 col-span-2 row-span-1 rounded bg-gray-800/85 dark:bg-muted/85 text-xs text-center self-center" v-if="props.pokemon.cost !== undefined || props.cost !== undefined">
                    Cost: {{ props.pokemon.cost || props.cost?.cost }}
                </span>
        </CardContent>
    </Card>
</template>

<style>
</style>