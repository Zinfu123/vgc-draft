<script setup lang="ts">
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Leagues',
        href: '/Leagues',
    },
];
interface League {
    id: number;
    name: string;
}

interface ParticipatingLeague {
    id: number;
    name: string;
}

interface props {
    league: League[];
}

const props = defineProps<props>();

</script>

<template>
    <Head title="Leagues" />
    <AppLayout :breadcrumbs="breadcrumbs">
</AppLayout>
</template>