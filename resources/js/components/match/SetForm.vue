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
            <label for="team1_score" class="block text-sm/6 font-medium text-foreground">{{ props.set.team1.name }} Score</label>
            <div class="mt-2">
                <select
                    name="team1_score"
                    id="team1_score"
                    class="block w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    v-model="form.team1_score"
                >
                    <option value="0">0</option>
                    <option value="1">1</option>
                    <option v-if="form.team2_score < 2" value="2">2</option>
                </select>
            </div>
        </div>
        <div class="sm:col-span-3">
            <label for="team2_score" class="block text-sm/6 font-medium text-foreground">{{ props.set.team2.name }} Score</label>
            <div class="mt-2">
                <select
                    name="team2_score"
                    id="team2_score"
                    class="block w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    v-model="form.team2_score"
                >
                    <option value="0">0</option>
                    <option value="1">1</option>
                    <option v-if="form.team1_score < 2" value="2">2</option>
                </select>
            </div>
        </div>
        <div class="sm:col-span-3">
            <label for="team1_pokepaste" class="block text-sm/6 font-medium text-foreground"
                >{{ props.set.team1.name }} Pokepaste</label
            >
            <div class="mt-2">
                <input
                    type="text"
                    name="team1_pokepaste"
                    id="team1_pokepaste"
                    class="block w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    v-model="form.team1_pokepaste"
                />
                <Link :href="`/teams/form/${props.set.team1.id}`">
                    <p class="text-center text-sm text-muted-foreground transition-colors hover:text-primary">
                        {{ form.team1_pokepaste }}
                    </p>
                </Link>
            </div>
        </div>
        <div class="sm:col-span-3">
            <label for="team2_pokepaste" class="block text-sm/6 font-medium text-foreground"
                >{{ props.set.team2.name }} Pokepaste</label
            >
            <div class="mt-2">
                <input
                    type="text"
                    name="team2_pokepaste"
                    id="team2_pokepaste"
                    class="block w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    v-model="form.team2_pokepaste"
                />
                <Link :href="`/teams/form/${props.set.team2.id}`">
                    <p class="text-center text-sm text-muted-foreground transition-colors hover:text-primary">
                        {{ form.team2_pokepaste }}
                    </p>
                </Link>
            </div>
        </div>
        <div class="min-h-[38px] sm:col-span-6">
            <button
                type="submit"
                class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            >
                Update
            </button>
        </div>
    </form>
</template>
