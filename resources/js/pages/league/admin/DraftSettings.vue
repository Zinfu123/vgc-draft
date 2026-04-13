<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import CommissionerSubNav from '@/components/league/CommissionerSubNav.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface FlashProps {
    flash?: { success?: string | null };
}

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
    league_owner: number;
    status: number;
    playoffs_enabled: boolean;
}

interface DraftConfigPayload {
    id: number;
    league_id: number;
    draft_date: string | null;
    draft_start_at: string | null;
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
    user_id: number;
}

interface Draft {
    id: number | null;
    round_number: number;
    pick_number: number;
    status: number;
}

interface MatchConfig {
    id: number;
    league_id: number;
    number_of_pools: number;
    frequency_type: number;
    frequency_value: number;
    status: number;
}

const props = defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
    draftConfig: DraftConfigPayload;
    canReorderPicks: boolean;
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props as FlashProps).flash?.success ?? null);

function formatDraftDateInput(d: string | null | undefined): string {
    if (!d) return '';
    return String(d).slice(0, 10);
}

function toDatetimeLocalValue(utcStr: string | null | undefined): string {
    if (!utcStr) return '';
    const d = new Date(utcStr);
    if (isNaN(d.getTime())) return '';
    const offset = d.getTimezoneOffset() * 60000;
    return new Date(d.getTime() - offset).toISOString().slice(0, 16);
}

const configForm = useForm({
    draft_date: formatDraftDateInput(props.draftConfig.draft_date),
    draft_start_at: toDatetimeLocalValue(props.draftConfig.draft_start_at),
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
        configForm.draft_start_at = toDatetimeLocalValue(c.draft_start_at);
        configForm.draft_points = c.draft_points;
        configForm.minimum_drafts = c.minimum_drafts;
        configForm.ban_enabled = Boolean(c.ban_enabled);
        configForm.bans_per_user = c.bans_per_user ?? 1;
        configForm.minimum_cost_to_ban = c.minimum_cost_to_ban ?? 0;
    },
    { deep: true },
);

const submitConfig = () => {
    configForm
        .transform((data) => ({
            ...data,
            draft_start_at: data.draft_start_at ? new Date(data.draft_start_at).toISOString() : null,
        }))
        .patch(route('leagues.admin.draft-config.update', { league: props.league.id }), {
            preserveScroll: true,
        });
};

const orderedTeams = ref<Team[]>([...props.teams]);

watch(
    () => props.teams,
    (teams) => { orderedTeams.value = [...teams]; },
    { deep: true },
);

let dragPickIndex: number | null = null;

function onPickDragStart(index: number): void {
    dragPickIndex = index;
}

function onPickDrop(index: number): void {
    if (dragPickIndex === null) return;
    const next = [...orderedTeams.value];
    const [removed] = next.splice(dragPickIndex, 1);
    next.splice(index, 0, removed);
    orderedTeams.value = next;
    dragPickIndex = null;
}

const pickOrderForm = useForm({ team_ids: [] as number[] });

const savePickOrder = () => {
    pickOrderForm.team_ids = orderedTeams.value.map((t) => t.id);
    pickOrderForm.patch(route('leagues.admin.draft-pick-order.update', { league: props.league.id }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <LeagueDetailLayout
        :league="league"
        section="commissioner"
        :teams="teams"
        :draft="draft"
        :adminFlag="adminFlag"
        :matchConfig="matchConfig"
    >
        <Head :title="`Draft Settings · ${league.name}`" />

        <div class="flex flex-col gap-8">
            <CommissionerSubNav :league="league" />

            <div
                v-if="flashSuccess"
                class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950/40 dark:text-green-300"
                role="status"
            >
                <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                {{ flashSuccess }}
            </div>

            <!-- Draft Config -->
            <section class="flex flex-col gap-6">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">Draft Configuration</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">Draft date, points, minimum picks, and ban rules.</p>
                </div>

                <form class="flex max-w-md flex-col gap-4" @submit.prevent="submitConfig">
                    <div class="flex flex-col gap-1.5">
                        <Label for="draft_date">Draft date</Label>
                        <Input id="draft_date" v-model="configForm.draft_date" type="date" />
                        <p v-if="configForm.errors.draft_date" class="text-sm text-destructive">{{ configForm.errors.draft_date }}</p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="draft_start_at">Scheduled draft start (optional)</Label>
                        <Input id="draft_start_at" v-model="configForm.draft_start_at" type="datetime-local" />
                        <p class="text-xs text-muted-foreground">
                            The draft will start automatically at this time (your local timezone). Leave empty to start manually.
                        </p>
                        <p v-if="configForm.errors.draft_start_at" class="text-sm text-destructive">{{ configForm.errors.draft_start_at }}</p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="draft_points">Draft points (per team)</Label>
                        <Input id="draft_points" v-model.number="configForm.draft_points" type="number" min="1" />
                        <p v-if="configForm.errors.draft_points" class="text-sm text-destructive">{{ configForm.errors.draft_points }}</p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="minimum_drafts">Minimum drafts (per team)</Label>
                        <Input id="minimum_drafts" v-model.number="configForm.minimum_drafts" type="number" min="0" />
                        <p v-if="configForm.errors.minimum_drafts" class="text-sm text-destructive">{{ configForm.errors.minimum_drafts }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input id="ban_enabled" v-model="configForm.ban_enabled" type="checkbox" class="size-4 rounded border-input accent-primary" />
                        <Label for="ban_enabled" class="font-normal">Ban phase enabled</Label>
                    </div>
                    <template v-if="configForm.ban_enabled">
                        <div class="flex flex-col gap-1.5">
                            <Label for="bans_per_user">Bans per user</Label>
                            <Input id="bans_per_user" v-model.number="configForm.bans_per_user" type="number" min="1" />
                            <p v-if="configForm.errors.bans_per_user" class="text-sm text-destructive">{{ configForm.errors.bans_per_user }}</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="minimum_cost_to_ban">Minimum cost to ban</Label>
                            <Input id="minimum_cost_to_ban" v-model.number="configForm.minimum_cost_to_ban" type="number" min="0" />
                            <p v-if="configForm.errors.minimum_cost_to_ban" class="text-sm text-destructive">{{ configForm.errors.minimum_cost_to_ban }}</p>
                        </div>
                    </template>
                    <div class="flex pt-2">
                        <Button type="submit" :disabled="configForm.processing">Save draft configuration</Button>
                    </div>
                </form>
            </section>

            <!-- Pick Order -->
            <section class="flex flex-col gap-6">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">Draft Pick Order</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">Snake draft order follows pick position (1 = first pick in round 1).</p>
                </div>

                <template v-if="canReorderPicks">
                    <p class="text-xs text-muted-foreground">Drag teams to reorder. Top of the list is pick position 1.</p>
                    <ul class="max-w-sm divide-y divide-border overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                        <li
                            v-for="(team, index) in orderedTeams"
                            :key="team.id"
                            draggable="true"
                            class="flex cursor-grab items-center gap-3 px-3 py-2.5 text-sm transition-colors hover:bg-muted/30 active:cursor-grabbing"
                            @dragstart="onPickDragStart(index)"
                            @dragover.prevent
                            @drop="onPickDrop(index)"
                        >
                            <span class="w-5 shrink-0 text-center text-xs font-mono text-muted-foreground">{{ index + 1 }}</span>
                            <svg class="size-4 shrink-0 text-muted-foreground/50" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M8 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM8 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM8 22a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM16 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM16 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM16 22a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/>
                            </svg>
                            <span class="min-w-0 flex-1 font-medium text-foreground">{{ team.name }}</span>
                            <span class="shrink-0 text-xs text-muted-foreground">{{ team.coach }}</span>
                        </li>
                    </ul>
                    <p v-if="pickOrderForm.errors.team_ids" class="text-sm text-destructive">{{ pickOrderForm.errors.team_ids }}</p>
                    <div class="flex">
                        <Button type="button" :disabled="pickOrderForm.processing || orderedTeams.length === 0" @click="savePickOrder">
                            Save pick order
                        </Button>
                    </div>
                </template>
                <p v-else class="text-sm text-muted-foreground">
                    Pick order is locked while a draft exists for this league. Abort the draft from the draft screen if you need to reorder picks
                    before starting again.
                </p>
            </section>
        </div>
    </LeagueDetailLayout>
</template>
