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

function typeoutput() {
    const type1 = props.pokemon.type1.toLowerCase();
    const type2 = props.pokemon.type2.toLowerCase();
    if (type2 !== '-') {
        const pokemontypestyle = `background-image: linear-gradient(60deg, var(--${type1}type) 50%, var(--${type2}type) 50%)`
        return pokemontypestyle
    }
    else {
        const pokemontypestyle = `background-color: var(--${type1}type)`
        return pokemontypestyle
    }
}

const classname = typeoutput();

console.log(classname);

</script>
<template>
    <Card :style="typeoutput()">
        <CardHeader>
            <CardTitle :class="`text-center text-lg font-bold capitalize text-white `">
                <span class="rounded bg-gray-800/85 dark:bg-muted/85 px-2 py-1">
                        {{ props.pokemon.name }}
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent class="flex flex-col items-center justify-center relative text-white">
            <div class="z-20 flex items-center justify-center flex-row">
                <img :src="props.pokemon.sprite_url" :alt="props.pokemon.name" />
                <div class="flex flex-col text-black-300">
                    <span class="rounded bg-gray-800/85 dark:bg-muted/85 px-2 py-1 text-xs text-center">
                        {{ props.pokemon.type1 }}
                    </span>
                    <span v-if="props.pokemon.type2 !== '-'" class="rounded bg-gray-800/85 dark:bg-muted/85 px-2 py-1 text-xs text-center">
                        {{ props.pokemon.type2 }}
                    </span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

<style>
</style>