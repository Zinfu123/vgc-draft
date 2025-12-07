<script setup lang="ts">
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
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
    team1_pokepaste: string;
    team2_pokepaste: string;
    winner_id: number;
    winner_name: string;
    winner_logo: string;
    status: number;
}

interface CurrentUserTeam {
    id: number;
}
const page = usePage();
const user = computed(() => page.props.auth.user);

interface props {
    set: Set;
    currentUserTeam: CurrentUserTeam;
}

const props = defineProps<props>();
const setId = props.set.id;
const form = useForm({
    set_id: props.set.id,
    team1_score: props.set.team1_score,
    team2_score: props.set.team2_score,
    team1_id: props.set.team1.id,
    team2_id: props.set.team2.id,
    team1_pokepaste: props.set.team1_pokepaste || null,
    team2_pokepaste: props.set.team2_pokepaste || null,
    command: 'update',
});

type SetModel = {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1_score: number;
    team2_score: number;
    team1_pokepaste: string;
    team2_pokepaste: string;
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

const disableForm = computed(() => {
    if (echoEvent.value.status == 0 || props.set.status === 0) {
        return 1;
    } else if (props.set.team1.user.id != props.currentUserTeam.id && props.set.team2.user.id != props.currentUserTeam.id) {
        return ;
    } else {
        return 0;
    }
});
</script>
<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${props.set.team1.name} vs ${props.set.team2.name}`" />
        <div class="mx-auto mt-8 mb-8 flex max-w-4xl flex-col items-center">
            <h1 class="text-3xl font-bold">{{ props.set.team1.name }} vs {{ props.set.team2.name }}</h1>
        </div>
        <div class="mt-8 flex flex-row">
            <div class="mx-auto flex max-w-7xl flex-col border-1 border-red-600 sm:px-6 lg:px-8">
                <div>
                    <!-- Team 1 -->
                    <img v-if="props.set.team1.logo" :src="props.set.team1.logo" alt="Team Logo" class="mx-auto h-30 w-30 rounded-full"  />
                    <Link :href="`/teams/${props.set.team1.id}`">
                        <p class="text-center text-2xl font-bold hover:text-blue-500">
                            {{ props.set.team1.name }}
                        </p>
                        <p class="text-2x1 text-center text-gray-500 hover:text-blue-500">Coach: {{ props.set.team1.user.name }}</p>
                    </Link>
                    <div class="mx-auto flex max-w-7xl flex-col sm:px-6 lg:px-8">
                        <p class="text-center text-2xl font-bold">Pokemon</p>
                        <div class="grid grid-cols-2 gap-4">
                            <PokemonCard
                                v-for="pokemon in props.set.team1.pokemon"
                                :key="pokemon.id"
                                :pokemon="{ ...pokemon.pokemon, cost: pokemon.cost, type2: pokemon.pokemon.type2 || '-' }"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <!-- Center Column-->
            <div class="mx-auto flex max-w-7xl flex-col sm:px-6 lg:px-8">
                <form class="top-0" @submit.prevent="form.put('/match')">
                    <div class="space-y-12">
                        <div class="border-b border-gray-900/10 pb-12 dark:border-white/10">
                            <h2 class="mb-6 text-center text-base/7 font-semibold text-gray-900 dark:text-white">Set Result</h2>
                            <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
                                <div class="sm:col-span-3">
                                    <label for="team1_score" class="block text-sm/6 font-medium text-gray-900 dark:text-white"
                                        >{{ props.set.team1.name }} Score</label
                                    >
                                    <div class="mt-2">
                                        <select
                                            name="team1_score"
                                            id="team1_score"
                                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-gray-900 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"
                                            v-model="form.team1_score"
                                            :disabled="disableForm == 1"
                                        >
                                            <option value="0">0</option>
                                            <option value="1">1</option>
                                            <option v-if="form.team2_score < 2" value="2">2</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="team2_score" class="block text-sm/6 font-medium text-gray-900 dark:text-white"
                                        >{{ props.set.team2.name }} Score</label
                                    >
                                    <div class="mt-2">
                                        <select
                                            name="team2_score"
                                            id="team2_score"
                                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-gray-900 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"
                                            v-model="form.team2_score"
                                            :disabled="disableForm == 1"
                                        >
                                            <option value="0">0</option>
                                            <option value="1">1</option>
                                            <option v-if="form.team1_score < 2" value="2">2</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="team1_pokepaste" class="block text-sm/6 font-medium text-gray-900 dark:text-white"
                                        >{{ props.set.team1.name }} Pokepaste</label
                                    >
                                    <div class="mt-2">
                                        <input
                                            v-if="disableForm != 1"
                                            type="string"
                                            name="team1_pokepaste"
                                            id="team1_pokepaste"
                                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-gray-900 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"
                                            v-model="form.team1_pokepaste"
                                            :disabled="disableForm == 1"
                                        />
                                    </div>
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="team2_pokepaste" class="block text-sm/6 font-medium text-gray-900 dark:text-white"
                                        >{{ props.set.team2.name }} Pokepaste</label
                                    >
                                    <div class="mt-2">
                                        <input
                                            v-if="disableForm != 1"
                                            type="string"
                                            name="team2_pokepaste"
                                            id="team2_pokepaste"
                                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-gray-900 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"
                                            v-model="form.team2_pokepaste"
                                            :disabled="disableForm == 1"
                                        />
                                        <Link :href="`/teams/form/${props.set.team2.id}`" v-if="disableForm == 1 && form.team2_pokepaste != null && form.team2_pokepaste != 0">
                                        <p v-if="disableForm == 1 && form.team2_pokepaste != null" class="text-center text-sm text-gray-500">{{ form.team2_pokepaste }}</p>
                                        </Link>
                                    </div>
                                </div>
                                <button
                                    v-if="disableForm === 0 && (form.team1_score == 2 || form.team2_score == 2)"
                                    type="submit"
                                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                >
                                    Update
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <!-- Team 2 -->
            <div class="justify-top mx-auto flex max-w-7xl flex-col border-1 border-green-600 sm:px-6 lg:px-8">
                <div>
                    <img v-if="props.set.team2.logo" :src="props.set.team2.logo" alt="Team Logo" class="mx-auto h-30 w-30 rounded-full" />
                    <Link :href="`/teams/${props.set.team2.id}`">
                        <p class="text-center text-2xl font-bold hover:text-blue-500">
                            {{ props.set.team2.name }}
                        </p>
                        <p class="text-2x1 text-center text-gray-500 hover:text-blue-500">Coach: {{ props.set.team2.user.name }}</p>
                    </Link>
                    <div class="mx-auto flex max-w-7xl flex-col sm:px-6 lg:px-8">
                        <p class="text-center text-2xl font-bold">Pokemon</p>
                        <div class="grid grid-cols-2 gap-4">
                            <PokemonCard
                                v-for="pokemon in props.set.team2.pokemon"
                                :key="pokemon.id"
                                :pokemon="{ ...pokemon.pokemon, cost: pokemon.cost, type2: pokemon.pokemon.type2 || '-' }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
