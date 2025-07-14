<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ref, onMounted } from 'vue';

interface Pokemon {
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
}

interface props {
    pokemon: Pokemon;
}

const isDark = ref(false);

onMounted(() => {
    // 1. Check local storage
    if (localStorage.getItem('darkMode') === 'true') {
      isDark.value = true;
    }

    // 2. Check for "dark" class on the HTML or body element
    if (document.documentElement.classList.contains('dark')) {
      isDark.value = true;
    }

    // Alternatively, if you're using a specific library or custom solution,
    // you might have a different way to access the current dark mode state.
  });

const props = defineProps<props>();

const typeColor = () => {
    if (isDark.value === true) {
        return `type-${props.pokemon.type1.toLowerCase()}-dark`;
    }
    else {
        return `type-${props.pokemon.type1.toLowerCase()}`;
    }
};

</script>
<template>
    <Card :class="typeColor()">
        <CardHeader>
            <CardTitle :class="`text-center text-2xl font-bold capitalize`">
                {{ props.pokemon.name }}
            </CardTitle>
        </CardHeader>
        <CardContent class="flex flex-col items-center justify-center relative">
            <div class="z-10">
                <div class="diagonal-line"></div>
            </div>
            <div class="z-20">
            <img :src="props.pokemon.sprite_url" :alt="props.pokemon.name" />
                <span class="rounded bg-muted px-2 py-1 text-xs">
                    {{ props.pokemon.type1 }}
                </span>
                <span v-if="props.pokemon.type2" class="rounded bg-muted px-2 py-1 text-xs">
                    {{ props.pokemon.type2 }}
                </span>
            </div>
        </CardContent>
    </Card>
</template>

<style>
.type-normal {
    background-color: hsl(0 0% 66.7%);
}

.type-normal-dark {
    background-color: hsl(0, 0%, 81%);
}

.type-fire {
    background-color: hsl(12, 85%, 43%);
}

.type-fire-dark {
    background-color: hsl(12, 89%, 44%);
}

.type-water {
    background-color: hsl(207 90% 54%);
}

.type-water-dark {
    background-color: hsl(207 90% 34%);
}

.type-electric {
    background-color: hsl(56 94% 57%);
}

.type-electric-dark {
    background-color: hsl(56 94% 37%);
}

.type-grass {
    background-color: hsl(123, 63%, 36%, 0.877);
}

.type-grass-dark {
    background-color: hsl(120, 66%, 21%);
}

.type-ice {
    background-color: hsl(195 100% 81%);
}

.type-ice-dark {
    background-color: hsl(195 100% 61%);
}

.type-fighting {
    background-color: hsl(19, 80%, 49%);
}

.type-fighting-dark {
    background-color: hsl(19, 80%, 49%);
}

.type-poison {
    background-color: hsl(283 77% 57%);
}

.type-poison-dark {
    background-color: hsl(283 77% 37%);
}

.type-ground {
    background-color: hsl(45, 71%, 27%);
}

.type-ground-dark {
    background-color: hsl(45, 67%, 25%);
}

.type-psychic {
    background-color: hsl(323 100% 57%);
}

.type-psychic-dark {
    background-color: hsl(323 100% 37%);
}

.type-bug {
    background-color: hsl(91, 100%, 63%);
}

.type-bug-dark {
    background-color: hsl(91, 100%, 63%);
}

.type-rock {
    background-color: hsl(29, 24%, 31%);
}

.type-rock-dark {
    background-color: hsl(29, 24%, 31%);
}

.type-ghost {
    background-color: hsl(270, 100%, 78%);
}

.type-ghost-dark {
    background-color: hsl(270, 100%, 78%);
}

.type-dragon {
    background-color: hsl(256, 78%, 57%);
}

.type-dragon-dark {
    background-color: hsl(256, 78%, 57%);
}

.type-dark {
    background-color: hsl(37, 31%, 15%);
}

.type-dark-dark {
    background-color: hsl(37, 31%, 15%);
}

.type-steel {
    background-color: hsl(240, 1%, 32%);
}

.type-steel-dark {
    background-color: hsl(240, 1%, 32%);
}

.type-fairy {
    background-color: hsl(310, 100%, 76%);
}

.type-fairy-dark {
    background-color: hsl(310, 100%, 76%);
}

.diagonal-line{
    width: 100px;
    height: 2px;
    background-color: black;
    transform: rotate(45deg);
    transform-origin: top left;
}

</style>