<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface PokemonGameOption {
    value: string;
    label: string;
    generation: number;
}

interface PlayoffFormatOption {
    value: string;
    label: string;
    bracket_generation_supported: boolean;
}

interface Props {
    command: string;
    league_id: number;
    league_name: string;
    draft_date: string | null;
    set_start_date: string | null;
    set_frequency: number;
    enforce_round_count: boolean;
    round_count: number | null;
    draft_points: number;
    minimum_drafts: number;
    ban_enabled: boolean;
    bans_per_user: number | null;
    minimum_cost_to_ban: number | null;
    logo: string | null;
    pokemon_generation: number;
    pokemon_game: string;
    pokemon_game_options: PokemonGameOption[];
    pokemon_generation_options: number[];
    discord_webhook_url: string;
    discord_replay_webhook_url: string;
    draft_start_at: string | null;
    playoff_format: string;
    playoff_bracket_size: number;
    playoff_format_options: PlayoffFormatOption[];
    playoff_bracket_size_options: number[];
    playoffs_enabled: boolean;
    free_trade_window_hours: number;
    require_showdown_username: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    league_name: '',
    draft_date: null,
    set_start_date: null,
    set_frequency: 1,
    enforce_round_count: false,
    round_count: null,
    draft_points: 80,
    minimum_drafts: 10,
    ban_enabled: false,
    bans_per_user: null,
    minimum_cost_to_ban: null,
    pokemon_generation: 9,
    pokemon_game: 'scarlet_violet',
    pokemon_game_options: () => [],
    pokemon_generation_options: () => [9],
    discord_webhook_url: '',
    discord_replay_webhook_url: '',
    draft_start_at: null,
    playoff_format: 'single_elimination',
    playoff_bracket_size: 4,
    playoff_format_options: () => [],
    playoff_bracket_size_options: () => [2, 4, 6, 8, 16, 32],
    playoffs_enabled: true,
    free_trade_window_hours: 24,
    require_showdown_username: false,
});

function formatDateForInput(value: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    return isNaN(date.getTime()) ? '' : date.toISOString().slice(0, 10);
}

function toDatetimeLocalValue(utcStr: string | null | undefined): string {
    if (!utcStr) return '';
    const d = new Date(utcStr);
    if (isNaN(d.getTime())) return '';
    const offset = d.getTimezoneOffset() * 60000;
    return new Date(d.getTime() - offset).toISOString().slice(0, 16);
}

const form = useForm({
    league_id: props.league_id,
    name: props.league_name,
    draft_date: formatDateForInput(props.draft_date) || new Date().toISOString().slice(0, 10),
    set_start_date: formatDateForInput(props.set_start_date) || new Date().toISOString().slice(0, 10),
    set_frequency: props.set_frequency,
    enforce_round_count: props.enforce_round_count,
    round_count: props.round_count,
    draft_points: props.draft_points,
    minimum_drafts: props.minimum_drafts,
    ban_enabled: props.ban_enabled,
    bans_per_user: props.bans_per_user,
    minimum_cost_to_ban: props.minimum_cost_to_ban,
    logo: props.logo as File | null,
    pokemon_generation: props.pokemon_generation,
    pokemon_game: props.pokemon_game,
    discord_webhook_url: props.discord_webhook_url ?? '',
    discord_replay_webhook_url: props.discord_replay_webhook_url ?? '',
    draft_start_at: props.draft_start_at ? toDatetimeLocalValue(props.draft_start_at) : '',
    playoff_format: props.playoff_format,
    playoff_bracket_size: props.playoff_bracket_size,
    playoffs_enabled: props.playoffs_enabled,
    free_trade_window_hours: props.free_trade_window_hours,
    require_showdown_username: props.require_showdown_username,
});

const gamesForSelectedGeneration = computed(() =>
    props.pokemon_game_options.filter((g) => g.generation === form.pokemon_generation),
);

watch(
    () => form.pokemon_generation,
    () => {
        const ok = gamesForSelectedGeneration.value.some((g) => g.value === form.pokemon_game);
        if (!ok && gamesForSelectedGeneration.value[0]) {
            form.pokemon_game = gamesForSelectedGeneration.value[0].value;
        }
    },
);

const frequencyOptions = [
    { label: 'Daily', value: 1 },
    { label: 'Twice Weekly', value: 2 },
    { label: 'Weekly', value: 3 },
    { label: 'Custom Frequency', value: 4 },
];

const isCreateFlow = computed(() => props.command === 'create');

const wizardSteps = [
    { title: 'League identity', description: 'Name and optional logo.' },
    { title: 'Schedule', description: 'Draft day, season start, and match rhythm.' },
    { title: 'Ruleset', description: 'Generation and main-series game format.' },
    { title: 'Draft rules', description: 'Budget, roster size, and bans.' },
    {
        title: 'Regular season',
        description: 'Whether the schedule stops after a set number of rounds before playoffs.',
    },
    {
        title: 'Playoffs',
        description: 'Bracket format and how many teams qualify (seeding is set up in Admin later).',
    },
    { title: 'Discord (optional)', description: 'Webhooks for league pings and Showdown replay links.' },
    { title: 'Review', description: 'Confirm details and create the league.' },
] as const;

const wizardStep = ref(0);
const localWizardError = ref('');

const lastWizardStepIndex = wizardSteps.length - 1;

const selectedGameLabel = computed(() => {
    const g = props.pokemon_game_options.find((o) => o.value === form.pokemon_game);
    return g?.label ?? form.pokemon_game;
});

const frequencyLabel = computed(() => frequencyOptions.find((o) => o.value === form.set_frequency)?.label ?? '—');

const playoffFormatLabel = computed(() => {
    const o = props.playoff_format_options.find((x) => x.value === form.playoff_format);
    return o?.label ?? form.playoff_format;
});

const hasNotificationsWebhook = computed(() => (form.discord_webhook_url?.trim() ?? '') !== '');
const hasReplayWebhookOnly = computed(() => (form.discord_replay_webhook_url?.trim() ?? '') !== '');

function isValidOptionalWebhookUrl(raw: string | undefined): boolean {
    const t = raw?.trim() ?? '';
    if (t === '') {
        return true;
    }
    try {
        new URL(t);

        return true;
    } catch {
        return false;
    }
}

function validateWizardStep(step: number): boolean {
    localWizardError.value = '';
    if (step === 0) {
        if (!form.name?.trim()) {
            localWizardError.value = 'Enter a league name to continue.';
            return false;
        }
    }
    if (step === 1) {
        if (!form.draft_date) {
            localWizardError.value = 'Choose a draft date.';
            return false;
        }
        if (!form.set_start_date) {
            localWizardError.value = 'Choose when the season starts.';
            return false;
        }
    }
    if (step === 3) {
        if (!form.draft_points || form.draft_points < 1) {
            localWizardError.value = 'Draft points must be at least 1.';
            return false;
        }
        if (form.minimum_drafts == null || form.minimum_drafts < 0) {
            localWizardError.value = 'Set a minimum roster size (0 or more).';
            return false;
        }
        if (form.ban_enabled) {
            if (!form.bans_per_user || form.bans_per_user < 1) {
                localWizardError.value = 'When bans are on, set bans per coach (at least 1).';
                return false;
            }
            if (form.minimum_cost_to_ban == null || form.minimum_cost_to_ban < 0) {
                localWizardError.value = 'When bans are on, set a minimum ban cost (0 or more).';
                return false;
            }
        }
    }
    if (step === 4) {
        if (form.enforce_round_count && (!form.round_count || form.round_count < 1)) {
            localWizardError.value = 'Enter how many rounds, or turn off the round limit.';
            return false;
        }
    }
    if (step === 5) {
        if (!props.playoff_format_options.some((o) => o.value === form.playoff_format)) {
            localWizardError.value = 'Choose a playoff format.';
            return false;
        }
        if (!props.playoff_bracket_size_options.includes(Number(form.playoff_bracket_size))) {
            localWizardError.value = 'Choose a valid playoff bracket size.';
            return false;
        }
    }
    if (step === 6) {
        if (!isValidOptionalWebhookUrl(form.discord_webhook_url)) {
            localWizardError.value = 'Notifications webhook must be a valid URL or left empty.';
            return false;
        }
        if (!isValidOptionalWebhookUrl(form.discord_replay_webhook_url)) {
            localWizardError.value = 'Replays webhook must be a valid URL or left empty.';
            return false;
        }
    }
    return true;
}

function goNextWizardStep(): void {
    if (!validateWizardStep(wizardStep.value)) {
        return;
    }
    if (wizardStep.value < lastWizardStepIndex) {
        wizardStep.value += 1;
    }
}

function goPrevWizardStep(): void {
    localWizardError.value = '';
    if (wizardStep.value > 0) {
        wizardStep.value -= 1;
    }
}

const submit = () => {
    const roundCount = form.enforce_round_count ? form.round_count : 1;
    form.transform((data) => ({
        ...data,
        round_count: roundCount ?? 1,
        draft_start_at: data.draft_start_at ? new Date(data.draft_start_at).toISOString() : null,
    })).post(route('leagues.create'), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head :title="props.command === 'create' ? 'Create League' : 'Edit League'" />
    <AppLayout>
        <div class="mx-auto mt-6 mb-10 w-full max-w-2xl px-4 sm:mt-8 sm:px-6">
            <h1 class="mb-2 text-3xl font-bold">
                {{ props.command === 'create' ? 'Create a league' : 'Edit league' }}
            </h1>
            <p v-if="isCreateFlow" class="text-muted-foreground mb-8 text-sm leading-relaxed dark:text-neutral-400">
                Work through each step — regular season round cap, playoff bracket shape, optional Discord webhooks, then review.
                After the league exists, you will add teams, build the Pokémon pool, configure draft order, and launch the draft
                from league Admin. Coaches must have a
                <strong class="text-foreground font-medium dark:text-neutral-200">Pokémon Showdown</strong> username on
                <Link :href="route('profile.edit')" class="text-primary font-medium underline-offset-4 hover:underline">Settings → Profile</Link>
                and/or when creating their team — it is used for replay import and automated match helpers.
            </p>
            <form @submit.prevent="submit" class="space-y-8">
                <!-- Create: progress -->
                <div v-if="isCreateFlow" class="space-y-3">
                    <div class="flex justify-between text-xs font-medium text-muted-foreground dark:text-neutral-500">
                        <span>Step {{ wizardStep + 1 }} of {{ wizardSteps.length }}</span>
                        <span>{{ wizardSteps[wizardStep]?.title }}</span>
                    </div>
                    <div class="flex gap-1.5" role="list">
                        <div
                            v-for="(s, i) in wizardSteps"
                            :key="s.title"
                            role="listitem"
                            class="h-1.5 min-w-0 flex-1 rounded-full transition-colors"
                            :class="
                                i < wizardStep
                                    ? 'bg-primary'
                                    : i === wizardStep
                                      ? 'bg-primary/60'
                                      : 'bg-muted dark:bg-neutral-700'
                            "
                            :title="s.title"
                        />
                    </div>
                </div>

                <!-- Step panels -->
                <Card class="border-border/80 shadow-sm dark:border-neutral-800">
                    <CardHeader v-if="isCreateFlow" class="pb-2">
                        <CardTitle class="text-lg">{{ wizardSteps[wizardStep]?.title }}</CardTitle>
                        <CardDescription>{{ wizardSteps[wizardStep]?.description }}</CardDescription>
                    </CardHeader>
                    <CardContent :class="isCreateFlow ? 'pt-0' : 'pt-6'">
                        <!-- Identity -->
                        <div
                            v-show="!isCreateFlow || wizardStep === 0"
                            class="space-y-6"
                            :class="isCreateFlow ? '' : 'border-border/60 border-b pb-8'"
                        >
                            <h2 v-if="!isCreateFlow" class="text-lg font-semibold">League identity</h2>
                            <div class="grid gap-2">
                                <Label for="name">League name</Label>
                                <Input id="name" v-model="form.name" type="text" placeholder="e.g. Spring 2026 VGC League" required />
                                <InputError :message="form.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="logo">League logo (optional)</Label>
                                <Input
                                    id="logo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/jpg,image/gif,image/svg+xml"
                                    @input="form.logo = ($event.target as HTMLInputElement)?.files?.[0] || null"
                                />
                                <InputError :message="form.errors.logo" />
                            </div>
                        </div>

                        <!-- Schedule -->
                        <div
                            v-show="!isCreateFlow || wizardStep === 1"
                            class="space-y-6"
                            :class="[!isCreateFlow && 'mt-8 border-border/60 border-b pb-8', isCreateFlow && wizardStep === 1 && '']"
                        >
                            <h2 v-if="!isCreateFlow" class="text-lg font-semibold">Schedule</h2>
                            <div class="grid gap-2">
                                <Label for="draft_date">Draft date</Label>
                                <Input id="draft_date" v-model="form.draft_date" type="date" required />
                                <InputError :message="form.errors.draft_date" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="set_start_date">Season start</Label>
                                <Input id="set_start_date" v-model="form.set_start_date" type="date" required />
                                <p class="text-muted-foreground text-xs dark:text-neutral-500">
                                    The regular season will start automatically on this date once the draft is complete.
                                </p>
                                <InputError :message="form.errors.set_start_date" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="draft_start_at">Scheduled draft start (optional)</Label>
                                <Input id="draft_start_at" v-model="form.draft_start_at" type="datetime-local" />
                                <p class="text-muted-foreground text-xs dark:text-neutral-500">
                                    The draft will start automatically at this time. Times are shown and entered in your local timezone.
                                    Leave empty to start the draft manually from Admin.
                                </p>
                                <InputError :message="form.errors.draft_start_at" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="set_frequency">Set frequency</Label>
                                <select
                                    id="set_frequency"
                                    v-model.number="form.set_frequency"
                                    class="border-input bg-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:outline-none"
                                >
                                    <option v-for="option in frequencyOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.set_frequency" />
                            </div>
                        </div>

                        <!-- Ruleset -->
                        <div
                            v-show="!isCreateFlow || wizardStep === 2"
                            class="space-y-6"
                            :class="[!isCreateFlow && 'mt-8 border-border/60 border-b pb-8']"
                        >
                            <h2 v-if="!isCreateFlow" class="text-lg font-semibold">Ruleset</h2>
                            <div class="grid gap-2">
                                <Label for="pokemon_generation">Pokémon generation</Label>
                                <select
                                    id="pokemon_generation"
                                    v-model.number="form.pokemon_generation"
                                    class="border-input bg-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:outline-none"
                                >
                                    <option v-for="g in pokemon_generation_options" :key="g" :value="g">Generation {{ g }}</option>
                                </select>
                                <InputError :message="form.errors.pokemon_generation" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="pokemon_game">Ruleset (main series game)</Label>
                                <select
                                    id="pokemon_game"
                                    v-model="form.pokemon_game"
                                    class="border-input bg-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:outline-none"
                                >
                                    <option v-for="opt in gamesForSelectedGeneration" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.pokemon_game" />
                            </div>
                        </div>

                        <!-- Draft rules -->
                        <div
                            v-show="!isCreateFlow || wizardStep === 3"
                            class="space-y-6"
                            :class="[!isCreateFlow && 'mt-8 border-border/60 border-b pb-8']"
                        >
                            <h2 v-if="!isCreateFlow" class="text-lg font-semibold">Draft rules</h2>
                            <div class="grid gap-2">
                                <Label for="draft_points">Draft points (budget per team)</Label>
                                <Input id="draft_points" v-model.number="form.draft_points" type="number" min="1" required />
                                <InputError :message="form.errors.draft_points" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="minimum_drafts">Minimum Pokémon per team</Label>
                                <Input id="minimum_drafts" v-model.number="form.minimum_drafts" type="number" min="0" required />
                                <p class="text-muted-foreground text-xs dark:text-neutral-500">Usually matches how many picks each coach must make before the draft can finish.</p>
                                <InputError :message="form.errors.minimum_drafts" />
                            </div>
                            <div class="grid gap-2">
                                <div class="flex items-center gap-2">
                                    <input
                                        id="ban_enabled"
                                        v-model="form.ban_enabled"
                                        type="checkbox"
                                        class="border-input accent-primary size-4 rounded"
                                    />
                                    <Label for="ban_enabled">Enable ban phase before picks</Label>
                                </div>
                                <InputError :message="form.errors.ban_enabled" />
                            </div>
                            <div v-if="form.ban_enabled" class="grid gap-2">
                                <Label for="bans_per_user">Bans per coach</Label>
                                <Input id="bans_per_user" v-model.number="form.bans_per_user" type="number" min="1" required />
                                <InputError :message="form.errors.bans_per_user" />
                            </div>
                            <div v-if="form.ban_enabled" class="grid gap-2">
                                <Label for="minimum_cost_to_ban">Minimum cost to ban</Label>
                                <Input id="minimum_cost_to_ban" v-model.number="form.minimum_cost_to_ban" type="number" min="0" required />
                                <InputError :message="form.errors.minimum_cost_to_ban" />
                            </div>
                            <div class="grid gap-2">
                                <div class="flex items-center gap-2">
                                    <input
                                        id="require_showdown_username"
                                        v-model="form.require_showdown_username"
                                        type="checkbox"
                                        class="border-input accent-primary size-4 rounded"
                                    />
                                    <Label for="require_showdown_username">Require teams to have a Pokémon Showdown account</Label>
                                </div>
                                <p class="text-muted-foreground text-xs dark:text-neutral-500">
                                    When enabled, coaches must have a Showdown username on their profile or team before joining and participating in this league.
                                </p>
                                <InputError :message="form.errors.require_showdown_username" />
                            </div>
                        </div>

                        <!-- Regular season -->
                        <div
                            v-show="!isCreateFlow || wizardStep === 4"
                            class="space-y-6"
                            :class="[!isCreateFlow && 'mt-8 border-border/60 border-b pb-8']"
                        >
                            <h2 v-if="!isCreateFlow" class="text-lg font-semibold">Regular season</h2>
                            <p class="text-muted-foreground text-sm leading-relaxed dark:text-neutral-400">
                                This matches <strong class="text-foreground dark:text-neutral-200">Match configuration → number of rounds</strong>. Use it
                                when the league always runs the same number of Swiss or round-robin rounds before playoffs. Leave it off if the
                                regular season length is flexible or you decide when playoffs start outside these settings.
                            </p>
                            <div class="grid gap-2">
                                <div class="flex items-center gap-2">
                                    <input
                                        id="enforce_round_count"
                                        v-model="form.enforce_round_count"
                                        type="checkbox"
                                        class="border-input accent-primary size-4 rounded"
                                    />
                                    <Label for="enforce_round_count">Cap the regular season at a set number of rounds</Label>
                                </div>
                                <InputError :message="form.errors.enforce_round_count" />
                            </div>
                            <div v-if="form.enforce_round_count" class="grid gap-2">
                                <Label for="round_count">Rounds to play before playoffs</Label>
                                <Input id="round_count" v-model.number="form.round_count" type="number" min="1" required />
                                <p class="text-muted-foreground text-xs dark:text-neutral-500">
                                    Example: 7 means the app expects seven regular-season rounds per pool schedule, then you move to playoffs.
                                </p>
                                <InputError :message="form.errors.round_count" />
                            </div>
                        </div>

                        <!-- Playoffs -->
                        <div
                            v-show="!isCreateFlow || wizardStep === 5"
                            class="space-y-6"
                            :class="[!isCreateFlow && 'mt-8 border-border/60 border-b pb-8']"
                        >
                            <h2 v-if="!isCreateFlow" class="text-lg font-semibold">Playoffs</h2>
                            <p class="text-muted-foreground text-sm leading-relaxed dark:text-neutral-400">
                                You will seed teams and build the bracket in
                                <strong class="text-foreground dark:text-neutral-200">Admin → Playoffs</strong> after the regular season. Choosing
                                the format and size here sets the default for that screen.
                            </p>
                            <div class="flex items-start gap-3">
                                <input
                                    id="playoffs_enabled"
                                    v-model="form.playoffs_enabled"
                                    type="checkbox"
                                    class="mt-0.5 size-4 rounded border-input"
                                />
                                <div class="grid gap-0.5">
                                    <Label for="playoffs_enabled" class="cursor-pointer">Enable playoffs</Label>
                                    <p class="text-muted-foreground text-xs dark:text-neutral-500">
                                        If disabled, the commissioner can finalize the league directly from the regular season standings.
                                    </p>
                                </div>
                            </div>
                            <template v-if="form.playoffs_enabled">
                                <div class="grid gap-2">
                                    <Label for="playoff_format">Playoff format</Label>
                                    <select
                                        id="playoff_format"
                                        v-model="form.playoff_format"
                                        class="border-input bg-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:outline-none"
                                    >
                                        <option v-for="opt in playoff_format_options" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="!playoff_format_options.find((o) => o.value === form.playoff_format)?.bracket_generation_supported"
                                        class="text-muted-foreground text-xs dark:text-neutral-500"
                                    >
                                        Auto-generating the bracket in the app currently supports single elimination only; double elimination can be
                                        chosen to match how you run the league manually.
                                    </p>
                                    <InputError :message="form.errors.playoff_format" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="playoff_bracket_size">Teams in the playoff bracket</Label>
                                    <select
                                        id="playoff_bracket_size"
                                        v-model.number="form.playoff_bracket_size"
                                        class="border-input bg-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:outline-none"
                                    >
                                        <option v-for="n in playoff_bracket_size_options" :key="n" :value="n">
                                            Top {{ n }}
                                        </option>
                                    </select>
                                    <p class="text-muted-foreground text-xs dark:text-neutral-500">
                                        Standard sizes: 4, 8, or 16. "6" is supported as a special bracket shape in this app.
                                    </p>
                                    <InputError :message="form.errors.playoff_bracket_size" />
                                </div>
                            </template>

                            <div class="grid gap-2">
                                <Label for="free_trade_window_hours">Free trade window after draft (hours)</Label>
                                <Input
                                    id="free_trade_window_hours"
                                    v-model.number="form.free_trade_window_hours"
                                    type="number"
                                    min="0"
                                    step="1"
                                />
                                <p class="text-muted-foreground text-xs dark:text-neutral-500">
                                    After the draft ends, trades are free for this many hours before normal trade rules apply. Set to 0 to disable.
                                </p>
                                <InputError :message="form.errors.free_trade_window_hours" />
                            </div>
                        </div>

                        <!-- Discord (optional) -->
                        <div
                            v-show="!isCreateFlow || wizardStep === 6"
                            class="space-y-6"
                            :class="[!isCreateFlow && 'mt-8 border-border/60 border-b pb-8']"
                        >
                            <h2 v-if="!isCreateFlow" class="text-lg font-semibold">Discord (optional)</h2>
                            <p class="text-muted-foreground text-sm leading-relaxed dark:text-neutral-400">
                                Post draft pings, match results, and Showdown replay links to Discord. Entirely optional — you can
                                add or change these later under
                                <strong class="text-foreground dark:text-neutral-200">Admin → Discord</strong>.
                            </p>
                            <div class="bg-muted/40 dark:bg-neutral-900/40 space-y-2 rounded-lg border border-dashed p-4 text-sm">
                                <p class="text-foreground font-medium dark:text-neutral-200">How to get a webhook URL</p>
                                <ol class="text-muted-foreground list-decimal space-y-1.5 pl-5 dark:text-neutral-400">
                                    <li>In Discord, open a text channel where you want messages to appear.</li>
                                    <li>
                                        Click the channel name → <strong class="text-foreground dark:text-neutral-200">Edit Channel</strong> →
                                        <strong class="text-foreground dark:text-neutral-200">Integrations</strong> →
                                        <strong class="text-foreground dark:text-neutral-200">Webhooks</strong> →
                                        <strong class="text-foreground dark:text-neutral-200">Create Webhook</strong> (or “New Webhook”).
                                    </li>
                                    <li>
                                        Choose a name and copy the <strong class="text-foreground dark:text-neutral-200">Webhook URL</strong> (starts with
                                        <span class="font-mono text-xs">https://discord.com/api/webhooks/</span>).
                                    </li>
                                    <li>Paste it below. Use one webhook for both fields, or create two webhooks for different channels.</li>
                                </ol>
                            </div>
                            <div class="grid gap-2">
                                <Label for="discord_webhook_url">Notifications webhook</Label>
                                <Input
                                    id="discord_webhook_url"
                                    v-model="form.discord_webhook_url"
                                    type="url"
                                    autocomplete="off"
                                    placeholder="https://discord.com/api/webhooks/…"
                                />
                                <p class="text-muted-foreground text-xs dark:text-neutral-500">
                                    Draft start and next turn (picks/bans, with link to the draft room), plus match result notifications.
                                </p>
                                <InputError :message="form.errors.discord_webhook_url" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="discord_replay_webhook_url">Replays webhook</Label>
                                <Input
                                    id="discord_replay_webhook_url"
                                    v-model="form.discord_replay_webhook_url"
                                    type="url"
                                    autocomplete="off"
                                    placeholder="https://discord.com/api/webhooks/…"
                                />
                                <p class="text-muted-foreground text-xs dark:text-neutral-500">
                                    Messages when coaches save Pokémon Showdown replay URLs. If you leave this empty, replay posts use the
                                    notifications webhook when that one is set.
                                </p>
                                <InputError :message="form.errors.discord_replay_webhook_url" />
                            </div>
                        </div>

                        <!-- Review -->
                        <div v-if="isCreateFlow && wizardStep === 7" class="space-y-6">
                            <div class="bg-muted/50 dark:bg-neutral-900/50 space-y-3 rounded-lg border border-dashed p-4 text-sm">
                                <p class="font-medium">Summary</p>
                                <ul class="text-muted-foreground list-inside list-disc space-y-1 dark:text-neutral-400">
                                    <li>
                                        <span class="text-foreground font-medium dark:text-neutral-200">{{ form.name || '—' }}</span>
                                    </li>
                                    <li>
                                        Draft on {{ form.draft_date }}, season starts {{ form.set_start_date }}, {{ frequencyLabel }}
                                    </li>
                                    <li>Gen {{ form.pokemon_generation }} — {{ selectedGameLabel }}</li>
                                    <li>
                                        {{ form.draft_points }} draft points, at least {{ form.minimum_drafts }} Pokémon per team<span v-if="form.ban_enabled">, bans on</span><span v-else>, no bans</span>
                                    </li>
                                    <li>
                                        <span v-if="form.enforce_round_count">
                                            Regular season capped at {{ form.round_count }} round{{ form.round_count === 1 ? '' : 's' }}, then playoffs.
                                        </span>
                                        <span v-else>Regular season: no fixed round cap in settings (you control when playoffs start).</span>
                                    </li>
                                    <li>
                                        <span v-if="form.playoffs_enabled">
                                            Playoffs: {{ playoffFormatLabel }}, top {{ form.playoff_bracket_size }} teams (seeding in Admin).
                                        </span>
                                        <span v-else>No playoffs — commissioner finalizes league from standings.</span>
                                    </li>
                                    <li>
                                        Free trade window: {{ form.free_trade_window_hours > 0 ? `${form.free_trade_window_hours} hours after draft` : 'disabled' }}.
                                    </li>
                                    <li>
                                        <span v-if="form.draft_start_at">
                                            Draft scheduled for {{ new Date(form.draft_start_at).toLocaleString() }} (your local time).
                                        </span>
                                        <span v-else>Draft start: manual (via Admin → Draft).</span>
                                    </li>
                                    <li>
                                        Showdown account: {{ form.require_showdown_username ? 'required for all coaches' : 'optional' }}.
                                    </li>
                                    <li>
                                        <span class="text-foreground font-medium dark:text-neutral-200">Discord:</span>
                                        notifications {{ hasNotificationsWebhook ? 'configured' : 'not set' }}; replays
                                        {{
                                            hasReplayWebhookOnly
                                                ? 'separate channel/webhook'
                                                : hasNotificationsWebhook
                                                  ? 'use notifications webhook if needed'
                                                  : 'not set'
                                        }}.
                                    </li>
                                </ul>
                            </div>
                            <div class="space-y-3 rounded-lg border border-primary/20 bg-primary/5 p-4 dark:bg-primary/10">
                                <p class="flex items-center gap-2 text-sm font-medium">
                                    <Check class="text-primary size-4 shrink-0" aria-hidden="true" />
                                    After you create the league
                                </p>
                                <ol class="text-muted-foreground list-inside list-decimal space-y-2 text-sm dark:text-neutral-400">
                                    <li>Open <strong class="text-foreground dark:text-neutral-200">Admin → Match configuration</strong> and confirm pool settings.</li>
                                    <li>
                                        Add teams and assign coaches. Each coach needs a
                                        <strong class="text-foreground dark:text-neutral-200">Showdown username</strong> (Profile and/or the team-creation form). Then build the
                                        <strong class="text-foreground dark:text-neutral-200">Pokémon pool</strong> (CSV, templates, or search).
                                    </li>
                                    <li>
                                        In <strong class="text-foreground dark:text-neutral-200">Admin → Draft</strong>, set pick order and start the draft when everyone is ready.
                                    </li>
                                    <li>
                                        After the regular season, open <strong class="text-foreground dark:text-neutral-200">Admin → Playoffs</strong> to
                                        confirm seeds and generate the bracket.
                                    </li>
                                    <li>
                                        Adjust <strong class="text-foreground dark:text-neutral-200">Admin → Discord</strong> anytime for webhook URLs (same as this step).
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <p v-if="localWizardError" class="text-destructive text-sm">
                    {{ localWizardError }}
                </p>

                <!-- Edit: single submit -->
                <div v-if="!isCreateFlow" class="flex justify-end">
                    <Button type="submit" class="min-h-11 min-w-[10rem] touch-manipulation" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 shrink-0 animate-spin" />
                        Update league
                    </Button>
                </div>

                <!-- Create: nav -->
                <div v-else class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Button
                        type="button"
                        variant="outline"
                        class="order-2 min-h-11 touch-manipulation sm:order-1"
                        :disabled="wizardStep === 0 || form.processing"
                        @click="goPrevWizardStep"
                    >
                        Back
                    </Button>
                    <div class="order-1 flex flex-col gap-2 sm:order-2 sm:flex-row sm:justify-end">
                        <Button
                            v-if="wizardStep < lastWizardStepIndex"
                            type="button"
                            class="min-h-11 min-w-[8rem] touch-manipulation"
                            :disabled="form.processing"
                            @click="goNextWizardStep"
                        >
                            Continue
                        </Button>
                        <Button
                            v-else
                            type="submit"
                            class="min-h-11 min-w-[10rem] touch-manipulation"
                            :disabled="form.processing"
                        >
                            <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 shrink-0 animate-spin" />
                            Create league
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
