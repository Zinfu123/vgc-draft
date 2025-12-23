<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Set {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1: {
        id: number;
        name: string;
    };
    team2: {
        id: number;
        name: string;
    };
    team1_score: number;
    team2_score: number;
    team1_pokepaste: string;
    team2_pokepaste: string;
    status: number;
}

interface props {
    set: Set;
}
const command = computed(() => {
    return props.set.status === 0 ? 'updatePokepaste' : 'update';
});

const props = defineProps<props>();

    const form = useForm({
    set_id: props.set.id,
    team1_score: props.set.team1_score || 0,
    team2_score: props.set.team2_score || 0,
    team1_id: props.set.team1.id,
    team2_id: props.set.team2.id,
    team1_pokepaste: props.set.team1_pokepaste || null,
    team2_pokepaste: props.set.team2_pokepaste || null,
    command: command.value,
});

const handleSubmit = () => {
    if (props.set.status === 0) {
        form.command = 'updatePokepaste';
    } else {
        form.command = 'update';
    }
    form.put('/match');
};
</script>

<template>
    <form @submit.prevent="handleSubmit">
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
                type="text"
                name="team1_pokepaste"
                id="team1_pokepaste"
                class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-gray-900 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"
                v-model="form.team1_pokepaste"
            />
            <Link
                :href="`/teams/form/${props.set.team1.id}`"
            >
                <p class="text-center text-sm text-gray-500">
                    {{ form.team1_pokepaste }}
                </p>
            </Link>
        </div>
    </div>
    <div class="sm:col-span-3">
        <label for="team2_pokepaste" class="block text-sm/6 font-medium text-gray-900 dark:text-white"
            >{{ props.set.team2.name }} Pokepaste</label
        >
        <div class="mt-2">
            <input
                type="text"
                name="team2_pokepaste"
                id="team2_pokepaste"
                class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-gray-900 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"
                v-model="form.team2_pokepaste"
            />
            <Link
                :href="`/teams/form/${props.set.team2.id}`"
            >
                <p class="text-center text-sm text-gray-500">
                    {{ form.team2_pokepaste }}
                </p>
            </Link>
        </div>
    </div>
    <div class="min-h-[38px] sm:col-span-6">
        <button
            type="submit"
            :class="[
                'rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
            ]"
        >
            Update
        </button>
    </div>
    </form>
</template>