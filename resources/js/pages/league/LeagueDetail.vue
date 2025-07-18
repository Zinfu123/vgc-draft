<script setup lang="ts">
import { type BreadcrumbItem } from '@/types';

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
}

interface props {
    league: League;
}

const props = defineProps<props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Leagues',
        href: '/Leagues',
    },
    {
        title: 'Leagues',
        href: `/Leagues/${props.league.name}`,
    },
];
</script>
<template>
    <Head :title="`${props.league.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col max-w-4xl mx-auto items-center">
            <h1 class="text-3xl font-bold">{{ props.league.name }}</h1>
            <button @click="createTeam" class="bg-blue-500 text-white p-2 rounded-md">Create Team</button>
        </div>
    </AppLayout>
</template>