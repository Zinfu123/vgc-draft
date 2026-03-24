<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/league/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface FlashProps {
    flash?: {
        success?: string | null;
    };
}

interface League {
    id: number;
    name: string;
    logo?: string;
}

interface Team {
    id: number;
    name: string;
    coach: string;
    admin_flag: number;
}

const props = defineProps<{
    league: League;
    teams: Team[];
    isLeagueOwner: boolean;
}>();

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leagues', href: '/leagues' },
    { title: props.league.name, href: `/leagues/${props.league.id}` },
    { title: 'Admin', href: '#' },
];

const processingTeamId = ref<number | null>(null);

const flashSuccess = computed(() => (page.props as FlashProps).flash?.success ?? null);

function setTeamAdmin(team: Team, checked: boolean): void {
    if (!props.isLeagueOwner) {
        return;
    }
    processingTeamId.value = team.id;
    router.patch(
        route('leagues.admin.team-admin.update', { league: props.league.id }),
        {
            team_id: team.id,
            admin_flag: checked,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processingTeamId.value = null;
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${props.league.name} — League admins`" />

        <AdminLayout :league-id="props.league.id" :league-name="props.league.name">
            <div class="flex flex-col space-y-6">
                <p
                    v-if="flashSuccess"
                    class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-950/40 dark:text-green-200"
                >
                    {{ flashSuccess }}
                </p>

                <HeadingSmall
                    title="League admins"
                    description="League owners always have full admin access. Grant co-admin access to coaches so they can use admin settings and tools."
                />

                <p v-if="!isLeagueOwner" class="text-sm text-muted-foreground">
                    Only the league owner can change who is a co-admin.
                </p>

                <ul class="divide-y divide-border rounded-md border border-border bg-card">
                    <li v-for="team in teams" :key="team.id" class="flex flex-wrap items-center justify-between gap-3 px-3 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-medium">{{ team.name }}</p>
                            <p class="text-muted-foreground">{{ team.coach }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                :id="`admin-${team.id}`"
                                type="checkbox"
                                class="size-4 rounded border-input"
                                :checked="team.admin_flag === 1"
                                :disabled="!isLeagueOwner || processingTeamId === team.id"
                                @change="setTeamAdmin(team, ($event.target as HTMLInputElement).checked)"
                            />
                            <Label :for="`admin-${team.id}`" class="cursor-pointer font-normal">Co-admin</Label>
                        </div>
                    </li>
                </ul>
            </div>
        </AdminLayout>
    </AppLayout>
</template>
