<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

interface Pool {
    id: number;
    name: string | null;
    league_id: number;
}

defineProps<{
    pool: Pool | null;
}>();
</script>

<template>
    <Head title="Pool" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">Pool</h1>

            <EmptyState v-if="!pool" title="Pool not found" description="This pool could not be loaded." />

            <div v-else class="rounded-md border px-3 py-2">
                <p class="font-medium">{{ pool.name ?? `Pool #${pool.id}` }}</p>
            </div>
        </div>
    </AppLayout>
</template>
