<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface PokemonGameOption {
    value: string;
    label: string;
    generation: number;
}

interface Props {
    command: string;
    league_id: number;
    league_name: string;
    draft_date: string | null;
    set_start_date: string | null;
    set_frequency: number;
    enforce_round_count: boolean;
    round_count: number | null;
    draft_points: number;
    minimum_drafts: number;
    ban_enabled: boolean;
    bans_per_user: number | null;
    minimum_cost_to_ban: number | null;
    logo: string | null;
    pokemon_generation: number;
    pokemon_game: string;
    pokemon_game_options: PokemonGameOption[];
    pokemon_generation_options: number[];
}

const props = withDefaults(defineProps<Props>(), {
    league_name: '',
    draft_date: null,
    set_start_date: null,
    set_frequency: 1,
    enforce_round_count: false,
    round_count: null,
    draft_points: 80,
    minimum_drafts: 10,
    ban_enabled: false,
    bans_per_user: null,
    minimum_cost_to_ban: null,
    pokemon_generation: 9,
    pokemon_game: 'scarlet_violet',
    pokemon_game_options: () => [],
    pokemon_generation_options: () => [9],
});

function formatDateForInput(value: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    return isNaN(date.getTime()) ? '' : date.toISOString().slice(0, 10);
}

const form = useForm({
    league_id: props.league_id,
    name: props.league_name,
    draft_date: formatDateForInput(props.draft_date) || new Date().toISOString().slice(0, 10),
    set_start_date: formatDateForInput(props.set_start_date) || new Date().toISOString().slice(0, 10),
    set_frequency: props.set_frequency,
    enforce_round_count: props.enforce_round_count,
    round_count: props.round_count,
    draft_points: props.draft_points,
    minimum_drafts: props.minimum_drafts,
    ban_enabled: props.ban_enabled,
    bans_per_user: props.bans_per_user,
    minimum_cost_to_ban: props.minimum_cost_to_ban,
    logo: props.logo as File | null,
    pokemon_generation: props.pokemon_generation,
    pokemon_game: props.pokemon_game,
});

const gamesForSelectedGeneration = computed(() =>
    props.pokemon_game_options.filter((g) => g.generation === form.pokemon_generation),
);

watch(
    () => form.pokemon_generation,
    () => {
        const ok = gamesForSelectedGeneration.value.some((g) => g.value === form.pokemon_game);
        if (!ok && gamesForSelectedGeneration.value[0]) {
            form.pokemon_game = gamesForSelectedGeneration.value[0].value;
        }
    },
);

const frequencyOptions = [
    { label: 'Daily', value: 1 },
    { label: 'Twice Weekly', value: 2 },
    { label: 'Weekly', value: 3 },
    { label: 'Custom Frequency', value: 4 },
];

const submit = () => {
    form.post(route('leagues.create'), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head :title="props.command === 'create' ? 'Create League' : 'Edit League'" />
    <AppLayout>
        <div class="mx-auto mt-8 mb-8 flex max-w-2xl flex-col items-center">
            <h1 class="mb-8 text-3xl font-bold">{{ props.command === 'create' ? 'Create League' : 'Edit League' }}</h1>
            <form @submit.prevent="submit" class="w-full max-w-md space-y-6">
                <div class="grid gap-2">
                    <Label for="name">League Name</Label>
                    <Input id="name" type="text" v-model="form.name" placeholder="e.g. Spring 2025 VGC League" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="draft_date">Draft Date</Label>
                    <Input id="draft_date" type="date" v-model="form.draft_date" required />
                    <InputError :message="form.errors.draft_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="set_start_date">Set Start Date</Label>
                    <Input id="set_start_date" type="date" v-model="form.set_start_date" required />
                    <InputError :message="form.errors.set_start_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="set_frequency">Set Frequency</Label>
                    <select
                        id="set_frequency"
                        v-model.number="form.set_frequency"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option v-for="option in frequencyOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.set_frequency" />
                </div>

                <div class="grid gap-2">
                    <Label for="pokemon_generation">Pokémon generation</Label>
                    <select
                        id="pokemon_generation"
                        v-model.number="form.pokemon_generation"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option v-for="g in pokemon_generation_options" :key="g" :value="g">Generation {{ g }}</option>
                    </select>
                    <InputError :message="form.errors.pokemon_generation" />
                </div>

                <div class="grid gap-2">
                    <Label for="pokemon_game">Ruleset (main series game)</Label>
                    <select
                        id="pokemon_game"
                        v-model="form.pokemon_game"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option v-for="opt in gamesForSelectedGeneration" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.pokemon_game" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center gap-2">
                        <input
                            id="enforce_round_count"
                            type="checkbox"
                            v-model="form.enforce_round_count"
                            class="size-4 rounded border-input accent-primary"
                        />
                        <Label for="enforce_round_count">Enforce number of rounds</Label>
                    </div>
                    <InputError :message="form.errors.enforce_round_count" />
                </div>

                <div v-if="form.enforce_round_count" class="grid gap-2">
                    <Label for="round_count">Number of Rounds</Label>
                    <Input id="round_count" type="number" v-model.number="form.round_count" min="1" required />
                    <InputError :message="form.errors.round_count" />
                </div>

                <div class="grid gap-2">
                    <Label for="draft_points">Draft Points</Label>
                    <Input id="draft_points" type="number" v-model.number="form.draft_points" min="1" required />
                    <InputError :message="form.errors.draft_points" />
                </div>

                <div class="grid gap-2">
                    <Label for="minimum_drafts">Minimum Number of Pokemon</Label>
                    <Input id="minimum_drafts" type="number" v-model.number="form.minimum_drafts" min="1" required />
                    <InputError :message="form.errors.minimum_drafts" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center gap-2">
                        <input
                            id="ban_enabled"
                            type="checkbox"
                            v-model="form.ban_enabled"
                            class="size-4 rounded border-input accent-primary"
                        />
                        <Label for="ban_enabled">Enable bans</Label>
                    </div>
                    <InputError :message="form.errors.ban_enabled" />
                </div>

                <div v-if="form.ban_enabled" class="grid gap-2">
                    <Label for="bans_per_user">Bans Per User</Label>
                    <Input id="bans_per_user" type="number" v-model.number="form.bans_per_user" min="1" required />
                    <InputError :message="form.errors.bans_per_user" />
                </div>

                <div v-if="form.ban_enabled" class="grid gap-2">
                    <Label for="minimum_cost_to_ban">Minimum Cost to Ban</Label>
                    <Input id="minimum_cost_to_ban" type="number" v-model.number="form.minimum_cost_to_ban" min="0" required />
                    <InputError :message="form.errors.minimum_cost_to_ban" />
                </div>

                <div class="grid gap-2">
                    <Label for="logo">League Logo (optional)</Label>
                    <Input
                        id="logo"
                        type="file"
                        accept="image/jpeg,image/png,image/jpg,image/gif,image/svg+xml"
                        @input="form.logo = ($event.target as HTMLInputElement)?.files?.[0] || null"
                    />
                    <InputError :message="form.errors.logo" />
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 shrink-0 animate-spin" />
                    {{ props.command === 'create' ? 'Create League' : 'Update League' }}
                </Button>
            </form>
        </div>
    </AppLayout>
</template>
