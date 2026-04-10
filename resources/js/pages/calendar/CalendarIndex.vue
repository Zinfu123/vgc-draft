<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface DraftDay {
    league_id: number;
    league_name: string;
    date: string;
}

interface MatchWeekStart {
    league_id: number;
    league_name: string;
    date: string;
    round_label: string;
}

interface ScheduledMatch {
    set_id: number;
    league_id: number;
    opponent_team_name: string;
    scheduled_at: string;
}

interface Props {
    draftDays: DraftDay[];
    matchWeekStarts: MatchWeekStart[];
    scheduledMatches: ScheduledMatch[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Calendar', href: '/calendar' }];

const today = new Date();
const currentYear = ref(today.getFullYear());
const currentMonth = ref(today.getMonth());

function prevMonth(): void {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
}

function nextMonth(): void {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
}

const monthLabel = computed(() =>
    new Date(currentYear.value, currentMonth.value, 1).toLocaleString(undefined, {
        month: 'long',
        year: 'numeric',
    }),
);

const daysInGrid = computed(() => {
    const firstDay = new Date(currentYear.value, currentMonth.value, 1);
    const lastDay = new Date(currentYear.value, currentMonth.value + 1, 0);
    const startPadding = firstDay.getDay();
    const days: Array<{ date: Date | null; isCurrentMonth: boolean }> = [];

    for (let i = 0; i < startPadding; i++) {
        days.push({ date: null, isCurrentMonth: false });
    }

    for (let d = 1; d <= lastDay.getDate(); d++) {
        days.push({
            date: new Date(currentYear.value, currentMonth.value, d),
            isCurrentMonth: true,
        });
    }

    return days;
});

function toDateKey(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

const draftDayMap = computed(() => {
    const map = new Map<string, DraftDay[]>();
    for (const d of props.draftDays) {
        const key = d.date.slice(0, 10);
        if (!map.has(key)) {
            map.set(key, []);
        }
        map.get(key)!.push(d);
    }
    return map;
});

const matchWeekMap = computed(() => {
    const map = new Map<string, MatchWeekStart[]>();
    for (const m of props.matchWeekStarts) {
        const key = m.date.slice(0, 10);
        if (!map.has(key)) {
            map.set(key, []);
        }
        map.get(key)!.push(m);
    }
    return map;
});

const scheduledMatchMap = computed(() => {
    const map = new Map<string, ScheduledMatch[]>();
    for (const s of props.scheduledMatches) {
        const d = new Date(s.scheduled_at);
        const key = toDateKey(d);
        if (!map.has(key)) {
            map.set(key, []);
        }
        map.get(key)!.push(s);
    }
    return map;
});

function isToday(date: Date): boolean {
    return toDateKey(date) === toDateKey(today);
}

function eventsForDate(date: Date): {
    draftDays: DraftDay[];
    matchWeekStarts: MatchWeekStart[];
    scheduledMatches: ScheduledMatch[];
} {
    const key = toDateKey(date);
    return {
        draftDays: draftDayMap.value.get(key) ?? [],
        matchWeekStarts: matchWeekMap.value.get(key) ?? [],
        scheduledMatches: scheduledMatchMap.value.get(key) ?? [],
    };
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    });
}

const upcomingEvents = computed(() => {
    const now = new Date();
    const cutoff = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);

    const events: Array<{
        type: 'draft' | 'match_week' | 'scheduled_match';
        label: string;
        date: Date;
        href: string;
    }> = [];

    for (const d of props.draftDays) {
        const date = new Date(d.date + 'T00:00:00');
        if (date >= now && date <= cutoff) {
            events.push({
                type: 'draft',
                label: `${d.league_name} — Draft Day`,
                date,
                href: route('leagues.draft', { league: d.league_id }),
            });
        }
    }

    for (const m of props.matchWeekStarts) {
        const date = new Date(m.date + 'T00:00:00');
        if (date >= now && date <= cutoff) {
            events.push({
                type: 'match_week',
                label: `${m.league_name} — ${m.round_label}`,
                date,
                href: route('leagues.matches', { league: m.league_id }),
            });
        }
    }

    for (const s of props.scheduledMatches) {
        const date = new Date(s.scheduled_at);
        if (date >= now && date <= cutoff) {
            events.push({
                type: 'scheduled_match',
                label: `vs ${s.opponent_team_name}`,
                date,
                href: route('sets.show', { set_id: s.set_id }),
            });
        }
    }

    return events.sort((a, b) => a.date.getTime() - b.date.getTime());
});

const weekDayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Calendar" />
        <div class="mx-auto w-full max-w-7xl px-4 pt-4 pb-10 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-6">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-foreground">Calendar</h1>
                    <p class="text-muted-foreground mt-1 text-sm">Draft days, match weeks, and your scheduled matches.</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <div class="border-border bg-card rounded-lg border shadow-sm">
                        <div class="flex items-center justify-between border-b border-border px-4 py-3">
                            <Button variant="outline" size="sm" @click="prevMonth">‹</Button>
                            <span class="text-sm font-semibold text-foreground">{{ monthLabel }}</span>
                            <Button variant="outline" size="sm" @click="nextMonth">›</Button>
                        </div>

                        <div class="grid grid-cols-7 border-b border-border">
                            <div
                                v-for="day in weekDayLabels"
                                :key="day"
                                class="py-2 text-center text-xs font-medium text-muted-foreground"
                            >
                                {{ day }}
                            </div>
                        </div>

                        <div class="grid grid-cols-7">
                            <div
                                v-for="(cell, idx) in daysInGrid"
                                :key="idx"
                                class="min-h-16 border-b border-r border-border p-1 last:border-r-0"
                                :class="{ 'bg-muted/20': !cell.isCurrentMonth }"
                            >
                                <template v-if="cell.date">
                                    <div
                                        class="mb-1 flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium"
                                        :class="
                                            isToday(cell.date)
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-foreground'
                                        "
                                    >
                                        {{ cell.date.getDate() }}
                                    </div>

                                    <div class="flex flex-col gap-0.5">
                                        <Link
                                            v-for="(dd, di) in eventsForDate(cell.date).draftDays"
                                            :key="`dd-${di}`"
                                            :href="route('leagues.draft', { league: dd.league_id })"
                                            class="truncate rounded bg-purple-100 px-1 py-0.5 text-xs font-medium text-purple-800 hover:bg-purple-200 dark:bg-purple-950/50 dark:text-purple-300 dark:hover:bg-purple-900/50"
                                            :title="`${dd.league_name} — Draft Day`"
                                        >
                                            {{ dd.league_name }}
                                        </Link>
                                        <Link
                                            v-for="(mw, mi) in eventsForDate(cell.date).matchWeekStarts"
                                            :key="`mw-${mi}`"
                                            :href="route('leagues.matches', { league: mw.league_id })"
                                            class="truncate rounded bg-blue-100 px-1 py-0.5 text-xs font-medium text-blue-800 hover:bg-blue-200 dark:bg-blue-950/50 dark:text-blue-300 dark:hover:bg-blue-900/50"
                                            :title="`${mw.league_name} — ${mw.round_label}`"
                                        >
                                            {{ mw.round_label }}
                                        </Link>
                                        <Link
                                            v-for="(sm, si) in eventsForDate(cell.date).scheduledMatches"
                                            :key="`sm-${si}`"
                                            :href="route('sets.show', { set_id: sm.set_id })"
                                            class="truncate rounded bg-green-100 px-1 py-0.5 text-xs font-medium text-green-800 hover:bg-green-200 dark:bg-green-950/50 dark:text-green-300 dark:hover:bg-green-900/50"
                                            :title="`vs ${sm.opponent_team_name} — ${formatTime(sm.scheduled_at)}`"
                                        >
                                            vs {{ sm.opponent_team_name }}
                                        </Link>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="border-t border-border px-4 py-3">
                            <div class="flex flex-wrap gap-4 text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span class="h-3 w-3 rounded bg-purple-100 dark:bg-purple-950/50"></span>
                                    <span class="text-muted-foreground">Draft day</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="h-3 w-3 rounded bg-blue-100 dark:bg-blue-950/50"></span>
                                    <span class="text-muted-foreground">Match round</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="h-3 w-3 rounded bg-green-100 dark:bg-green-950/50"></span>
                                    <span class="text-muted-foreground">Scheduled match</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="border-border bg-card rounded-lg border shadow-sm">
                        <div class="border-b border-border px-4 py-3">
                            <h2 class="text-sm font-semibold text-foreground">Upcoming (next 7 days)</h2>
                        </div>
                        <div class="px-4 py-3">
                            <p v-if="upcomingEvents.length === 0" class="text-muted-foreground text-sm">
                                No events in the next 7 days.
                            </p>
                            <ul v-else class="flex flex-col gap-3">
                                <li v-for="(event, idx) in upcomingEvents" :key="idx" class="flex items-start gap-2">
                                    <span
                                        class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                                        :class="{
                                            'bg-purple-500': event.type === 'draft',
                                            'bg-blue-500': event.type === 'match_week',
                                            'bg-green-500': event.type === 'scheduled_match',
                                        }"
                                    ></span>
                                    <div class="min-w-0">
                                        <Link
                                            :href="event.href"
                                            class="text-sm font-medium text-foreground hover:underline"
                                        >
                                            {{ event.label }}
                                        </Link>
                                        <p class="text-muted-foreground text-xs">
                                            {{
                                                event.date.toLocaleString(undefined, {
                                                    weekday: 'short',
                                                    month: 'short',
                                                    day: 'numeric',
                                                    hour: event.type === 'scheduled_match' ? 'numeric' : undefined,
                                                    minute: event.type === 'scheduled_match' ? '2-digit' : undefined,
                                                })
                                            }}
                                        </p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
