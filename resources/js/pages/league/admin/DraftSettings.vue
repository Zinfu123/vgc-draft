<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/league/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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

interface DraftConfigPayload {
    id: number;
    league_id: number;
    draft_date: string | null;
    draft_points: number;
    minimum_drafts: number;
    ban_enabled: boolean;
    bans_per_user: number | null;
    minimum_cost_to_ban: number | null;
}

interface Team {
    id: number;
    name: string;
    coach: string;
    pick_position: number;
}

const props = defineProps<{
    league: League;
    draftConfig: DraftConfigPayload;
    teams: Team[];
    canReorderPicks: boolean;
}>();

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leagues', href: '/leagues' },
    { title: props.league.name, href: `/leagues/${props.league.id}` },
    { title: 'Admin', href: '#' },
];

function formatDraftDateInput(d: string | null | undefined): string {
    if (!d) {
        return '';
    }
    return String(d).slice(0, 10);
}

const configForm = useForm({
    draft_date: formatDraftDateInput(props.draftConfig.draft_date),
    draft_points: props.draftConfig.draft_points,
    minimum_drafts: props.draftConfig.minimum_drafts,
    ban_enabled: Boolean(props.draftConfig.ban_enabled),
    bans_per_user: props.draftConfig.bans_per_user ?? 1,
    minimum_cost_to_ban: props.draftConfig.minimum_cost_to_ban ?? 0,
});

watch(
    () => props.draftConfig,
    (c) => {
        configForm.draft_date = formatDraftDateInput(c.draft_date);
        configForm.draft_points = c.draft_points;
        configForm.minimum_drafts = c.minimum_drafts;
        configForm.ban_enabled = Boolean(c.ban_enabled);
        configForm.bans_per_user = c.bans_per_user ?? 1;
        configForm.minimum_cost_to_ban = c.minimum_cost_to_ban ?? 0;
    },
    { deep: true },
);

const submitConfig = () => {
    configForm.patch(route('leagues.admin.draft-config.update', { league: props.league.id }), {
        preserveScroll: true,
    });
};

const orderedTeams = ref<Team[]>([...props.teams]);

watch(
    () => props.teams,
    (teams) => {
        orderedTeams.value = [...teams];
    },
    { deep: true },
);

let dragPickIndex: number | null = null;

function onPickDragStart(index: number): void {
    dragPickIndex = index;
}

function onPickDrop(index: number): void {
    if (dragPickIndex === null) {
        return;
    }
    const next = [...orderedTeams.value];
    const [removed] = next.splice(dragPickIndex, 1);
    next.splice(index, 0, removed);
    orderedTeams.value = next;
    dragPickIndex = null;
}

const pickOrderForm = useForm({
    team_ids: [] as number[],
});

const savePickOrder = () => {
    pickOrderForm.team_ids = orderedTeams.value.map((t) => t.id);
    pickOrderForm.patch(route('leagues.admin.draft-pick-order.update', { league: props.league.id }), {
        preserveScroll: true,
    });
};

const flashSuccess = computed(() => (page.props as FlashProps).flash?.success ?? null);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${props.league.name} — Draft settings`" />

        <AdminLayout :league-id="props.league.id" :league-name="props.league.name">
            <div class="flex flex-col space-y-10">
                <p
                    v-if="flashSuccess"
                    class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-950/40 dark:text-green-200"
                >
                    {{ flashSuccess }}
                </p>

                <div class="flex flex-col space-y-6">
                    <HeadingSmall title="Draft configuration" description="Draft date, points, minimum picks, and ban rules." />

                    <form class="flex flex-col gap-4" @submit.prevent="submitConfig">
                        <div class="flex flex-col gap-1">
                            <Label for="draft_date">Draft date</Label>
                            <Input id="draft_date" v-model="configForm.draft_date" type="date" />
                            <p v-if="configForm.errors.draft_date" class="text-sm text-destructive">{{ configForm.errors.draft_date }}</p>
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label for="draft_points">Draft points (per team)</Label>
                            <Input id="draft_points" v-model.number="configForm.draft_points" type="number" min="1" />
                            <p v-if="configForm.errors.draft_points" class="text-sm text-destructive">{{ configForm.errors.draft_points }}</p>
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label for="minimum_drafts">Minimum drafts (per team)</Label>
                            <Input id="minimum_drafts" v-model.number="configForm.minimum_drafts" type="number" min="0" />
                            <p v-if="configForm.errors.minimum_drafts" class="text-sm text-destructive">{{ configForm.errors.minimum_drafts }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                id="ban_enabled"
                                v-model="configForm.ban_enabled"
                                type="checkbox"
                                class="size-4 rounded border-input"
                            />
                            <Label for="ban_enabled" class="font-normal">Ban phase enabled</Label>
                        </div>
                        <template v-if="configForm.ban_enabled">
                            <div class="flex flex-col gap-1">
                                <Label for="bans_per_user">Bans per user</Label>
                                <Input id="bans_per_user" v-model.number="configForm.bans_per_user" type="number" min="1" />
                                <p v-if="configForm.errors.bans_per_user" class="text-sm text-destructive">{{ configForm.errors.bans_per_user }}</p>
                            </div>
                            <div class="flex flex-col gap-1">
                                <Label for="minimum_cost_to_ban">Minimum cost to ban</Label>
                                <Input id="minimum_cost_to_ban" v-model.number="configForm.minimum_cost_to_ban" type="number" min="0" />
                                <p v-if="configForm.errors.minimum_cost_to_ban" class="text-sm text-destructive">
                                    {{ configForm.errors.minimum_cost_to_ban }}
                                </p>
                            </div>
                        </template>
                        <div class="flex justify-start pt-2">
                            <Button type="submit" :disabled="configForm.processing">Save draft configuration</Button>
                        </div>
                    </form>
                </div>

                <div class="flex flex-col space-y-6">
                    <HeadingSmall title="Draft pick order" description="Snake draft order follows pick position (1 = first pick in round 1)." />
                    <template v-if="canReorderPicks">
                        <p class="text-xs text-muted-foreground">Drag teams to reorder. Top of the list is pick position 1.</p>
                        <ul class="divide-y divide-border rounded-md border border-border bg-card">
                            <li
                                v-for="(team, index) in orderedTeams"
                                :key="team.id"
                                draggable="true"
                                class="flex cursor-grab items-center justify-between gap-2 px-3 py-2 text-sm active:cursor-grabbing"
                                @dragstart="onPickDragStart(index)"
                                @dragover.prevent
                                @drop="onPickDrop(index)"
                            >
                                <span class="font-medium">{{ index + 1 }}. {{ team.name }}</span>
                                <span class="text-muted-foreground">{{ team.coach }}</span>
                            </li>
                        </ul>
                        <p v-if="pickOrderForm.errors.team_ids" class="text-sm text-destructive">{{ pickOrderForm.errors.team_ids }}</p>
                        <Button type="button" :disabled="pickOrderForm.processing || orderedTeams.length === 0" @click="savePickOrder">
                            Save pick order
                        </Button>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">
                        Pick order is locked while a draft exists for this league. Abort the draft from the draft screen if you need to reorder picks
                        before starting again.
                    </p>
                </div>
            </div>
        </AdminLayout>
    </AppLayout>
</template>
