<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

interface Team {
    id: number;
    name: string;
    league_id: number;
}

defineProps<{
    Teams: Team[];
}>();
</script>

<template>
    <Head title="Teams" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">Teams</h1>

            <EmptyState v-if="Teams.length === 0" title="No teams" description="No teams found for this league." />

            <ul v-else class="flex flex-col gap-2">
                <li v-for="team in Teams" :key="team.id" class="rounded-md border px-3 py-2">
                    {{ team.name }}
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
