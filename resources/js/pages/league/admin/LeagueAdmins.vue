<script setup lang="ts">
import AuthAlert from '@/components/auth/AuthAlert.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/league/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
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
    isLeagueAdmin: boolean;
}>();

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leagues', href: '/leagues' },
    { title: props.league.name, href: `/leagues/${props.league.id}` },
    { title: 'Admin', href: '#' },
];

const processingTeamId = ref<number | null>(null);
const dropDialogOpen = ref(false);
const teamToDrop = ref<Team | null>(null);

const dropForm = useForm({ team_id: 0 });

function openDropDialog(team: Team): void {
    if (!props.isLeagueAdmin) {
        return;
    }
    teamToDrop.value = team;
    dropDialogOpen.value = true;
}

function confirmDropTeam(): void {
    if (!teamToDrop.value) {
        return;
    }
    dropForm.team_id = teamToDrop.value.id;
    dropForm.post(route('leagues.admin.drop-team', { league: props.league.id }), {
        preserveScroll: true,
        onSuccess: () => {
            dropDialogOpen.value = false;
            teamToDrop.value = null;
        },
        onFinish: () => {
            dropForm.reset();
        },
    });
}

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
        <Head :title="`${props.league.name} — User Management`" />

        <AdminLayout :league-id="props.league.id" :league-name="props.league.name">
            <div class="flex flex-col gap-6">
                <AuthAlert v-if="flashSuccess" :message="flashSuccess" />

                <HeadingSmall
                    title="User Management"
                    description="League owners always have full admin access. Grant co-admin access to coaches so they can use admin settings and tools."
                />

                <p v-if="!isLeagueOwner" class="text-sm text-muted-foreground">
                    Only the league owner can change who is a co-admin.
                </p>

                <div v-if="teams.length === 0" class="rounded-lg border border-dashed border-border py-10 text-center text-sm text-muted-foreground">
                    No teams in this league yet.
                </div>

                <ul v-else class="flex flex-col divide-y divide-border overflow-hidden rounded-lg border border-border bg-card">
                    <li
                        v-for="team in teams"
                        :key="team.id"
                        class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 px-4 py-3.5 transition-colors hover:bg-muted/30"
                    >
                        <!-- Team info -->
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-foreground">{{ team.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ team.coach }}</p>
                        </div>

                        <!-- Role badge -->
                        <span
                            v-if="team.admin_flag === 1"
                            class="shrink-0 rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
                        >
                            Co-admin
                        </span>
                        <span
                            v-else
                            class="shrink-0 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                        >
                            Coach
                        </span>

                        <!-- Actions -->
                        <div class="flex shrink-0 items-center gap-3">
                            <div v-if="isLeagueOwner" class="flex items-center gap-2">
                                <Checkbox
                                    :id="`admin-${team.id}`"
                                    :checked="team.admin_flag === 1"
                                    :disabled="processingTeamId === team.id"
                                    @update:checked="setTeamAdmin(team, $event)"
                                />
                                <label
                                    :for="`admin-${team.id}`"
                                    class="cursor-pointer select-none text-sm text-muted-foreground"
                                    :class="{ 'opacity-50': processingTeamId === team.id }"
                                >
                                    Co-admin
                                </label>
                            </div>

                            <Button
                                v-if="isLeagueAdmin"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                                :disabled="dropForm.processing"
                                @click="openDropDialog(team)"
                            >
                                Drop
                            </Button>
                        </div>
                    </li>
                </ul>
            </div>
        </AdminLayout>

        <!-- Drop team confirmation dialog -->
        <Dialog v-model:open="dropDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Drop team from league?</DialogTitle>
                <DialogDescription>
                    <template v-if="teamToDrop">
                        Removing <strong class="text-foreground">{{ teamToDrop.name }}</strong> will return their Pokémon to
                        the pool, convert their scheduled sets to byes, and may reset playoffs. This cannot be undone.
                    </template>
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button type="button" variant="outline" @click="dropDialogOpen = false">Cancel</Button>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="dropForm.processing"
                    @click="confirmDropTeam"
                >
                    Drop team
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
    </AppLayout>
</template>
