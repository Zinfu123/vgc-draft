<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/league/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface League {
    id: number;
    name: string;
    logo?: string;
}

interface Team {
    id: number;
    name: string;
    coach: string;
    user_id: number;
    trades: number;
}

const props = defineProps<{
    league: League;
    teams: Team[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leagues', href: '/leagues' },
    { title: props.league.name, href: `/leagues/${props.league.id}` },
    { title: 'Admin', href: '#' },
];

const tradesForm = useForm({
    trades: 0,
});

const handleTradesSubmit = () => {
    tradesForm.post(route('leagues.trades.set-team-trades', { league: props.league.id }));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${props.league.name} — Admin`" />

        <AdminLayout :league-id="props.league.id" :league-name="props.league.name">
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Trades"
                    description="Set the number of trades for all teams. Each trade is consumed per Pokémon received in an accepted trade."
                />

                <div class="rounded-md border border-border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border bg-muted/50">
                                <th class="px-4 py-2 text-left font-medium">Team</th>
                                <th class="px-4 py-2 text-left font-medium">Coach</th>
                                <th class="px-4 py-2 text-right font-medium">Remaining Trades</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="team in props.teams" :key="team.id" class="border-b border-border last:border-0">
                                <td class="px-4 py-2">{{ team.name }}</td>
                                <td class="px-4 py-2 text-muted-foreground">{{ team.coach }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ team.trades ?? 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form @submit.prevent="handleTradesSubmit" class="flex items-end gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium" for="trades">Set trades for all teams</label>
                        <Input id="trades" type="number" v-model="tradesForm.trades" min="0" class="w-32" />
                        <p v-if="tradesForm.errors.trades" class="text-sm text-destructive">{{ tradesForm.errors.trades }}</p>
                    </div>
                    <Button type="submit" :disabled="tradesForm.processing">Apply to All Teams</Button>
                </form>
            </div>
        </AdminLayout>
    </AppLayout>
</template>
