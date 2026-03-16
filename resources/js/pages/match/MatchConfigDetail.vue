<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface MatchConfig {
    id: number;
    league_id: number;
    number_of_pools: number;
    frequency_type: number;
    frequency_value: number;
    status: number;
}

const props = defineProps<{
    matchConfig: MatchConfig | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leagues', href: '/leagues' },
    { title: 'Match Config', href: '#' },
];

const form = useForm({
    league_id: props.matchConfig?.league_id ?? null,
    number_of_pools: props.matchConfig?.number_of_pools ?? 1,
    frequency_type: props.matchConfig?.frequency_type ?? 1,
    frequency_value: props.matchConfig?.frequency_value ?? null,
    command: props.matchConfig?.id ? 'update' : 'create',
});

const handleSubmit = () => {
    form.post(route('leagues.match-config.create-edit-show', { league: form.league_id }));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Match Config" />
        <div class="mx-auto w-full max-w-2xl px-4 pt-8 pb-10 sm:px-6 lg:px-8">
            <h1 class="mb-6 text-2xl font-bold">Match Configuration</h1>

            <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium" for="number_of_pools">Number of Pools</label>
                    <Input id="number_of_pools" type="number" v-model="form.number_of_pools" min="1" />
                    <p v-if="form.errors.number_of_pools" class="text-sm text-destructive">{{ form.errors.number_of_pools }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium" for="frequency_type">Frequency Type</label>
                    <select
                        id="frequency_type"
                        v-model="form.frequency_type"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none dark:bg-background"
                    >
                        <option :value="1">Daily</option>
                        <option :value="2">Weekly</option>
                        <option :value="3">Single Day</option>
                        <option :value="4">Custom</option>
                    </select>
                    <p v-if="form.errors.frequency_type" class="text-sm text-destructive">{{ form.errors.frequency_type }}</p>
                </div>

                <div v-if="form.frequency_type === 4" class="flex flex-col gap-1">
                    <label class="text-sm font-medium" for="frequency_value">Frequency Value</label>
                    <Input id="frequency_value" type="number" v-model="form.frequency_value" min="1" />
                    <p v-if="form.errors.frequency_value" class="text-sm text-destructive">{{ form.errors.frequency_value }}</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" @click="() => history.back()">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.command === 'create' ? 'Create' : 'Update' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
