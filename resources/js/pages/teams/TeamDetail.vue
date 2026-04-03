<script setup lang="ts">
import BlobBackground from '@/components/BlobBackground.vue';
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

interface Team {
    id: number;
    name: string;
    logo: string | null;
    coach: string;
    set_wins: number;
    set_losses: number;
    victory_points: number;
    pokemon: Array<{
        id: number;
        name: string;
        cost: number;
        pokemon: {
            sprite_url: string;
            type1: string;
            type2?: string;
        };
    }>;
}

interface League {
    id: number;
    name: string;
}

interface props {
    team: Team;
    league: League;
}

const props = defineProps<props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: props.league.name,
        href: `/leagues/${props.league.id}`,
    },
    {
        title: props.team.name,
        href: `/teams/${props.team.id}`,
    },
];

function winPct(wins: number, losses: number): string {
    const total = wins + losses;
    if (total === 0) {
        return '—';
    }

    return (wins / total).toLocaleString('en-US', { style: 'percent', minimumFractionDigits: 1 });
}
</script>

<template>
    <Head :title="`${props.team.name} · ${props.league.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative mx-auto w-full max-w-7xl px-4 py-6 pb-10 sm:px-6 lg:px-8">
            <BlobBackground>
                <div class="absolute -top-24 right-1/4 h-64 w-64 rounded-full bg-watertype/15 blur-3xl dark:bg-watertype/20" />
                <div class="absolute top-1/3 -left-20 h-56 w-56 rounded-full bg-dragontype/10 blur-3xl dark:bg-dragontype/15" />
            </BlobBackground>

            <div class="relative z-10 flex flex-col gap-6">
                <Card class="overflow-hidden border-border bg-card/80 shadow-sm backdrop-blur-sm dark:bg-card/90">
                    <CardHeader class="items-center space-y-4 text-center pb-2">
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <Button variant="outline" size="sm" class="touch-manipulation" as-child>
                                <Link :href="route('leagues.teams', { league: props.league.id })">
                                    {{ props.league.name }}
                                </Link>
                            </Button>
                            <Button variant="ghost" size="sm" class="touch-manipulation text-muted-foreground" as-child>
                                <Link :href="route('leagues.matches', { league: props.league.id, team: props.team.id })">
                                    Matches
                                </Link>
                            </Button>
                        </div>
                        <div
                            v-if="props.team.logo"
                            class="ring-offset-background rounded-full ring-2 ring-border ring-offset-2"
                        >
                            <img
                                :src="props.team.logo"
                                alt=""
                                class="inline size-28 rounded-full object-cover md:size-32"
                            />
                        </div>
                        <div
                            v-else
                            class="flex size-28 items-center justify-center rounded-full bg-muted text-2xl font-bold text-muted-foreground ring-2 ring-border ring-offset-2 ring-offset-background md:size-32"
                            aria-hidden="true"
                        >
                            {{ props.team.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="space-y-1">
                            <CardTitle class="text-balance text-2xl sm:text-3xl">{{ props.team.name }}</CardTitle>
                            <CardDescription class="text-base">Coach: {{ props.team.coach }}</CardDescription>
                        </div>
                    </CardHeader>
                </Card>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
                    <Card class="border-border bg-primary/5 shadow-sm dark:bg-primary/10 lg:col-span-2">
                        <CardHeader>
                            <CardTitle class="text-xl">Drafted Pokémon</CardTitle>
                            <CardDescription>Roster for this league.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                                <PokemonCard
                                    v-for="pokemon in props.team.pokemon"
                                    :key="pokemon.id"
                                    :pokemon="{
                                        name: pokemon.name,
                                        ...pokemon.pokemon,
                                        cost: pokemon.cost,
                                        type2: pokemon.pokemon.type2 || '-',
                                    }"
                                />
                            </div>
                            <p
                                v-if="props.team.pokemon.length === 0"
                                class="text-center text-sm text-muted-foreground sm:text-left"
                            >
                                No Pokémon on this roster yet.
                            </p>
                        </CardContent>
                    </Card>

                    <div class="flex flex-col gap-6 lg:col-span-1">
                        <Card class="border-border shadow-sm">
                            <CardHeader>
                                <CardTitle class="text-xl">Upcoming matches</CardTitle>
                                <CardDescription>Scheduled sets for this team.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div
                                    class="rounded-md border border-dashed border-border bg-muted/30 p-4 text-center text-sm text-muted-foreground"
                                >
                                    Match schedule is managed on the league matches page.
                                </div>
                                <Button variant="secondary" class="mt-4 w-full touch-manipulation" as-child>
                                    <Link :href="route('leagues.matches', { league: props.league.id, team: props.team.id })">
                                        View league matches
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>

                        <Card class="border-border shadow-sm">
                            <CardHeader>
                                <CardTitle class="text-xl">Record</CardTitle>
                                <CardDescription>Season totals in this league.</CardDescription>
                            </CardHeader>
                            <CardContent class="flex flex-col gap-4">
                                <div class="flex flex-col items-center gap-1 text-center">
                                    <span class="text-4xl font-bold tabular-nums">
                                        {{ props.team.set_wins }} – {{ props.team.set_losses }}
                                    </span>
                                    <span class="text-sm text-muted-foreground">Set record</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ winPct(props.team.set_wins, props.team.set_losses) }} sets won
                                    </span>
                                </div>
                                <div
                                    class="flex flex-col items-center gap-1 border-t border-border pt-4 text-center"
                                >
                                    <span class="text-2xl font-bold tabular-nums text-orange-500 dark:text-orange-400">
                                        {{ props.team.victory_points }}
                                    </span>
                                    <span class="text-sm text-muted-foreground">Victory points</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
