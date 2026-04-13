<script setup lang="ts">
import { router } from '@inertiajs/vue3';

interface Podium {
    first: string | null;
    second: string | null;
    third: string | null;
}

interface DraftConfig {
    draft_date: string | null;
    draft_start_at: string | null;
}

interface Leagues {
    id: number;
    name: string;
    draft_config: DraftConfig | null;
    set_start_date: string;
    logo: string | null;
    winner: string | null;
    status: number;
    podium?: Podium;
}

defineProps<{
    leagues: Leagues[];
}>();

const STATUS_LABELS: Record<number, string> = {
    0: 'Cancelled',
    1: 'Completed',
    2: 'Registration',
    3: 'Staging',
    4: 'Regular Season',
    5: 'Playoffs',
};

const STATUS_CLASSES: Record<number, string> = {
    0: 'bg-red-500/15 text-red-700 dark:bg-red-500/20 dark:text-red-300',
    1: 'bg-emerald-500/15 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
    2: 'bg-sky-500/15 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200',
    3: 'bg-amber-500/15 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200',
    4: 'bg-indigo-500/15 text-indigo-800 dark:bg-indigo-500/20 dark:text-indigo-200',
    5: 'bg-violet-500/15 text-violet-800 dark:bg-violet-500/20 dark:text-violet-200',
};

function statusLabel(league: Leagues): string {
    return STATUS_LABELS[league.status] ?? 'Unknown';
}

function statusClass(league: Leagues): string {
    return STATUS_CLASSES[league.status] ?? 'bg-muted text-muted-foreground';
}

function formatDraftDate(league: Leagues): string {
    const startAt = league.draft_config?.draft_start_at;
    if (startAt) {
        return new Date(startAt).toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    }
    const dateStr = league.draft_config?.draft_date;
    if (dateStr) {
        return new Date(dateStr).toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            timeZone: 'UTC',
        });
    }
    return 'TBD';
}

function hasPodium(league: Leagues): boolean {
    const p = league.podium;
    return Boolean(p && (p.first || p.second || p.third));
}

function openLeague(id: number): void {
    router.get(`/leagues/${id}`);
}
</script>

<template>
    <div
        v-for="league in leagues"
        :key="league.id"
        role="button"
        tabindex="0"
        class="group w-72 shrink-0 cursor-pointer overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-br from-primary/[0.08] via-card to-violet-500/[0.07] shadow-md ring-1 shadow-primary/5 ring-black/5 transition-all duration-300 outline-none hover:-translate-y-1 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/15 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-ring dark:border-primary/30 dark:from-primary/[0.12] dark:via-card dark:to-violet-500/[0.12] dark:ring-white/10 dark:shadow-primary/10 dark:hover:shadow-primary/20"
        @click="openLeague(league.id)"
        @keydown.enter.prevent="openLeague(league.id)"
        @keydown.space.prevent="openLeague(league.id)"
    >
        <div
            class="h-1.5 bg-gradient-to-r from-primary via-violet-500 to-cyan-500 opacity-[0.92] dark:from-primary dark:via-fuchsia-500 dark:to-sky-400"
            aria-hidden="true"
        />

        <div class="space-y-4 p-5">
            <div class="flex items-start justify-between gap-3">
                <h3 class="text-left text-base font-semibold leading-snug tracking-tight text-foreground">
                    {{ league.name }}
                </h3>
                <span
                    class="shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                    :class="statusClass(league)"
                >
                    {{ statusLabel(league) }}
                </span>
            </div>

            <div class="flex gap-4">
                <div
                    class="relative flex h-[5.5rem] w-[5.5rem] shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary/25 via-violet-500/15 to-cyan-500/10 p-1.5 shadow-inner ring-1 ring-white/70 dark:from-primary/30 dark:via-violet-500/20 dark:to-fuchsia-500/10 dark:ring-white/15"
                >
                    <img
                        v-if="league.logo !== null"
                        :src="league.logo"
                        alt=""
                        class="h-full w-full rounded-[0.65rem] object-contain"
                    />
                    <span
                        v-else
                        class="text-2xl font-bold tracking-tight text-primary/35 dark:text-primary/50"
                        aria-hidden="true"
                    >
                        {{ league.name.charAt(0) ? league.name.charAt(0).toUpperCase() : '?' }}
                    </span>
                </div>

                <div class="min-w-0 flex-1 space-y-2 text-left">
                    <template v-if="hasPodium(league)">
                        <div
                            v-if="league.podium?.first"
                            class="rounded-lg border border-amber-500/25 bg-amber-500/10 px-2.5 py-1.5 dark:border-amber-400/20 dark:bg-amber-500/15"
                        >
                            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">1st</p>
                            <p class="truncate text-sm font-semibold text-foreground">{{ league.podium.first }}</p>
                        </div>
                        <div
                            v-if="league.podium?.second"
                            class="rounded-lg border border-slate-400/30 bg-slate-500/10 px-2.5 py-1.5 dark:border-slate-400/25 dark:bg-slate-400/10"
                        >
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">2nd</p>
                            <p class="truncate text-sm font-semibold text-foreground">{{ league.podium.second }}</p>
                        </div>
                        <div
                            v-if="league.podium?.third"
                            class="rounded-lg border border-orange-600/25 bg-orange-600/10 px-2.5 py-1.5 dark:border-orange-500/25 dark:bg-orange-500/15"
                        >
                            <p class="text-[10px] font-bold uppercase tracking-wider text-orange-800 dark:text-orange-400">3rd</p>
                            <p class="truncate text-sm font-semibold text-foreground">{{ league.podium.third }}</p>
                        </div>
                    </template>
                    <div
                        v-else-if="league.winner !== null"
                        class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1.5 dark:border-emerald-400/25 dark:bg-emerald-500/15"
                    >
                        <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Winner</p>
                        <p class="truncate text-sm font-semibold text-foreground">{{ league.winner }}</p>
                    </div>
                    <template v-else>
                        <div class="rounded-lg border border-sky-500/20 bg-sky-500/5 px-2.5 py-1.5 dark:border-sky-400/20 dark:bg-sky-500/10">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-sky-700 dark:text-sky-300">Draft</p>
                            <p class="text-sm font-semibold text-foreground">{{ formatDraftDate(league) }}</p>
                        </div>
                    </template>
                    <div class="rounded-lg border border-violet-500/20 bg-violet-500/5 px-2.5 py-1.5 dark:border-violet-400/20 dark:bg-violet-500/10">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-violet-700 dark:text-violet-300">Season start</p>
                        <p class="text-sm font-semibold text-foreground">{{ league.set_start_date }}</p>
                    </div>
                </div>
            </div>

            <p
                class="text-center text-[11px] font-medium text-primary/70 opacity-0 transition-opacity group-hover:opacity-100 dark:text-primary/60"
            >
                Open league →
            </p>
        </div>
    </div>
</template>
