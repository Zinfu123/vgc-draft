<script setup lang="ts">
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { computed, ref } from 'vue';

interface Set {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1: {
        id: number;
        name: string;
        logo: string;
        user: {
            id: number;
            name: string;
        };
        pokemon: [
            {
                id: number;
                name: string;
                pokemon: {
                    id: number;
                    name: string;
                    sprite_url: string;
                    type1: string;
                    type2?: string;
                };
                cost: number;
            },
        ];
    };
    team2: {
        id: number;
        name: string;
        logo: string;
        user: {
            id: number;
            name: string;
        };
        pokemon: [
            {
                id: number;
                name: string;
                pokemon: {
                    id: number;
                    name: string;
                    sprite_url: string;
                    type1: string;
                    type2?: string;
                };
                cost: number;
            },
        ];
    };
    team1_score: number;
    team2_score: number;
    team1_pokepaste: string | null;
    team2_pokepaste: string | null;
    replay1: string | null;
    replay2: string | null;
    replay3: string | null;
    winner_id: number;
    winner_name: string;
    winner_logo: string;
    status: number;
}

interface CurrentUserTeam {
    id: number;
}

interface MatchPokepastePayload {
    pokepaste_public_id: string;
}

interface MatchPokepasteSides {
    team1: { public_id: string; has_data: boolean } | null;
    team2: { public_id: string; has_data: boolean } | null;
}

interface props {
    set: Set;
    currentUserTeam: CurrentUserTeam | null;
    matchPokepaste: MatchPokepastePayload | null;
    matchPokepasteSides: MatchPokepasteSides;
}

const props = defineProps<props>();
const setId = props.set.id;
const form = useForm({
    set_id: props.set.id,
    team1_score: props.set.team1_score || 0,
    team2_score: props.set.team2_score || 0,
    team1_id: props.set.team1.id,
    team2_id: props.set.team2.id,
    command: 'update',
});

const replayForm = useForm({
    set_id: props.set.id,
    replay1: props.set.replay1 || '',
    replay2: props.set.replay2 || '',
    replay3: props.set.replay3 || '',
});

type SetModel = {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1_score: number;
    team2_score: number;
    replay1: string | null;
    replay2: string | null;
    replay3: string | null;
    winner_id: number;
    winner_name: string;
    winner_logo: string;
    status: number;
};
const echoEvent = ref<{ id: SetModel['id']; status: SetModel['status'] }>({ id: form.set_id, status: props.set.status });

useEchoPublic<SetModel>(`set_updated.${setId}`, 'SetUpdatedEvent', (e) => {
    console.log(e);
    echoEvent.value = { id: e.id, status: e.status };
    // router.visit(route('sets.show', { set_id: e.id }), {
    //     only: ['set'],
    //     preserveState: true,
    //     preserveScroll: true,
    // });
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'League',
        href: `/leagues/${props.set.league_id}`,
    },
    {
        title: 'Match',
        href: `/match/set/${props.set.id}`,
    },
];

const isUserInSet = computed((): boolean => {
    if (!props.currentUserTeam) {
        return false;
    }
    return props.set.team1.id === props.currentUserTeam.id || props.set.team2.id === props.currentUserTeam.id;
});

const isSetCompleted = computed((): boolean => {
    return echoEvent.value.status === 0 || props.set.status === 0;
});

const disableForm = computed((): boolean => {
    if (echoEvent.value.status === 0 || props.set.status === 0) {
        return true;
    } else if (!isUserInSet.value) {
        return true;
    } else {
        return false;
    }
});

const canSubmitSetResult = computed((): boolean => {
    const a = Number(form.team1_score);
    const b = Number(form.team2_score);
    return (a === 2 && b <= 1) || (b === 2 && a <= 1);
});

const winnerCoach = computed((): string | null => {
    if (props.set.winner_id === props.set.team1.id) {
        return props.set.team1.user.name;
    } else if (props.set.winner_id === props.set.team2.id) {
        return props.set.team2.user.name;
    }
    return null;
});

const winnerLogo = computed((): string | null => {
    if (props.set.winner_id === props.set.team1.id) {
        return props.set.team1.logo || null;
    } else if (props.set.winner_id === props.set.team2.id) {
        return props.set.team2.logo || null;
    }
    return props.set.winner_logo || null;
});

const handleSubmit = () => {
    form.command = 'update';
    form.put('/match');
};

const handleReplaySubmit = () => {
    replayForm.put(route('sets.update-replays'));
};
</script>
<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${props.set.team1.name} vs ${props.set.team2.name}`" />
        <div class="mx-auto mt-8 mb-8 flex max-w-4xl flex-col items-center">
            <h1 class="text-3xl font-bold">{{ props.set.team1.name }} vs {{ props.set.team2.name }}</h1>
        </div>
        <div class="mt-8 flex flex-row items-start gap-4">
            <div class="flex min-w-0 flex-1 flex-col">
                <!-- Team 1 -->
                <img v-if="props.set.team1.logo" :src="props.set.team1.logo" alt="Team Logo" class="mx-auto h-30 w-30 rounded-full" />
                <Link :href="`/teams/${props.set.team1.id}`">
                    <p class="text-center text-2xl font-bold transition-colors hover:text-primary">
                        {{ props.set.team1.name }}
                    </p>
                    <p class="text-center text-muted-foreground transition-colors hover:text-primary">Coach: {{ props.set.team1.user.name }}</p>
                </Link>
                <p class="text-center text-2xl font-bold">Pokemon</p>
                <div class="flex flex-wrap justify-center gap-4">
                    <PokemonCard
                        v-for="pokemon in props.set.team1.pokemon"
                        :key="pokemon.id"
                        :pokemon="{ ...pokemon.pokemon, cost: pokemon.cost, type2: pokemon.pokemon.type2 || '-' }"
                    />
                </div>
            </div>
            <!-- Center Column-->
            <div class="flex min-w-0 flex-1 flex-col px-4">
                <form class="top-0" @submit.prevent="handleSubmit">
                    <div class="space-y-12">
                        <div v-if="props.matchPokepaste && isUserInSet" class="pb-2">
                            <h2 class="text-lg font-semibold text-foreground">Match team paste</h2>
                            <p class="text-muted-foreground mt-1 text-sm">
                                Build and save your six in the editor (Showdown-style). Only you can open your paste.
                            </p>
                            <Link
                                :href="'/pokepaste/' + props.matchPokepaste.pokepaste_public_id"
                                class="bg-primary text-primary-foreground mt-4 inline-flex rounded-md px-4 py-2 text-sm font-semibold shadow-sm transition-colors hover:bg-primary/90"
                            >
                                Open team paste editor
                            </Link>
                        </div>

                        <div class="border-b border-border pb-12">
                            <h2 class="mb-6 text-center text-base/7 font-semibold text-foreground">Set Result</h2>
                            <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
                                <div class="sm:col-span-3">
                                    <label for="team1_score" class="block text-sm/6 font-medium text-foreground"
                                        >{{ props.set.team1.name }} Score</label
                                    >
                                    <div class="mt-2">
                                        <select
                                            name="team1_score"
                                            id="team1_score"
                                            class="block w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                                            v-model="form.team1_score"
                                            :disabled="disableForm"
                                        >
                                            <option value="0">0</option>
                                            <option value="1">1</option>
                                            <option v-if="form.team2_score < 2" value="2">2</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="team2_score" class="block text-sm/6 font-medium text-foreground"
                                        >{{ props.set.team2.name }} Score</label
                                    >
                                    <div class="mt-2">
                                        <select
                                            name="team2_score"
                                            id="team2_score"
                                            class="block w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                                            v-model="form.team2_score"
                                            :disabled="disableForm"
                                        >
                                            <option value="0">0</option>
                                            <option value="1">1</option>
                                            <option v-if="form.team1_score < 2" value="2">2</option>
                                        </select>
                                    </div>
                                </div>

                                <p v-if="form.errors.set_result" class="text-destructive sm:col-span-6 text-sm">
                                    {{ form.errors.set_result }}
                                </p>
                                <p
                                    v-else-if="!isSetCompleted && isUserInSet && !canSubmitSetResult"
                                    class="text-muted-foreground sm:col-span-6 text-sm"
                                >
                                    Choose a final set score: one side must have 2 wins (2-0 or 2-1).
                                </p>

                                <template v-if="isSetCompleted">
                                    <div class="sm:col-span-3">
                                        <p class="block text-sm/6 font-medium text-foreground">{{ props.set.team1.name }} team paste</p>
                                        <div class="mt-2">
                                            <Link
                                                v-if="props.matchPokepasteSides.team1?.has_data"
                                                :href="'/pokepaste/' + props.matchPokepasteSides.team1.public_id"
                                                class="text-primary text-sm font-medium hover:underline"
                                            >
                                                View team paste
                                            </Link>
                                            <p v-else class="text-muted-foreground text-sm">No team paste saved for this match.</p>
                                        </div>
                                    </div>
                                    <div class="sm:col-span-3">
                                        <p class="block text-sm/6 font-medium text-foreground">{{ props.set.team2.name }} team paste</p>
                                        <div class="mt-2">
                                            <Link
                                                v-if="props.matchPokepasteSides.team2?.has_data"
                                                :href="'/pokepaste/' + props.matchPokepasteSides.team2.public_id"
                                                class="text-primary text-sm font-medium hover:underline"
                                            >
                                                View team paste
                                            </Link>
                                            <p v-else class="text-muted-foreground text-sm">No team paste saved for this match.</p>
                                        </div>
                                    </div>
                                </template>

                                <!-- Replay 1 -->
                                <div class="sm:col-span-6">
                                    <label for="replay1" class="block text-sm/6 font-medium text-foreground">Game 1 Replay</label>
                                    <div class="mt-2">
                                        <input
                                            v-if="isUserInSet"
                                            type="url"
                                            id="replay1"
                                            placeholder="https://replay.pokemonshowdown.com/..."
                                            class="block w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                                            v-model="replayForm.replay1"
                                        />
                                        <a
                                            v-if="!isUserInSet && replayForm.replay1"
                                            :href="replayForm.replay1"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="block max-w-full truncate text-center text-sm text-muted-foreground transition-colors hover:text-primary"
                                        >
                                            {{ replayForm.replay1 }}
                                        </a>
                                        <p v-if="replayForm.errors.replay1" class="mt-1 text-sm text-destructive">{{ replayForm.errors.replay1 }}</p>
                                    </div>
                                </div>

                                <!-- Replay 2 -->
                                <div class="sm:col-span-6">
                                    <label for="replay2" class="block text-sm/6 font-medium text-foreground">Game 2 Replay</label>
                                    <div class="mt-2">
                                        <input
                                            v-if="isUserInSet"
                                            type="url"
                                            id="replay2"
                                            placeholder="https://replay.pokemonshowdown.com/..."
                                            class="block w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                                            v-model="replayForm.replay2"
                                        />
                                        <a
                                            v-if="!isUserInSet && replayForm.replay2"
                                            :href="replayForm.replay2"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="block max-w-full truncate text-center text-sm text-muted-foreground transition-colors hover:text-primary"
                                        >
                                            {{ replayForm.replay2 }}
                                        </a>
                                        <p v-if="replayForm.errors.replay2" class="mt-1 text-sm text-destructive">{{ replayForm.errors.replay2 }}</p>
                                    </div>
                                </div>

                                <!-- Replay 3 -->
                                <div class="sm:col-span-6">
                                    <label for="replay3" class="block text-sm/6 font-medium text-foreground">Game 3 Replay</label>
                                    <div class="mt-2">
                                        <input
                                            v-if="isUserInSet"
                                            type="url"
                                            id="replay3"
                                            placeholder="https://replay.pokemonshowdown.com/..."
                                            class="block w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                                            v-model="replayForm.replay3"
                                        />
                                        <a
                                            v-if="!isUserInSet && replayForm.replay3"
                                            :href="replayForm.replay3"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="block max-w-full truncate text-center text-sm text-muted-foreground transition-colors hover:text-primary"
                                        >
                                            {{ replayForm.replay3 }}
                                        </a>
                                        <p v-if="replayForm.errors.replay3" class="mt-1 text-sm text-destructive">{{ replayForm.errors.replay3 }}</p>
                                    </div>
                                </div>

                                <div class="flex min-h-[38px] gap-3 sm:col-span-6">
                                    <button
                                        v-if="!isSetCompleted && isUserInSet"
                                        type="submit"
                                        :disabled="disableForm || !canSubmitSetResult"
                                        class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Update
                                    </button>
                                    <button
                                        v-if="isUserInSet"
                                        type="button"
                                        :disabled="replayForm.processing"
                                        class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                                        @click="handleReplaySubmit"
                                    >
                                        Save Replays
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-if="props.set.winner_id" class="mt-8 border-t border-border pt-8">
                            <h2 class="mb-6 text-center text-base/7 font-semibold text-foreground">Winner</h2>
                            <div class="flex flex-col items-center justify-center space-y-4">
                                <img v-if="winnerLogo" :src="winnerLogo" alt="Winner Logo" class="mx-auto h-30 w-30 rounded-full" />
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-foreground">{{ props.set.winner_name }}</p>
                                    <p v-if="winnerCoach" class="text-lg text-muted-foreground">Coach: {{ winnerCoach }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <!-- Team 2 -->
            <div class="flex min-w-0 flex-1 flex-col">
                <img v-if="props.set.team2.logo" :src="props.set.team2.logo" alt="Team Logo" class="mx-auto h-30 w-30 rounded-full" />
                <Link :href="`/teams/${props.set.team2.id}`">
                    <p class="text-center text-2xl font-bold transition-colors hover:text-primary">
                        {{ props.set.team2.name }}
                    </p>
                    <p class="text-center text-muted-foreground transition-colors hover:text-primary">Coach: {{ props.set.team2.user.name }}</p>
                </Link>
                <p class="text-center text-2xl font-bold">Pokemon</p>
                <div class="flex flex-wrap justify-center gap-4">
                    <PokemonCard
                        v-for="pokemon in props.set.team2.pokemon"
                        :key="pokemon.id"
                        :pokemon="{ ...pokemon.pokemon, cost: pokemon.cost, type2: pokemon.pokemon.type2 || '-' }"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
