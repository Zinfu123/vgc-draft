<script setup lang="ts">
import MatchTeamPanel from '@/components/match/MatchTeamPanel.vue';
import ReplayInput from '@/components/match/ReplayInput.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { isReverbBroadcastClientConfigured } from '@/lib/broadcasting';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
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
        pokemon: [
            {
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
            },
        ];
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
        pokemon: [
            {
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
            },
        ];
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

interface props {
    set: Set;
    currentUserTeam: CurrentUserTeam | null;
    matchPokepaste: MatchPokepastePayload | null;
    matchPokepasteSides: MatchPokepasteSides;
    isLeagueAdmin: boolean;
    requireTeamMatchPokepasteBeforeResults?: boolean;
    requireReplaysBeforeResults?: boolean;
}

const props = defineProps<props>();
const setId = props.set.id;
const form = useForm({
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

const importFromReplayForm = useForm({
    set_id: props.set.id,
    replay_slot: 1,
    p1_team_id: props.set.team1.id,
});

const flashSuccess = computed((): string | null => {
    const f = page.props.flash as { success?: string } | undefined;
    return f?.success ?? null;
});

const savedReplayOptions = computed((): { slot: number; label: string; url: string }[] => {
    const out: { slot: number; label: string; url: string }[] = [];
    const r1 = props.set.replay1?.trim();
    const r2 = props.set.replay2?.trim();
    const r3 = props.set.replay3?.trim();
    if (r1) {
        out.push({ slot: 1, label: 'Game 1', url: r1 });
    }
    if (r2) {
        out.push({ slot: 2, label: 'Game 2', url: r2 });
    }
    if (r3) {
        out.push({ slot: 3, label: 'Game 3', url: r3 });
    }

    return out;
});

const hasSavedReplayUrl = computed((): boolean => savedReplayOptions.value.length > 0);

const hasServerSavedReplay = computed((): boolean => {
    return !!(
        props.set.replay1?.trim() ||
        props.set.replay2?.trim() ||
        props.set.replay3?.trim()
    );
});

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
            body: JSON.stringify({
                set_id: props.set.id,
                replay_slot: importFromReplayForm.replay_slot,
            }),
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
            const msg =
                json.message ||
                (Array.isArray(json.errors) ? json.errors.join(' ') : null) ||
                'Could not read Showdown players from this replay.';
            replayPreviewError.value = msg;
            importFromReplayForm.p1_team_id = props.set.team1.id;

            return;
        }
        replayPreviewData.value = {
            p1_name: json.p1_name ?? '',
            p2_name: json.p2_name ?? '',
            suggested_p1_team_id: json.suggested_p1_team_id ?? null,
            needs_manual_p1_map: !!json.needs_manual_p1_map,
        };
        if (json.suggested_p1_team_id != null && json.needs_manual_p1_map === false) {
            importFromReplayForm.p1_team_id = json.suggested_p1_team_id;
        } else {
            importFromReplayForm.p1_team_id = props.set.team1.id;
        }
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
    const first = savedReplayOptions.value[0];
    if (first) {
        importFromReplayForm.replay_slot = first.slot;
    }
    importFromReplayForm.p1_team_id = props.set.team1.id;
    importFromReplayForm.clearErrors();
    void fetchReplayPlayerPreview();
});

watch(
    () => importFromReplayForm.replay_slot,
    () => {
        if (importReplayModalOpen.value) {
            void fetchReplayPlayerPreview();
        }
    },
);

type SetModel = {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1_score: number | null;
    team2_score: number | null;
    replay1: string | null;
    replay2: string | null;
    replay3: string | null;
    winner_id: number | null;
    winner_name: string;
    winner_logo: string;
    status: number;
};
const echoEvent = ref<{ id: SetModel['id']; status: SetModel['status'] }>({ id: form.set_id, status: props.set.status });

if (isReverbBroadcastClientConfigured) {
    useEchoPublic<SetModel>(`set_updated.${setId}`, 'SetUpdatedEvent', (e) => {
        echoEvent.value = { id: e.id, status: e.status };
    });
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'League',
        href: `/leagues/${props.set.league_id}`,
    },
    {
        title: 'Match',
        href: `/match/set/${props.set.id}`,
    },
];

const isUserInSet = computed((): boolean => {
    if (!props.currentUserTeam) {
        return false;
    }
    return props.set.team1.id === props.currentUserTeam.id || props.set.team2.id === props.currentUserTeam.id;
});

const isSetCompleted = computed((): boolean => {
    return echoEvent.value.status === 0 || props.set.status === 0;
});

const bothSidesPasteReady = computed((): boolean => {
    return !!(props.matchPokepasteSides.team1?.has_data && props.matchPokepasteSides.team2?.has_data);
});

const disableForm = computed((): boolean => {
    if (echoEvent.value.status === 0 || props.set.status === 0) {
        return true;
    } else if (!isUserInSet.value) {
        return true;
    } else if (props.requireTeamMatchPokepasteBeforeResults && !bothSidesPasteReady.value) {
        return true;
    } else if (props.requireReplaysBeforeResults && !hasServerSavedReplay.value) {
        return true;
    } else {
        return false;
    }
});

const canSubmitSetResult = computed((): boolean => {
    const a = Number(form.team1_score);
    const b = Number(form.team2_score);
    return (a === 2 && b <= 1) || (b === 2 && a <= 1);
});

const winnerCoach = computed((): string | null => {
    if (props.set.winner_id === props.set.team1.id) {
        return props.set.team1.user.name;
    } else if (props.set.winner_id === props.set.team2.id) {
        return props.set.team2.user.name;
    }
    return null;
});

const winnerShowdownUsername = computed((): string | null => {
    if (props.set.winner_id === props.set.team1.id) {
        return coachShowdownDisplay(props.set.team1) || null;
    }
    if (props.set.winner_id === props.set.team2.id) {
        return coachShowdownDisplay(props.set.team2) || null;
    }
    return null;
});

const winnerLogo = computed((): string | null => {
    if (props.set.winner_id === props.set.team1.id) {
        return props.set.team1.logo || null;
    } else if (props.set.winner_id === props.set.team2.id) {
        return props.set.team2.logo || null;
    }
    return props.set.winner_logo || null;
});

const authUserId = computed((): number | null => {
    const u = page.props.auth?.user as { id?: number } | undefined;
    return u?.id ?? null;
});

function showdownDisplay(username: string | null | undefined): string {
    const t = username?.trim();
    return t !== undefined && t !== '' ? t : '';
}

function coachShowdownDisplay(team: {
    showdown_username?: string | null;
    user: { showdown_username?: string | null };
}): string {
    return showdownDisplay(team.showdown_username) || showdownDisplay(team.user.showdown_username);
}

const currentUserMissingShowdown = computed((): boolean => {
    const id = authUserId.value;
    if (id === null) {
        return false;
    }
    if (props.set.team1.user.id === id && !coachShowdownDisplay(props.set.team1)) {
        return true;
    }
    if (props.set.team2.user.id === id && !coachShowdownDisplay(props.set.team2)) {
        return true;
    }
    return false;
});

const handleSubmit = () => {
    form.command = 'update';
    form.put('/match');
};

const handleReplaySubmit = () => {
    replayForm.put(route('sets.update-replays'));
};

const closeImportReplayModal = () => {
    importReplayModalOpen.value = false;
};

const submitImportFromReplay = () => {
    importFromReplayForm.post(route('sets.import-replay-teams'), {
        preserveScroll: true,
        onSuccess: () => {
            importReplayModalOpen.value = false;
        },
    });
};

const handleReopenMatch = () => {
    if (
        !confirm(
            'Reopen this match? The set result will be cleared, standings will be adjusted to remove this match’s points and win/loss totals, and players can submit a new result.',
        )
    ) {
        return;
    }
    reopenForm.put('/match');
};
</script>
<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${props.set.team1.name} vs ${props.set.team2.name}`" />
        <div class="mx-auto mt-6 mb-6 flex max-w-4xl flex-col items-center px-4 sm:mt-8 sm:mb-8">
            <h1 class="text-center text-2xl font-bold sm:text-3xl">{{ props.set.team1.name }} vs {{ props.set.team2.name }}</h1>
            <p
                v-if="flashSuccess"
                class="text-green-700 dark:text-green-400 mt-4 max-w-lg text-center text-sm font-medium"
            >
                {{ flashSuccess }}
            </p>
            <div
                class="border-border bg-muted/30 text-foreground mx-auto mt-6 w-full max-w-xl rounded-lg border px-4 py-3 text-left shadow-sm sm:text-center"
            >
                <h2 class="text-base font-semibold">Pokémon Showdown</h2>
                <p class="text-muted-foreground mt-1 text-xs">
                    Names from each coach's profile — useful for challenges and replay import.
                </p>
                <dl class="mt-3 space-y-2 text-sm sm:mx-auto sm:max-w-md sm:text-left">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <dt class="min-w-0 font-medium">{{ props.set.team1.name }}</dt>
                        <dd class="text-muted-foreground shrink-0 font-mono text-xs sm:text-sm">
                            <template v-if="coachShowdownDisplay(props.set.team1)">
                                {{ coachShowdownDisplay(props.set.team1) }}
                            </template>
                            <span v-else class="italic">Not set</span>
                        </dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <dt class="min-w-0 font-medium">{{ props.set.team2.name }}</dt>
                        <dd class="text-muted-foreground shrink-0 font-mono text-xs sm:text-sm">
                            <template v-if="coachShowdownDisplay(props.set.team2)">
                                {{ coachShowdownDisplay(props.set.team2) }}
                            </template>
                            <span v-else class="italic">Not set</span>
                        </dd>
                    </div>
                </dl>
                <p v-if="currentUserMissingShowdown" class="text-muted-foreground mt-3 text-xs">
                    <Link :href="route('profile.edit')" class="text-primary font-medium hover:underline">Add your Showdown name in Profile</Link>
                    to help opponents find you and speed up replay matching.
                </p>
            </div>
        </div>
        <div class="mt-8 flex w-full flex-col items-stretch gap-10 px-4 lg:flex-row lg:items-start lg:gap-4 lg:px-6">
            <MatchTeamPanel :team="props.set.team1" :showdown-display="coachShowdownDisplay(props.set.team1)" />
            <!-- Center Column-->
            <div class="flex min-w-0 flex-1 flex-col px-0 lg:px-4">
                <form class="top-0" @submit.prevent="handleSubmit">
                    <div class="space-y-12">
                        <div v-if="props.matchPokepaste && isUserInSet" class="pb-2">
                            <h2 class="text-lg font-semibold text-foreground">Match team paste</h2>
                            <p class="text-muted-foreground mt-1 text-sm">
                                Build and save your six in the editor (Showdown-style). Only you can open your paste.
                            </p>
                            <Link
                                :href="'/pokepaste/' + props.matchPokepaste.pokepaste_public_id"
                                class="bg-primary text-primary-foreground mt-4 inline-flex rounded-md px-4 py-2 text-sm font-semibold shadow-sm transition-colors hover:bg-primary/90"
                            >
                                Open team paste editor
                            </Link>
                        </div>

                        <div class="border-b border-border pb-12">
                            <h2 class="mb-6 text-center text-base/7 font-semibold text-foreground">Set Result</h2>
                            <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
                                <div class="sm:col-span-3">
                                    <label for="team1_score" class="block text-sm/6 font-medium text-foreground"
                                        >{{ props.set.team1.name }} Score</label
                                    >
                                    <div class="mt-2">
                                        <select
                                            name="team1_score"
                                            id="team1_score"
                                            class="block min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-base text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none sm:min-h-9 sm:py-1.5 sm:text-sm"
                                            v-model="form.team1_score"
                                            :disabled="disableForm"
                                        >
                                            <option value="0">0</option>
                                            <option value="1">1</option>
                                            <option v-if="form.team2_score < 2" value="2">2</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="team2_score" class="block text-sm/6 font-medium text-foreground"
                                        >{{ props.set.team2.name }} Score</label
                                    >
                                    <div class="mt-2">
                                        <select
                                            name="team2_score"
                                            id="team2_score"
                                            class="block min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-base text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none sm:min-h-9 sm:py-1.5 sm:text-sm"
                                            v-model="form.team2_score"
                                            :disabled="disableForm"
                                        >
                                            <option value="0">0</option>
                                            <option value="1">1</option>
                                            <option v-if="form.team1_score < 2" value="2">2</option>
                                        </select>
                                    </div>
                                </div>

                                <p v-if="form.errors.set_result" class="text-destructive sm:col-span-6 text-sm">
                                    {{ form.errors.set_result }}
                                </p>
                                <p
                                    v-else-if="!isSetCompleted && isUserInSet && requireTeamMatchPokepasteBeforeResults && !bothSidesPasteReady"
                                    class="text-amber-700 sm:col-span-6 text-sm dark:text-amber-400"
                                >
                                    Both teams must submit their match team paste before set results can be entered.
                                    <span v-if="!matchPokepasteSides.team1?.has_data" class="block">Missing paste: {{ props.set.team1.name }}</span>
                                    <span v-if="!matchPokepasteSides.team2?.has_data" class="block">Missing paste: {{ props.set.team2.name }}</span>
                                </p>
                                <p
                                    v-else-if="!isSetCompleted && isUserInSet && requireReplaysBeforeResults && !hasServerSavedReplay"
                                    class="text-amber-700 sm:col-span-6 text-sm dark:text-amber-400"
                                >
                                    At least one Showdown replay must be saved (use Save Replays) before you can submit set results for this league.
                                </p>
                                <p
                                    v-else-if="!isSetCompleted && isUserInSet && !canSubmitSetResult"
                                    class="text-muted-foreground sm:col-span-6 text-sm"
                                >
                                    Choose a final set score: one side must have 2 wins (2-0 or 2-1).
                                </p>

                                <template v-if="isSetCompleted">
                                    <div class="sm:col-span-3">
                                        <p class="block text-sm/6 font-medium text-foreground">{{ props.set.team1.name }} team paste</p>
                                        <div class="mt-2">
                                            <Link
                                                v-if="props.matchPokepasteSides.team1?.has_data"
                                                :href="'/pokepaste/' + props.matchPokepasteSides.team1.public_id"
                                                class="text-primary text-sm font-medium hover:underline"
                                            >
                                                View team paste
                                            </Link>
                                            <p v-else class="text-muted-foreground text-sm">No team paste saved for this match.</p>
                                        </div>
                                    </div>
                                    <div class="sm:col-span-3">
                                        <p class="block text-sm/6 font-medium text-foreground">{{ props.set.team2.name }} team paste</p>
                                        <div class="mt-2">
                                            <Link
                                                v-if="props.matchPokepasteSides.team2?.has_data"
                                                :href="'/pokepaste/' + props.matchPokepasteSides.team2.public_id"
                                                class="text-primary text-sm font-medium hover:underline"
                                            >
                                                View team paste
                                            </Link>
                                            <p v-else class="text-muted-foreground text-sm">No team paste saved for this match.</p>
                                        </div>
                                    </div>
                                </template>

                                <ReplayInput
                                    id="replay1"
                                    label="Game 1 Replay"
                                    v-model="replayForm.replay1"
                                    :is-user-in-set="isUserInSet"
                                    :error="replayForm.errors.replay1"
                                />
                                <ReplayInput
                                    id="replay2"
                                    label="Game 2 Replay"
                                    v-model="replayForm.replay2"
                                    :is-user-in-set="isUserInSet"
                                    :error="replayForm.errors.replay2"
                                />
                                <ReplayInput
                                    id="replay3"
                                    label="Game 3 Replay"
                                    v-model="replayForm.replay3"
                                    :is-user-in-set="isUserInSet"
                                    :error="replayForm.errors.replay3"
                                />

                                <div class="flex flex-col gap-3 sm:col-span-6 sm:flex-row sm:flex-wrap">
                                    <Button
                                        v-if="!isSetCompleted && isUserInSet"
                                        type="submit"
                                        :disabled="disableForm || !canSubmitSetResult"
                                        class="touch-manipulation"
                                    >
                                        Update
                                    </Button>
                                    <Button
                                        v-if="isUserInSet"
                                        type="button"
                                        :disabled="replayForm.processing"
                                        class="touch-manipulation"
                                        @click="handleReplaySubmit"
                                    >
                                        Save Replays
                                    </Button>
                                    <Button
                                        v-if="isUserInSet && hasSavedReplayUrl"
                                        type="button"
                                        variant="outline"
                                        :disabled="importFromReplayForm.processing"
                                        class="touch-manipulation"
                                        @click="importReplayModalOpen = true"
                                    >
                                        Import rosters from replay
                                    </Button>
                                </div>
                                <p
                                    v-if="page.props.errors && (page.props.errors as Record<string, string>).replay_import"
                                    class="text-destructive sm:col-span-6 text-sm"
                                >
                                    {{ (page.props.errors as Record<string, string>).replay_import }}
                                </p>
                            </div>
                        </div>
                        <div v-if="props.set.winner_id" class="mt-8 border-t border-border pt-8">
                            <h2 class="mb-6 text-center text-base/7 font-semibold text-foreground">Winner</h2>
                            <div class="flex flex-col items-center justify-center space-y-4">
                                <img v-if="winnerLogo" :src="winnerLogo" alt="Winner Logo" class="mx-auto h-30 w-30 rounded-full" />
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-foreground">{{ props.set.winner_name }}</p>
                                    <p v-if="winnerCoach" class="text-lg text-muted-foreground">Coach: {{ winnerCoach }}</p>
                                    <p v-if="winnerShowdownUsername" class="text-muted-foreground text-sm">
                                        Showdown:
                                        <span class="text-foreground font-mono font-medium">{{ winnerShowdownUsername }}</span>
                                    </p>
                                </div>
                                <div v-if="props.isLeagueAdmin && isSetCompleted" class="flex w-full max-w-md flex-col items-center gap-2">
                                    <p v-if="reopenForm.errors.set_id" class="text-center text-sm text-destructive">{{ reopenForm.errors.set_id }}</p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        :disabled="reopenForm.processing"
                                        class="border-destructive/60 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        @click="handleReopenMatch"
                                    >
                                        Reopen match (admin)
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <!-- Team 2 -->
            <MatchTeamPanel :team="props.set.team2" :showdown-display="coachShowdownDisplay(props.set.team2)" />
        </div>
        <Dialog v-model:open="importReplayModalOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Import rosters from Showdown replay</DialogTitle>
                    <DialogDescription>
                        Uses the saved replay log to set both teams’ match paste species (replay order). If a team’s paste already has the
                        same six Pokémon in any order, abilities, moves, items, and EVs you entered are kept and only slot order and any new
                        species mapping are updated. Choose which replay and which league team is Showdown player 1 (p1); a coach’s Showdown
                        name from Profile or team that matches the replay can pre-select p1.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <fieldset>
                        <legend class="text-sm font-medium text-foreground">Which replay?</legend>
                        <div class="mt-2 space-y-2">
                            <label
                                v-for="opt in savedReplayOptions"
                                :key="opt.slot"
                                class="flex cursor-pointer items-start gap-2 rounded-md border border-transparent p-2 hover:bg-muted/50"
                            >
                                <input
                                    v-model.number="importFromReplayForm.replay_slot"
                                    type="radio"
                                    name="replay_slot_import"
                                    class="mt-1"
                                    :value="opt.slot"
                                />
                                <span class="text-sm">
                                    <span class="font-medium text-foreground">{{ opt.label }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">{{ opt.url }}</span>
                                </span>
                            </label>
                        </div>
                        <p v-if="importFromReplayForm.errors.replay_slot" class="mt-1 text-sm text-destructive">
                            {{ importFromReplayForm.errors.replay_slot }}
                        </p>
                    </fieldset>
                    <p v-if="replayPreviewLoading" class="text-sm text-muted-foreground">Reading replay…</p>
                    <p v-else-if="replayPreviewError" class="text-sm text-destructive">{{ replayPreviewError }}</p>
                    <template v-else-if="replayPreviewData">
                        <p class="text-sm text-muted-foreground">
                            Showdown:
                            <span class="font-medium text-foreground">{{ replayPreviewData.p1_name }}</span>
                            vs
                            <span class="font-medium text-foreground">{{ replayPreviewData.p2_name }}</span>
                        </p>
                        <p
                            v-if="!replayPreviewData.needs_manual_p1_map && replayPreviewData.suggested_p1_team_id"
                            class="text-sm font-medium text-green-700 dark:text-green-400"
                        >
                            Player 1 (p1) matched your Settings → Profile Showdown names ({{
                                replayPreviewData.suggested_p1_team_id === props.set.team1.id
                                    ? props.set.team1.name
                                    : props.set.team2.name
                            }}).
                        </p>
                        <p v-else class="text-sm text-muted-foreground">
                            Add a Showdown username on your Profile or team for automatic p1 matching, or choose manually below.
                        </p>
                    </template>
                    <fieldset v-if="replayPreviewLoading || !replayPreviewData || replayPreviewData.needs_manual_p1_map">
                        <legend class="text-sm font-medium text-foreground">Showdown player 1 (p1) is</legend>
                        <div class="mt-2 space-y-2">
                            <label class="flex cursor-pointer items-center gap-2">
                                <input v-model.number="importFromReplayForm.p1_team_id" type="radio" name="p1_team_import" :value="props.set.team1.id" />
                                <span class="text-sm text-foreground">{{ props.set.team1.name }}</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2">
                                <input v-model.number="importFromReplayForm.p1_team_id" type="radio" name="p1_team_import" :value="props.set.team2.id" />
                                <span class="text-sm text-foreground">{{ props.set.team2.name }}</span>
                            </label>
                        </div>
                        <p v-if="importFromReplayForm.errors.p1_team_id" class="mt-1 text-sm text-destructive">
                            {{ importFromReplayForm.errors.p1_team_id }}
                        </p>
                    </fieldset>
                    <p v-if="importFromReplayForm.errors.set_id" class="text-sm text-destructive">{{ importFromReplayForm.errors.set_id }}</p>
                </div>

                <DialogFooter class="flex-wrap gap-2">
                    <Button type="button" variant="outline" @click="closeImportReplayModal">Cancel</Button>
                    <Button
                        type="button"
                        :disabled="importFromReplayForm.processing || savedReplayOptions.length === 0"
                        @click="submitImportFromReplay"
                    >
                        Import both teams
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
