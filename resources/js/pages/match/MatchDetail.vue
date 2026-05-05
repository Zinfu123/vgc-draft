<script setup lang="ts">
import MatchChat from '@/components/match/MatchChat.vue';
import MatchTeamPanel from '@/components/match/MatchTeamPanel.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { isReverbBroadcastClientConfigured } from '@/lib/broadcasting';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { CalendarClock, ExternalLink, MessageSquare } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const page = usePage();

interface Set {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1: {
        id: number;
        name: string;
        logo: string;
        showdown_username?: string | null;
        user: {
            id: number;
            name: string;
            showdown_username?: string | null;
        };
        pokemon: Array<{
            id: number;
            name: string;
            pokemon: {
                id: number;
                name: string;
                sprite_url: string;
                type1: string;
                type2?: string;
            };
            cost: number;
        }>;
    };
    team2: {
        id: number;
        name: string;
        logo: string;
        showdown_username?: string | null;
        user: {
            id: number;
            name: string;
            showdown_username?: string | null;
        };
        pokemon: Array<{
            id: number;
            name: string;
            pokemon: {
                id: number;
                name: string;
                sprite_url: string;
                type1: string;
                type2?: string;
            };
            cost: number;
        }>;
    };
    team1_score: number | null;
    team2_score: number | null;
    team1_pokepaste: string | null;
    team2_pokepaste: string | null;
    replay1: string | null;
    replay2: string | null;
    replay3: string | null;
    winner_id: number | null;
    winner_name: string;
    winner_logo: string;
    status: number;
    scheduled_at: string | null;
}

interface CurrentUserTeam {
    id: number;
}

interface MatchPokepastePayload {
    pokepaste_public_id: string;
}

interface MatchPokepasteSides {
    team1: { public_id: string; has_data: boolean } | null;
    team2: { public_id: string; has_data: boolean } | null;
}

interface MatchMessage {
    id: number;
    set_id: number;
    user_id: number;
    user_name: string;
    body: string;
    is_read: boolean;
    created_at: string;
}

interface PendingScheduleRequest {
    id: number;
    proposed_at: string;
    proposed_by_user_id: number;
    status: string;
    is_mine: boolean;
}

interface Props {
    set: Set;
    currentUserTeam: CurrentUserTeam | null;
    matchPokepaste: MatchPokepastePayload | null;
    matchPokepasteSides: MatchPokepasteSides;
    isLeagueAdmin: boolean;
    requireTeamMatchPokepasteBeforeResults?: boolean;
    requireReplaysBeforeResults?: boolean;
    matchMessages?: MatchMessage[];
    isParticipant?: boolean;
    pendingScheduleRequest?: PendingScheduleRequest | null;
}

const props = defineProps<Props>();
const setId = props.set.id;

const chatSheetOpen = ref(false);
const localUnreadCount = ref(0);

watch(
    () => props.matchMessages,
    (messages) => {
        if (messages !== undefined) {
            const userId = authUserId.value;
            localUnreadCount.value = messages.filter((m) => m.user_id !== userId && !m.is_read).length;
        }
    },
    { once: true },
);

watch(chatSheetOpen, (open) => {
    if (open) {
        localUnreadCount.value = 0;
        void markMessagesRead();
    }
});

async function markMessagesRead(): Promise<void> {
    if (!props.isParticipant) {
        return;
    }
    try {
        await fetch(route('sets.messages.mark-read', { set: props.set.id }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': readXsrfToken(),
            },
        });
    } catch {
        // silently fail — badge will reset on next page load
    }
}

const scheduleRequestDialogOpen = ref(false);
const rescheduleDialogOpen = ref(false);

const scheduleRequestForm = useForm({
    proposed_at: '',
});

const rescheduleForm = useForm({
    action: 'reschedule' as const,
    proposed_at: '',
});

const respondForm = useForm({
    action: '' as 'accept' | 'decline' | 'cancel',
});

function openScheduleRequestDialog(): void {
    scheduleRequestForm.reset();
    scheduleRequestForm.clearErrors();
    scheduleRequestDialogOpen.value = true;
}

function openRescheduleDialog(): void {
    rescheduleForm.reset();
    rescheduleForm.clearErrors();
    rescheduleDialogOpen.value = true;
}

function submitScheduleRequest(): void {
    scheduleRequestForm.post(route('sets.schedule-request.store', { set: props.set.id }), {
        onSuccess: () => { scheduleRequestDialogOpen.value = false; },
    });
}

function submitReschedule(scheduleRequestId: number): void {
    rescheduleForm.patch(route('sets.schedule-request.respond', { scheduleRequest: scheduleRequestId }), {
        onSuccess: () => { rescheduleDialogOpen.value = false; },
    });
}

function acceptScheduleRequest(scheduleRequestId: number): void {
    respondForm.action = 'accept';
    respondForm.patch(route('sets.schedule-request.respond', { scheduleRequest: scheduleRequestId }));
}

function declineScheduleRequest(scheduleRequestId: number): void {
    respondForm.action = 'decline';
    respondForm.patch(route('sets.schedule-request.respond', { scheduleRequest: scheduleRequestId }));
}

function cancelScheduleRequest(scheduleRequestId: number): void {
    respondForm.action = 'cancel';
    respondForm.patch(route('sets.schedule-request.respond', { scheduleRequest: scheduleRequestId }));
}

function formatScheduledTime(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

const scoreForm = useForm({
    set_id: props.set.id,
    team1_score: props.set.team1_score || 0,
    team2_score: props.set.team2_score || 0,
    team1_id: props.set.team1.id,
    team2_id: props.set.team2.id,
    command: 'update',
});

const replayForm = useForm({
    set_id: props.set.id,
    replay1: props.set.replay1 || '',
    replay2: props.set.replay2 || '',
    replay3: props.set.replay3 || '',
});

const reopenForm = useForm({
    command: 'reopen' as const,
    set_id: props.set.id,
});

const importReplayModalOpen = ref(false);
const skipNextPreviewFetch = ref(false);

const importFromReplayForm = useForm({
    set_id: props.set.id,
    replay_slot: 1,
    p1_team_id: props.set.team1.id,
});

// — Echo —
type SetModel = { id: number; status: number };
const echoEvent = ref<SetModel>({ id: setId, status: props.set.status });
if (isReverbBroadcastClientConfigured) {
    useEchoPublic<SetModel>(`set_updated.${setId}`, 'SetUpdatedEvent', (e) => {
        echoEvent.value = { id: e.id, status: e.status };
    });

    useEchoPublic<MatchMessage>(`match.chat.${setId}`, 'MatchMessageSentEvent', (e) => {
        if (e.user_id !== authUserId.value) {
            if (chatSheetOpen.value) {
                void markMessagesRead();
            } else {
                localUnreadCount.value++;
            }
        }
    });
}

// — Computed —
const isSetCompleted = computed(() => echoEvent.value.status === 0 || props.set.status === 0);
const isUserInSet = computed(() => {
    if (!props.currentUserTeam) return false;
    return props.set.team1.id === props.currentUserTeam.id || props.set.team2.id === props.currentUserTeam.id;
});
const bothSidesPasteReady = computed(() => !!(props.matchPokepasteSides.team1?.has_data && props.matchPokepasteSides.team2?.has_data));
const hasServerSavedReplay = computed(() => !!(props.set.replay1?.trim() || props.set.replay2?.trim() || props.set.replay3?.trim()));
const canSubmitSetResult = computed(() => {
    const a = Number(scoreForm.team1_score);
    const b = Number(scoreForm.team2_score);
    return (a === 2 && b <= 1) || (b === 2 && a <= 1);
});
const disableScoreForm = computed(() => {
    if (isSetCompleted.value || !isUserInSet.value) return true;
    if (props.requireTeamMatchPokepasteBeforeResults && !bothSidesPasteReady.value) return true;
    if (props.requireReplaysBeforeResults && !hasServerSavedReplay.value) return true;
    return false;
});

const savedReplayOptions = computed(() => {
    const out: { slot: number; label: string; url: string }[] = [];
    if (props.set.replay1?.trim()) out.push({ slot: 1, label: 'Game 1', url: props.set.replay1.trim() });
    if (props.set.replay2?.trim()) out.push({ slot: 2, label: 'Game 2', url: props.set.replay2.trim() });
    if (props.set.replay3?.trim()) out.push({ slot: 3, label: 'Game 3', url: props.set.replay3.trim() });
    return out;
});

const winnerTeam = computed(() => {
    if (!props.set.winner_id) return null;
    return props.set.winner_id === props.set.team1.id ? props.set.team1 : props.set.team2;
});

const flashSuccess = computed((): string | null => {
    const f = page.props.flash as { success?: string } | undefined;
    return f?.success ?? null;
});

const authUserId = computed((): number | null => {
    const u = page.props.auth?.user as { id?: number } | undefined;
    return u?.id ?? null;
});

function showdownDisplay(team: { showdown_username?: string | null; user: { showdown_username?: string | null } }): string {
    const t = team.showdown_username?.trim() || team.user.showdown_username?.trim() || '';
    return t;
}

const currentUserMissingShowdown = computed(() => {
    const id = authUserId.value;
    if (id === null) return false;
    if (props.set.team1.user.id === id && !showdownDisplay(props.set.team1)) return true;
    if (props.set.team2.user.id === id && !showdownDisplay(props.set.team2)) return true;
    return false;
});

// — Replay preview (for import modal) —
const replayPreviewLoading = ref(false);
const replayPreviewError = ref<string | null>(null);
const replayPreviewData = ref<{
    p1_name: string;
    p2_name: string;
    suggested_p1_team_id: number | null;
    needs_manual_p1_map: boolean;
} | null>(null);

function readXsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function fetchReplayPlayerPreview(): Promise<void> {
    replayPreviewLoading.value = true;
    replayPreviewError.value = null;
    replayPreviewData.value = null;
    try {
        const res = await fetch(route('sets.preview-replay-players'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': readXsrfToken(),
            },
            body: JSON.stringify({ set_id: props.set.id, replay_slot: importFromReplayForm.replay_slot }),
        });
        const json = (await res.json().catch(() => ({}))) as {
            ok?: boolean;
            message?: string;
            errors?: string[];
            p1_name?: string;
            p2_name?: string;
            suggested_p1_team_id?: number | null;
            needs_manual_p1_map?: boolean;
        };
        if (!res.ok || !json.ok) {
            replayPreviewError.value =
                json.message || (Array.isArray(json.errors) ? json.errors.join(' ') : null) || 'Could not read Showdown players from this replay.';
            importFromReplayForm.p1_team_id = props.set.team1.id;
            return;
        }
        replayPreviewData.value = {
            p1_name: json.p1_name ?? '',
            p2_name: json.p2_name ?? '',
            suggested_p1_team_id: json.suggested_p1_team_id ?? null,
            needs_manual_p1_map: !!json.needs_manual_p1_map,
        };
        importFromReplayForm.p1_team_id =
            json.suggested_p1_team_id != null && !json.needs_manual_p1_map ? json.suggested_p1_team_id : props.set.team1.id;
    } catch {
        replayPreviewError.value = 'Could not load replay preview.';
        importFromReplayForm.p1_team_id = props.set.team1.id;
    } finally {
        replayPreviewLoading.value = false;
    }
}

watch(importReplayModalOpen, (open) => {
    if (!open) {
        replayPreviewError.value = null;
        replayPreviewData.value = null;
        return;
    }
    if (skipNextPreviewFetch.value) {
        skipNextPreviewFetch.value = false;
        return;
    }
    const first = savedReplayOptions.value[0];
    if (first) importFromReplayForm.replay_slot = first.slot;
    importFromReplayForm.p1_team_id = props.set.team1.id;
    importFromReplayForm.clearErrors();
    void fetchReplayPlayerPreview();
});

watch(
    () => importFromReplayForm.replay_slot,
    () => { if (importReplayModalOpen.value) void fetchReplayPlayerPreview(); },
);

// — Actions —
function submitScoreForm(): void {
    scoreForm.command = 'update';
    scoreForm.put('/match');
}

function submitReplays(): void {
    if (props.requireReplaysBeforeResults) {
        replayForm.put(route('sets.update-replays'), {
            preserveScroll: true,
            onSuccess: () => void attemptReplayImportAfterSave(),
        });
    } else {
        replayForm.put(route('sets.update-replays'));
    }
}

async function attemptReplayImportAfterSave(): Promise<void> {
    const first = savedReplayOptions.value[0];
    if (!first) return;

    importFromReplayForm.replay_slot = first.slot;
    importFromReplayForm.p1_team_id = props.set.team1.id;
    importFromReplayForm.clearErrors();

    await fetchReplayPlayerPreview();

    if (
        !replayPreviewError.value &&
        replayPreviewData.value &&
        !replayPreviewData.value.needs_manual_p1_map &&
        replayPreviewData.value.suggested_p1_team_id !== null
    ) {
        importFromReplayForm.p1_team_id = replayPreviewData.value.suggested_p1_team_id;
        importFromReplayForm.post(route('sets.import-replay-teams'), { preserveScroll: true });
    } else {
        skipNextPreviewFetch.value = true;
        importReplayModalOpen.value = true;
    }
}

function submitImportFromReplay(): void {
    importFromReplayForm.post(route('sets.import-replay-teams'), {
        preserveScroll: true,
        onSuccess: () => { importReplayModalOpen.value = false; },
    });
}

function preventKeyboardEntry(e: KeyboardEvent): void {
    if (!['Tab', 'Escape'].includes(e.key)) {
        e.preventDefault();
    }
}

function handleReopenMatch(): void {
    if (!confirm('Reopen this match? The set result will be cleared and players can submit a new result.')) return;
    reopenForm.put('/match');
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'League', href: `/leagues/${props.set.league_id}` },
    { title: 'Match', href: `/match/set/${props.set.id}` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${set.team1.name} vs ${set.team2.name}`" />

        <!-- Top bar: chat + schedule (match players only, active matches only) -->
        <template v-if="isUserInSet && !isSetCompleted">
            <div class="flex w-full justify-end gap-2 px-4 sm:px-6 lg:px-8">
                <div v-if="matchMessages === undefined" class="flex gap-2">
                    <div class="h-9 w-24 animate-pulse rounded-md bg-muted" />
                    <div class="h-9 w-32 animate-pulse rounded-md bg-muted" />
                </div>
                <template v-else>
                    <Button variant="outline" size="sm" class="gap-1.5" @click="openScheduleRequestDialog">
                        <CalendarClock class="size-4" />
                        {{ set.scheduled_at ? 'Reschedule' : 'Request a Time' }}
                    </Button>
                    <Sheet v-model:open="chatSheetOpen">
                        <SheetTrigger as-child>
                            <Button variant="outline" size="sm" class="gap-1.5">
                                <MessageSquare class="size-4" />
                                Chat
                                <span
                                    v-if="localUnreadCount > 0"
                                    class="bg-primary text-primary-foreground ml-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-xs"
                                >
                                    {{ localUnreadCount }}
                                </span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" class="flex w-full flex-col p-0 sm:max-w-md">
                            <SheetHeader class="border-border shrink-0 border-b px-4 py-3">
                                <SheetTitle class="text-base">Chat</SheetTitle>
                            </SheetHeader>
                            <div class="flex-1 overflow-hidden">
                                <MatchChat
                                    :set-id="set.id"
                                    :initial-messages="matchMessages"
                                    :current-user-id="authUserId ?? 0"
                                />
                            </div>
                        </SheetContent>
                    </Sheet>
                </template>
            </div>
        </template>

        <!-- Scoreboard header -->
        <div class="mt-6 px-4 sm:px-6 lg:px-8">
            <div class="border-border bg-card mx-auto max-w-2xl rounded-xl border p-6">
                <p class="text-muted-foreground mb-4 text-center text-xs font-medium uppercase tracking-wider">Round {{ set.round }}</p>
                <div class="grid grid-cols-3 items-center gap-4">
                    <!-- Team 1 -->
                    <div class="flex flex-col items-center gap-2">
                        <img v-if="set.team1.logo" :src="set.team1.logo" :alt="set.team1.name" class="h-16 w-16 rounded-full object-cover" />
                        <div v-else class="bg-muted h-16 w-16 rounded-full" />
                        <Link :href="`/teams/${set.team1.id}`" class="hover:text-primary text-center text-sm font-semibold transition-colors">
                            {{ set.team1.name }}
                        </Link>
                        <p class="text-muted-foreground text-xs">{{ set.team1.user.name }}</p>
                        <p v-if="showdownDisplay(set.team1)" class="text-muted-foreground font-mono text-xs">{{ showdownDisplay(set.team1) }}</p>
                        <p v-else-if="currentUserMissingShowdown && set.team1.user.id === authUserId" class="text-xs text-amber-600 dark:text-amber-400">
                            <Link :href="route('profile.edit')" class="underline">Add Showdown name</Link>
                        </p>
                    </div>

                    <!-- Score -->
                    <div class="flex flex-col items-center gap-1">
                        <div class="flex items-center gap-3">
                            <span class="text-5xl font-bold tabular-nums">{{ set.team1_score ?? '–' }}</span>
                            <span class="text-muted-foreground text-2xl">–</span>
                            <span class="text-5xl font-bold tabular-nums">{{ set.team2_score ?? '–' }}</span>
                        </div>
                        <span
                            class="mt-1 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="isSetCompleted ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-muted text-muted-foreground'"
                        >
                            {{ isSetCompleted ? 'Complete' : 'In progress' }}
                        </span>
                        <p v-if="winnerTeam" class="mt-1 text-center text-xs font-semibold">
                            {{ winnerTeam.name }} wins
                        </p>
                    </div>

                    <!-- Team 2 -->
                    <div class="flex flex-col items-center gap-2">
                        <img v-if="set.team2.logo" :src="set.team2.logo" :alt="set.team2.name" class="h-16 w-16 rounded-full object-cover" />
                        <div v-else class="bg-muted h-16 w-16 rounded-full" />
                        <Link :href="`/teams/${set.team2.id}`" class="hover:text-primary text-center text-sm font-semibold transition-colors">
                            {{ set.team2.name }}
                        </Link>
                        <p class="text-muted-foreground text-xs">{{ set.team2.user.name }}</p>
                        <p v-if="showdownDisplay(set.team2)" class="text-muted-foreground font-mono text-xs">{{ showdownDisplay(set.team2) }}</p>
                        <p v-else-if="currentUserMissingShowdown && set.team2.user.id === authUserId" class="text-xs text-amber-600 dark:text-amber-400">
                            <Link :href="route('profile.edit')" class="underline">Add Showdown name</Link>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scheduled time badge -->
        <div v-if="set.scheduled_at" class="mt-4 flex justify-center">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800 dark:bg-green-900/50 dark:text-green-300">
                <CalendarClock class="size-4" />
                Scheduled: {{ formatScheduledTime(set.scheduled_at) }}
            </span>
        </div>

        <!-- Flash message -->
        <p v-if="flashSuccess" class="mt-4 text-center text-sm font-medium text-green-700 dark:text-green-400">
            {{ flashSuccess }}
        </p>

        <!-- Main content -->
        <div class="mx-auto mt-8 max-w-5xl space-y-6 px-4 pb-16 sm:px-6 lg:px-8">

            <!-- Pending schedule request -->
            <div v-if="pendingScheduleRequest" class="border-border bg-card rounded-xl border p-6">
                <div class="mb-3 flex items-center gap-2">
                    <CalendarClock class="text-primary size-5" />
                    <h2 class="font-semibold">Pending Time Request</h2>
                </div>
                <p class="text-muted-foreground mb-4 text-sm">
                    Proposed time:
                    <span class="text-foreground font-medium">{{ formatScheduledTime(pendingScheduleRequest.proposed_at) }}</span>
                </p>

                <!-- The proposer is waiting for the other player -->
                <div v-if="pendingScheduleRequest.is_mine" class="flex flex-wrap items-center gap-3">
                    <p class="text-muted-foreground text-sm italic">Waiting for your opponent to respond…</p>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="respondForm.processing"
                        class="border-destructive/60 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="cancelScheduleRequest(pendingScheduleRequest!.id)"
                    >
                        Cancel request
                    </Button>
                </div>

                <!-- The other player can respond -->
                <div v-else-if="isUserInSet" class="flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        :disabled="respondForm.processing"
                        @click="acceptScheduleRequest(pendingScheduleRequest!.id)"
                    >
                        Accept
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="respondForm.processing"
                        @click="openRescheduleDialog"
                    >
                        Propose new time
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="respondForm.processing"
                        class="border-destructive/60 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="declineScheduleRequest(pendingScheduleRequest!.id)"
                    >
                        Decline
                    </Button>
                </div>
            </div>

            <!-- Replays -->
            <div class="border-border bg-card rounded-xl border p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold">Replays</h2>
                        <p class="text-muted-foreground text-sm">Paste your Pokémon Showdown replay links. Rosters and results are imported automatically.</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <template v-for="slot in [{ key: 'replay1', label: 'Game 1' }, { key: 'replay2', label: 'Game 2' }, { key: 'replay3', label: 'Game 3' }]" :key="slot.key">
                        <div class="flex items-center gap-3">
                            <span class="text-muted-foreground w-14 shrink-0 text-sm font-medium">{{ slot.label }}</span>
                            <div class="relative min-w-0 flex-1">
                                <input
                                    v-if="isUserInSet && !isSetCompleted"
                                    v-model="(replayForm as any)[slot.key]"
                                    type="url"
                                    :placeholder="`https://replay.pokemonshowdown.com/...`"
                                    class="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring block w-full rounded-md border px-3 py-2 pr-9 text-sm focus:ring-2 focus:outline-none"
                                />
                                <a
                                    v-else-if="(set as any)[slot.key]"
                                    :href="(set as any)[slot.key]"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-primary flex items-center gap-1 truncate text-sm hover:underline"
                                >
                                    <span class="truncate">{{ (set as any)[slot.key] }}</span>
                                    <ExternalLink class="size-3 shrink-0" />
                                </a>
                                <p v-else class="text-muted-foreground text-sm italic">No replay yet</p>
                                <p v-if="(replayForm.errors as any)[slot.key]" class="text-destructive mt-1 text-xs">
                                    {{ (replayForm.errors as any)[slot.key] }}
                                </p>
                            </div>
                        </div>
                    </template>
                </div>

                <div v-if="isUserInSet && !isSetCompleted" class="mt-4 flex flex-wrap gap-2">
                    <Button :disabled="replayForm.processing || importFromReplayForm.processing" @click="submitReplays">
                        {{ replayForm.processing ? 'Saving…' : importFromReplayForm.processing ? 'Importing…' : 'Save replays' }}
                    </Button>
                </div>
                <p
                    v-if="page.props.errors && (page.props.errors as Record<string, string>).replay_import"
                    class="text-destructive mt-2 text-sm"
                >
                    {{ (page.props.errors as Record<string, string>).replay_import }}
                </p>
            </div>

            <!-- Match Pastes -->
            <div class="border-border bg-card rounded-xl border p-6">
                <h2 class="mb-1 font-semibold">Match Pastes</h2>
                <p class="text-muted-foreground mb-4 text-sm">
                    Each team's six Pokémon with full build details (moves, items, EVs). Only visible to the submitting player until the match is complete.
                </p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <!-- Team 1 paste -->
                    <div class="border-border rounded-lg border p-4">
                        <p class="mb-2 text-sm font-medium">{{ set.team1.name }}</p>
                        <template v-if="matchPokepaste && isUserInSet && currentUserTeam?.id === set.team1.id">
                            <p class="text-muted-foreground mb-3 text-xs">Build your team paste with moves, items, and EVs.</p>
                            <Link
                                :href="'/pokepaste/' + matchPokepaste.pokepaste_public_id"
                                class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                            >
                                Open paste editor
                                <ExternalLink class="size-3" />
                            </Link>
                        </template>
                        <template v-else-if="matchPokepasteSides.team1?.has_data && isSetCompleted">
                            <Link
                                :href="'/pokepaste/' + matchPokepasteSides.team1.public_id"
                                class="text-primary inline-flex items-center gap-1 text-sm hover:underline"
                            >
                                View paste <ExternalLink class="size-3" />
                            </Link>
                        </template>
                        <p v-else-if="matchPokepasteSides.team1?.has_data" class="text-muted-foreground text-sm italic">Submitted — visible after match</p>
                        <p v-else class="text-muted-foreground text-sm italic">No paste submitted</p>
                    </div>

                    <!-- Team 2 paste -->
                    <div class="border-border rounded-lg border p-4">
                        <p class="mb-2 text-sm font-medium">{{ set.team2.name }}</p>
                        <template v-if="matchPokepaste && isUserInSet && currentUserTeam?.id === set.team2.id">
                            <p class="text-muted-foreground mb-3 text-xs">Build your team paste with moves, items, and EVs.</p>
                            <Link
                                :href="'/pokepaste/' + matchPokepaste.pokepaste_public_id"
                                class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                            >
                                Open paste editor
                                <ExternalLink class="size-3" />
                            </Link>
                        </template>
                        <template v-else-if="matchPokepasteSides.team2?.has_data && isSetCompleted">
                            <Link
                                :href="'/pokepaste/' + matchPokepasteSides.team2.public_id"
                                class="text-primary inline-flex items-center gap-1 text-sm hover:underline"
                            >
                                View paste <ExternalLink class="size-3" />
                            </Link>
                        </template>
                        <p v-else-if="matchPokepasteSides.team2?.has_data" class="text-muted-foreground text-sm italic">Submitted — visible after match</p>
                        <p v-else class="text-muted-foreground text-sm italic">No paste submitted</p>
                    </div>
                </div>
            </div>

            <!-- Set result (active matches, participants only) -->
            <div v-if="!isSetCompleted && isUserInSet" class="border-border bg-card rounded-xl border p-6">
                <h2 class="mb-1 font-semibold">Set Result</h2>
                <p class="text-muted-foreground mb-4 text-sm">Results are calculated automatically when replays are saved. You can also enter them manually here.</p>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ set.team1.name }}</label>
                        <select
                            v-model="scoreForm.team1_score"
                            :disabled="disableScoreForm"
                            class="border-input bg-background text-foreground focus:ring-ring block w-full rounded-md border px-3 py-2 text-sm focus:ring-2 focus:outline-none disabled:opacity-50"
                        >
                            <option value="0">0</option>
                            <option value="1">1</option>
                            <option v-if="scoreForm.team2_score < 2" value="2">2</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ set.team2.name }}</label>
                        <select
                            v-model="scoreForm.team2_score"
                            :disabled="disableScoreForm"
                            class="border-input bg-background text-foreground focus:ring-ring block w-full rounded-md border px-3 py-2 text-sm focus:ring-2 focus:outline-none disabled:opacity-50"
                        >
                            <option value="0">0</option>
                            <option value="1">1</option>
                            <option v-if="scoreForm.team1_score < 2" value="2">2</option>
                        </select>
                    </div>
                </div>

                <p v-if="(scoreForm.errors as any).set_result" class="text-destructive mt-2 text-sm">{{ (scoreForm.errors as any).set_result }}</p>
                <p v-else-if="requireTeamMatchPokepasteBeforeResults && !bothSidesPasteReady" class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                    Both teams must submit their match paste before results can be entered.
                    <span v-if="!matchPokepasteSides.team1?.has_data" class="block">Missing: {{ set.team1.name }}</span>
                    <span v-if="!matchPokepasteSides.team2?.has_data" class="block">Missing: {{ set.team2.name }}</span>
                </p>
                <p v-else-if="requireReplaysBeforeResults && !hasServerSavedReplay" class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                    At least one replay must be saved before submitting results for this league.
                </p>
                <p v-else-if="!canSubmitSetResult" class="text-muted-foreground mt-2 text-sm">
                    Choose a valid score — one team must have 2 wins (2-0 or 2-1).
                </p>

                <div class="mt-4">
                    <Button :disabled="disableScoreForm || !canSubmitSetResult || scoreForm.processing" @click="submitScoreForm">
                        {{ scoreForm.processing ? 'Saving…' : 'Submit result' }}
                    </Button>
                </div>
            </div>

            <!-- Admin reopen -->
            <div v-if="isLeagueAdmin && isSetCompleted" class="flex justify-center">
                <Button
                    variant="outline"
                    :disabled="reopenForm.processing"
                    class="border-destructive/60 text-destructive hover:bg-destructive/10 hover:text-destructive"
                    @click="handleReopenMatch"
                >
                    Reopen match (admin)
                </Button>
                <p v-if="reopenForm.errors.set_id" class="text-destructive mt-1 text-center text-sm">{{ reopenForm.errors.set_id }}</p>
            </div>

            <!-- Team rosters -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <MatchTeamPanel :team="set.team1" :showdown-display="showdownDisplay(set.team1)" />
                <MatchTeamPanel :team="set.team2" :showdown-display="showdownDisplay(set.team2)" />
            </div>
        </div>

        <!-- Request / reschedule a time dialog -->
        <Dialog v-model:open="scheduleRequestDialogOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{ set.scheduled_at ? 'Reschedule Match' : 'Request a Match Time' }}</DialogTitle>
                    <DialogDescription>
                        {{ set.scheduled_at ? 'Propose a new date and time. Your opponent will be notified on Discord and can accept, decline, or counter-propose.' : 'Propose a date and time to play. Your opponent will be notified on Discord and can accept, decline, or propose a new time.' }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Proposed date &amp; time</label>
                        <input
                            v-model="scheduleRequestForm.proposed_at"
                            type="datetime-local"
                            class="border-input bg-background text-foreground focus:ring-ring block w-full rounded-md border px-3 py-2 text-sm focus:ring-2 focus:outline-none"
                            @keydown="preventKeyboardEntry"
                        />
                        <p v-if="scheduleRequestForm.errors.proposed_at" class="text-destructive mt-1 text-sm">
                            {{ scheduleRequestForm.errors.proposed_at }}
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="scheduleRequestDialogOpen = false">Cancel</Button>
                    <Button :disabled="scheduleRequestForm.processing || !scheduleRequestForm.proposed_at" @click="submitScheduleRequest">
                        {{ scheduleRequestForm.processing ? 'Sending…' : 'Send request' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Reschedule dialog -->
        <Dialog v-model:open="rescheduleDialogOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Propose a New Time</DialogTitle>
                    <DialogDescription>
                        Suggest an alternative date and time. Your opponent will be notified on Discord.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">New proposed date &amp; time</label>
                        <input
                            v-model="rescheduleForm.proposed_at"
                            type="datetime-local"
                            class="border-input bg-background text-foreground focus:ring-ring block w-full rounded-md border px-3 py-2 text-sm focus:ring-2 focus:outline-none"
                            @keydown="preventKeyboardEntry"
                        />
                        <p v-if="rescheduleForm.errors.proposed_at" class="text-destructive mt-1 text-sm">
                            {{ rescheduleForm.errors.proposed_at }}
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="rescheduleDialogOpen = false">Cancel</Button>
                    <Button
                        :disabled="rescheduleForm.processing || !rescheduleForm.proposed_at || !pendingScheduleRequest"
                        @click="pendingScheduleRequest && submitReschedule(pendingScheduleRequest.id)"
                    >
                        {{ rescheduleForm.processing ? 'Sending…' : 'Propose new time' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Import rosters from replay dialog -->
        <Dialog v-model:open="importReplayModalOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirm team assignment</DialogTitle>
                    <DialogDescription>
                        Showdown usernames couldn't be matched automatically. Choose which team was Showdown player 1 so rosters can be imported correctly.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <fieldset>
                        <legend class="text-sm font-medium">Which replay?</legend>
                        <div class="mt-2 space-y-2">
                            <label
                                v-for="opt in savedReplayOptions"
                                :key="opt.slot"
                                class="hover:bg-muted/50 flex cursor-pointer items-start gap-2 rounded-md border border-transparent p-2"
                            >
                                <input v-model.number="importFromReplayForm.replay_slot" type="radio" name="replay_slot_import" class="mt-1" :value="opt.slot" />
                                <span class="text-sm">
                                    <span class="font-medium">{{ opt.label }}</span>
                                    <span class="text-muted-foreground block truncate text-xs">{{ opt.url }}</span>
                                </span>
                            </label>
                        </div>
                        <p v-if="importFromReplayForm.errors.replay_slot" class="text-destructive mt-1 text-sm">{{ importFromReplayForm.errors.replay_slot }}</p>
                    </fieldset>

                    <p v-if="replayPreviewLoading" class="text-muted-foreground text-sm">Reading replay…</p>
                    <p v-else-if="replayPreviewError" class="text-destructive text-sm">{{ replayPreviewError }}</p>
                    <template v-else-if="replayPreviewData">
                        <p class="text-muted-foreground text-sm">
                            Showdown: <span class="text-foreground font-medium">{{ replayPreviewData.p1_name }}</span>
                            vs <span class="text-foreground font-medium">{{ replayPreviewData.p2_name }}</span>
                        </p>
                        <p v-if="!replayPreviewData.needs_manual_p1_map && replayPreviewData.suggested_p1_team_id" class="text-sm font-medium text-green-700 dark:text-green-400">
                            p1 matched to {{ replayPreviewData.suggested_p1_team_id === set.team1.id ? set.team1.name : set.team2.name }}.
                        </p>
                        <p v-else class="text-muted-foreground text-sm">Could not auto-match — choose below.</p>
                    </template>

                    <fieldset v-if="replayPreviewLoading || !replayPreviewData || replayPreviewData.needs_manual_p1_map">
                        <legend class="text-sm font-medium">Showdown p1 is</legend>
                        <div class="mt-2 space-y-2">
                            <label class="flex cursor-pointer items-center gap-2">
                                <input v-model.number="importFromReplayForm.p1_team_id" type="radio" name="p1_team_import" :value="set.team1.id" />
                                <span class="text-sm">{{ set.team1.name }}</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2">
                                <input v-model.number="importFromReplayForm.p1_team_id" type="radio" name="p1_team_import" :value="set.team2.id" />
                                <span class="text-sm">{{ set.team2.name }}</span>
                            </label>
                        </div>
                        <p v-if="importFromReplayForm.errors.p1_team_id" class="text-destructive mt-1 text-sm">{{ importFromReplayForm.errors.p1_team_id }}</p>
                    </fieldset>

                    <p v-if="importFromReplayForm.errors.set_id" class="text-destructive text-sm">{{ importFromReplayForm.errors.set_id }}</p>
                </div>

                <DialogFooter class="flex-wrap gap-2">
                    <Button variant="outline" @click="importReplayModalOpen = false">Cancel</Button>
                    <Button :disabled="importFromReplayForm.processing || savedReplayOptions.length === 0" @click="submitImportFromReplay">
                        Confirm &amp; import
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
