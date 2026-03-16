<script setup lang="ts">
import LeagueCarousel from '@/components/league/LeagueCarousel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface League {
    id: number;
    name: string;
    status: number;
    draft_date: string;
    set_start_date: string;
    logo: string | null;
    winner: string | null;
}

interface OpenLeague {
    id: number;
    name: string;
    draft_date: string;
    set_start_date: string;
    logo: string | null;
    winner: null;
}

interface Props {
    usersActiveLeagues: League[];
    usersPastLeagues: League[];
    openLeagues: OpenLeague[];
}

const props = defineProps<Props>();
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-6">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                <section>
                    <h2 class="mb-4 text-2xl font-bold">My Active Leagues</h2>
                    <div v-if="props.usersActiveLeagues.length > 0" class="grid grid-cols-[repeat(auto-fill,minmax(16rem,1fr))] gap-4">
                        <LeagueCarousel :leagues="props.usersActiveLeagues" />
                    </div>
                    <p v-else class="text-muted-foreground">You are not currently in any active leagues.</p>
                </section>

                <section>
                    <h2 class="mb-4 text-2xl font-bold">My Past Leagues</h2>
                    <div v-if="props.usersPastLeagues.length > 0" class="grid grid-cols-[repeat(auto-fill,minmax(16rem,1fr))] gap-4">
                        <LeagueCarousel :leagues="props.usersPastLeagues" />
                    </div>
                    <p v-else class="text-muted-foreground">You have no past leagues.</p>
                </section>
            </div>

            <section>
                <h2 class="mb-4 text-2xl font-bold">Open Leagues</h2>
                <div v-if="props.openLeagues.length > 0" class="grid grid-cols-[repeat(auto-fill,minmax(16rem,1fr))] gap-4">
                    <LeagueCarousel :leagues="props.openLeagues" />
                </div>
                <p v-else class="text-muted-foreground">There are no open leagues available to join.</p>
            </section>
        </div>
    </AppLayout>
</template>
