<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import CommissionerSubNav from '@/components/league/CommissionerSubNav.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Play } from 'lucide-vue-next';
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
    pick_timer_enabled: boolean;
    pick_timer_seconds: number | null;
    quiet_hours_enabled: boolean;
    quiet_hours_start: string | null;
    quiet_hours_end: string | null;
    quiet_hours_timezone: string | null;
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
    canStartDraft: boolean;
    draftExists: boolean;
    activeTeamCount: number;
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

function toTimeInputValue(value: string | null | undefined): string {
    if (!value) return '';
    return value.slice(0, 5);
}

function pickTimerSecondsToMinutes(value: number | null | undefined): number {
    if (!value || value < 60) return 120;
    return Math.round(value / 60);
}

const configForm = useForm({
    draft_date: formatDraftDateInput(props.draftConfig.draft_date),
    draft_start_at: toDatetimeLocalValue(props.draftConfig.draft_start_at),
    draft_points: props.draftConfig.draft_points,
    minimum_drafts: props.draftConfig.minimum_drafts,
    ban_enabled: Boolean(props.draftConfig.ban_enabled),
    bans_per_user: props.draftConfig.bans_per_user ?? 1,
    minimum_cost_to_ban: props.draftConfig.minimum_cost_to_ban ?? 0,
    pick_timer_enabled: Boolean(props.draftConfig.pick_timer_enabled),
    pick_timer_minutes: pickTimerSecondsToMinutes(props.draftConfig.pick_timer_seconds),
    quiet_hours_enabled: Boolean(props.draftConfig.quiet_hours_enabled),
    quiet_hours_start: toTimeInputValue(props.draftConfig.quiet_hours_start) || '00:00',
    quiet_hours_end: toTimeInputValue(props.draftConfig.quiet_hours_end) || '08:00',
    quiet_hours_timezone: props.draftConfig.quiet_hours_timezone ?? 'America/New_York',
});

const timerPresets: { label: string; minutes: number }[] = [
    { label: '30m', minutes: 30 },
    { label: '1h', minutes: 60 },
    { label: '2h', minutes: 120 },
    { label: '4h', minutes: 240 },
    { label: '8h', minutes: 480 },
    { label: '24h', minutes: 1440 },
];

const applyWeekendPreset = () => {
    configForm.pick_timer_enabled = true;
    configForm.pick_timer_minutes = 120;
    configForm.quiet_hours_enabled = true;
    configForm.quiet_hours_start = '00:00';
    configForm.quiet_hours_end = '08:00';
    configForm.quiet_hours_timezone = 'America/New_York';
};

const timezoneOptions = [
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'America/Anchorage',
    'Pacific/Honolulu',
    'UTC',
    'Europe/London',
    'Europe/Berlin',
    'Asia/Tokyo',
    'Australia/Sydney',
];

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
        configForm.pick_timer_enabled = Boolean(c.pick_timer_enabled);
        configForm.pick_timer_minutes = pickTimerSecondsToMinutes(c.pick_timer_seconds);
        configForm.quiet_hours_enabled = Boolean(c.quiet_hours_enabled);
        configForm.quiet_hours_start = toTimeInputValue(c.quiet_hours_start) || '00:00';
        configForm.quiet_hours_end = toTimeInputValue(c.quiet_hours_end) || '08:00';
        configForm.quiet_hours_timezone = c.quiet_hours_timezone ?? 'America/New_York';
    },
    { deep: true },
);

const submitConfig = () => {
    configForm
        .transform((data) => ({
            ...data,
            draft_start_at: data.draft_start_at ? new Date(data.draft_start_at).toISOString() : null,
            pick_timer_seconds: data.pick_timer_enabled ? Math.max(60, Math.round(data.pick_timer_minutes * 60)) : null,
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

const startDraftForm = useForm({ league_id: props.league.id });
const startDraftDialogOpen = ref(false);

const confirmStartDraft = () => {
    startDraftForm.post(route('draft.create'), {
        preserveScroll: false,
        onSuccess: () => {
            startDraftDialogOpen.value = false;
        },
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

                    <div class="mt-4 border-t border-border pt-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold">Pick timer</h3>
                                <p class="text-xs text-muted-foreground">If a team doesn't pick in time, their turn is skipped and the next team is up.</p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-md border border-primary/30 bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary transition-colors hover:bg-primary/20"
                                @click="applyWeekendPreset"
                            >
                                Weekend draft preset
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="pick_timer_enabled" v-model="configForm.pick_timer_enabled" type="checkbox" class="size-4 rounded border-input accent-primary" />
                        <Label for="pick_timer_enabled" class="font-normal">Enable pick timer</Label>
                    </div>
                    <template v-if="configForm.pick_timer_enabled">
                        <div class="flex flex-col gap-1.5">
                            <Label for="pick_timer_minutes">Minutes per pick</Label>
                            <Input id="pick_timer_minutes" v-model.number="configForm.pick_timer_minutes" type="number" min="1" />
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <button
                                    v-for="preset in timerPresets"
                                    :key="preset.label"
                                    type="button"
                                    class="rounded-md border border-border px-2 py-0.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted/60"
                                    :class="configForm.pick_timer_minutes === preset.minutes ? 'bg-primary text-primary-foreground hover:bg-primary' : ''"
                                    @click="configForm.pick_timer_minutes = preset.minutes"
                                >
                                    {{ preset.label }}
                                </button>
                            </div>
                            <p v-if="configForm.errors.pick_timer_seconds" class="text-sm text-destructive">{{ configForm.errors.pick_timer_seconds }}</p>
                        </div>
                    </template>

                    <div class="flex items-center gap-2">
                        <input id="quiet_hours_enabled" v-model="configForm.quiet_hours_enabled" type="checkbox" class="size-4 rounded border-input accent-primary" />
                        <Label for="quiet_hours_enabled" class="font-normal">Enable quiet hours (no auto-skip overnight)</Label>
                    </div>
                    <template v-if="configForm.quiet_hours_enabled">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <Label for="quiet_hours_start">Quiet hours start</Label>
                                <Input id="quiet_hours_start" v-model="configForm.quiet_hours_start" type="time" />
                                <p v-if="configForm.errors.quiet_hours_start" class="text-sm text-destructive">{{ configForm.errors.quiet_hours_start }}</p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label for="quiet_hours_end">Quiet hours end</Label>
                                <Input id="quiet_hours_end" v-model="configForm.quiet_hours_end" type="time" />
                                <p v-if="configForm.errors.quiet_hours_end" class="text-sm text-destructive">{{ configForm.errors.quiet_hours_end }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="quiet_hours_timezone">Timezone</Label>
                            <select
                                id="quiet_hours_timezone"
                                v-model="configForm.quiet_hours_timezone"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm"
                            >
                                <option v-for="tz in timezoneOptions" :key="tz" :value="tz">{{ tz }}</option>
                            </select>
                            <p class="text-xs text-muted-foreground">Picks are still allowed during quiet hours; only the auto-skip timer is frozen.</p>
                            <p v-if="configForm.errors.quiet_hours_timezone" class="text-sm text-destructive">{{ configForm.errors.quiet_hours_timezone }}</p>
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

            <!-- Start Draft -->
            <section class="flex flex-col gap-6">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">Start Draft</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Lock in the configuration and open the draft room. Notifications are sent to every coach as soon as the draft starts.
                    </p>
                </div>

                <div
                    class="flex flex-col gap-4 rounded-2xl border border-border bg-card p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex flex-col gap-1">
                        <p class="text-sm font-medium text-foreground">
                            <template v-if="draftExists">A draft has already been started for this league.</template>
                            <template v-else-if="activeTeamCount === 0">Add at least one team before starting the draft.</template>
                            <template v-else>Ready to start when you are.</template>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="draftExists">Open the draft room from the league draft tab to make picks.</template>
                            <template v-else>
                                Once started, picks begin in the configured pick order. You can pause, adjust, or skip the timer from the draft room.
                            </template>
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="lg"
                        class="shrink-0"
                        :disabled="!canStartDraft || startDraftForm.processing"
                        @click="startDraftDialogOpen = true"
                    >
                        <Play class="size-4" />
                        Start draft
                    </Button>
                </div>
                <p v-if="startDraftForm.errors.league_id" class="text-sm text-destructive">{{ startDraftForm.errors.league_id }}</p>
            </section>
        </div>

        <Dialog v-model:open="startDraftDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Start the draft?</DialogTitle>
                    <DialogDescription>
                        This will open the draft room for <strong class="text-foreground">{{ league.name }}</strong>, lock the pick order,
                        and notify every coach. You can still pause or adjust the timer once the draft is live, but the pick order cannot be
                        changed without aborting.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" :disabled="startDraftForm.processing" @click="startDraftDialogOpen = false">
                        Cancel
                    </Button>
                    <Button type="button" :disabled="startDraftForm.processing" @click="confirmStartDraft">
                        <Play class="size-4" />
                        Start draft
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </LeagueDetailLayout>
</template>
