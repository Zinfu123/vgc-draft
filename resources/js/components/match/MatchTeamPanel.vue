<script setup lang="ts">
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import { Link } from '@inertiajs/vue3';

interface TeamPokemon {
    id: number;
    name: string;
    cost: number;
    pokemon: {
        id: number;
        name: string;
        sprite_url: string;
        type1: string;
        type2?: string;
    };
}

interface Team {
    id: number;
    name: string;
    logo: string;
    showdown_username?: string | null;
    user: {
        id: number;
        name: string;
        showdown_username?: string | null;
    };
    pokemon: TeamPokemon[];
}

defineProps<{
    team: Team;
    showdownDisplay: string;
}>();
</script>

<template>
    <div class="flex min-w-0 flex-1 flex-col">
        <img
            v-if="team.logo"
            :src="team.logo"
            :alt="`${team.name} logo`"
            class="mx-auto h-30 w-30 rounded-full"
        />
        <Link :href="`/teams/${team.id}`">
            <p class="text-center text-2xl font-bold transition-colors hover:text-primary">{{ team.name }}</p>
            <p class="text-center text-muted-foreground transition-colors hover:text-primary">Coach: {{ team.user.name }}</p>
            <p class="text-center text-xs text-muted-foreground">
                <span class="font-medium text-foreground/80">Showdown</span>:
                <span v-if="showdownDisplay" class="font-mono">{{ showdownDisplay }}</span>
                <span v-else class="italic">Not set</span>
            </p>
        </Link>
        <p class="text-center text-2xl font-bold">Pokémon</p>
        <div class="flex flex-wrap justify-center gap-4">
            <PokemonCard
                v-for="pokemon in team.pokemon"
                :key="pokemon.id"
                :pokemon="{ ...pokemon.pokemon, cost: pokemon.cost, type2: pokemon.pokemon.type2 || '-' }"
            />
        </div>
    </div>
</template>
