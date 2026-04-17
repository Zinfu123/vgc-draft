<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { ChevronRight } from 'lucide-vue-next';

interface RosterPokemon {
    id: number;
    name: string;
    sprite_url: string | null;
    type1: string | null;
}

interface Teams {
    id: number;
    name: string;
    logo: string | null;
    coach: string;
    coach_discord_avatar_url: string | null;
    pokemon: RosterPokemon[];
}

defineProps<{
    team: Teams;
}>();
</script>

<template>
    <Card
        class="relative h-full overflow-hidden border-border/80 bg-gradient-to-b from-primary/[0.07] via-card to-card py-0 shadow-sm transition-all duration-200 group-hover:-translate-y-0.5 group-hover:border-primary/30 group-hover:shadow-lg dark:from-primary/[0.12] dark:via-card dark:to-card"
    >
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-watertype/70 via-electrictype/60 to-dragontype/70 opacity-90 dark:opacity-100"
            aria-hidden="true"
        />
        <CardContent class="flex flex-col gap-4 p-5 sm:p-6">
            <!-- Avatar + name/coach -->
            <div class="flex items-center gap-4">
                <div
                    v-if="team.logo || team.coach_discord_avatar_url"
                    class="ring-offset-background shrink-0 rounded-full ring-2 ring-border/80 ring-offset-2 shadow-md"
                >
                    <img
                        :src="team.logo ?? team.coach_discord_avatar_url!"
                        alt=""
                        class="size-14 rounded-full object-cover sm:size-16"
                    />
                </div>
                <div
                    v-else
                    class="flex size-14 shrink-0 items-center justify-center rounded-full bg-muted text-base font-bold text-muted-foreground ring-2 ring-border/80 ring-offset-2 ring-offset-background shadow-md sm:size-16 sm:text-lg"
                    aria-hidden="true"
                >
                    {{ team.name.charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="truncate font-semibold leading-tight tracking-tight sm:text-lg">{{ team.name }}</h3>
                    <p class="mt-0.5 truncate text-xs text-muted-foreground sm:text-sm">
                        Coach <span class="font-medium text-foreground/90">{{ team.coach }}</span>
                    </p>
                </div>
            </div>

            <!-- Pokémon sprites -->
            <div
                v-if="team.pokemon.length > 0"
                class="grid grid-cols-6 gap-1 rounded-xl border border-border/60 bg-muted/25 p-2 dark:bg-muted/15"
            >
                <div
                    v-for="mon in team.pokemon"
                    :key="mon.id"
                    class="flex items-center justify-center"
                    :title="mon.name"
                >
                    <img
                        v-if="mon.sprite_url"
                        :src="mon.sprite_url"
                        :alt="mon.name"
                        class="size-10 object-contain drop-shadow-sm sm:size-11"
                        loading="lazy"
                    />
                    <div
                        v-else
                        class="size-10 rounded-full bg-muted sm:size-11"
                        :aria-label="mon.name"
                    />
                </div>
            </div>
            <div
                v-else
                class="rounded-xl border border-dashed border-border/60 bg-muted/20 px-3 py-4 text-center dark:bg-muted/10"
            >
                <p class="text-xs text-muted-foreground">No Pokémon drafted yet</p>
            </div>

            <p
                class="flex items-center justify-center gap-1 text-center text-xs font-medium text-muted-foreground transition-colors group-hover:text-primary"
            >
                View team hub
                <ChevronRight class="size-3.5 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
            </p>
        </CardContent>
    </Card>
</template>
