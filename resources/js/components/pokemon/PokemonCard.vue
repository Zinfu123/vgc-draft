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
    const type2 = props?.pokemon?.type2?.toLowerCase();
    if (type2 !== '-' && type2 !== undefined && type2 !== null && type2 !== '') {
        const pokemontypestyle = `background-image: linear-gradient(60deg, var(--${type1}type) 50%, var(--${type2}type) 50%)`;
        return pokemontypestyle;
    } else {
        const pokemontypestyle = `background-color: var(--${type1}type)`;
        return pokemontypestyle;
    }
}
</script>
<template>
    <Card :style="typeoutput()" class="grid h-[200px] w-[200px] grid-cols-6 grid-rows-8 outline-1 outline-gray-200">
        <CardHeader class="col-span-6 col-start-1 row-span-1 row-start-1">
            <CardTitle :class="`col-span-4 col-start-1 row-span-1 row-start-1 text-center text-sm font-bold text-white capitalize`">
                <span class="col-span-5 col-start-1 row-span-1 row-start-1 rounded bg-gray-800/85 dark:bg-muted/85">
                    {{ props.pokemon.name }}
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent class="subgrid col-span-6 col-start-1 row-span-8 row-start-1 grid grid-cols-6 grid-rows-8">
            <img
                :src="'https://raw.githubusercontent.com/Autumnchi/coloured-home-sprites/main/' + props.pokemon.name + '.png'"
                :alt="props.pokemon.name"
                class="col-span-4 col-start-1 row-start-3"
            />
            <span class="col-span-2 col-start-5 row-start-4 self-center rounded bg-gray-800/85 text-center text-sm capitalize dark:bg-muted/85">
                {{ props.pokemon.type1 }}
            </span>
            <span
                v-if="props.pokemon.type2 !== '-' && props.pokemon.type2 !== undefined && props.pokemon.type2 !== null && props.pokemon.type2 !== ''"
                class="col-span-2 col-start-5 row-start-5 self-center rounded bg-gray-800/85 text-center text-sm capitalize dark:bg-muted/85"
            >
                {{ props.pokemon.type2 }}
            </span>
            <span
                class="col-span-2 col-start-3 row-span-1 row-start-8 self-center rounded bg-gray-800/85 text-center text-xs dark:bg-muted/85"
                v-if="props.pokemon.cost !== undefined || props.cost !== undefined"
            >
                Cost: {{ props.pokemon.cost || props.cost?.cost }}
            </span>
        </CardContent>
    </Card>
</template>

<style></style>
