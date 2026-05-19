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
    <div class="flex min-w-0 flex-1 flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <!-- Team info header -->
        <Link :href="`/teams/${team.id}`" class="flex flex-col items-center gap-2 p-4 transition-colors hover:bg-accent/50">
            <img
                v-if="team.logo"
                :src="team.logo"
                :alt="`${team.name} logo`"
                class="size-16 rounded-full object-cover"
            />
            <div class="text-center">
                <p class="text-xl font-bold">{{ team.name }}</p>
                <p class="text-sm text-muted-foreground">Coach: {{ team.user.name }}</p>
                <p class="text-xs text-muted-foreground">
                    <span class="font-medium text-foreground/80">Showdown</span>:
                    <span v-if="showdownDisplay" class="font-mono">{{ showdownDisplay }}</span>
                    <span v-else class="italic">Not set</span>
                </p>
            </div>
        </Link>

        <!-- Divider -->
        <div class="border-t border-border px-4 py-2">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pokémon</p>
        </div>

        <!-- Pokemon grid -->
        <div class="p-4">
            <div class="grid grid-cols-3 gap-3">
                <PokemonCard
                    v-for="pokemon in team.pokemon"
                    :key="pokemon.id"
                    :pokemon="{ ...pokemon.pokemon, cost: pokemon.cost, type2: pokemon.pokemon.type2 || '-' }"
                />
            </div>
        </div>
    </div>
</template>
