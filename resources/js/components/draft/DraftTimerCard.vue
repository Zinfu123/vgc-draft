<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { router } from '@inertiajs/vue3';
import { Pause, Play, SkipForward } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface Props {
    leagueId: number;
    pickTimerEnabled: boolean;
    pickTimerSeconds: number | null;
    currentDeadlineAt: string | null;
    pausedAt: string | null;
    pausedRemainingSeconds: number | null;
    quietHoursEnabled: boolean;
    quietHoursStart: string | null;
    quietHoursEnd: string | null;
    quietHoursTimezone: string | null;
    canManage: boolean;
}

const props = defineProps<Props>();

const now = ref(Date.now());
let intervalId: number | null = null;

onMounted(() => {
    intervalId = window.setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    if (intervalId !== null) {
        window.clearInterval(intervalId);
        intervalId = null;
    }
});

const isPaused = computed(() => props.pausedAt !== null);

const minutesFromTimeString = (value: string | null): number | null => {
    if (!value) return null;
    const match = /^(\d{1,2}):(\d{2})/.exec(value);
    if (!match) return null;
    const hour = Number(match[1]);
    const minute = Number(match[2]);
    if (Number.isNaN(hour) || Number.isNaN(minute)) return null;
    return hour * 60 + minute;
};

const currentMinutesInTimezone = (timezone: string): number => {
    try {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
        }).formatToParts(new Date(now.value));
        let hour = 0;
        let minute = 0;
        for (const part of parts) {
            if (part.type === 'hour') hour = Number(part.value);
            if (part.type === 'minute') minute = Number(part.value);
        }
        return hour * 60 + minute;
    } catch {
        const d = new Date(now.value);
        return d.getHours() * 60 + d.getMinutes();
    }
};

const isQuietHoursActive = computed(() => {
    if (!props.quietHoursEnabled) return false;
    const start = minutesFromTimeString(props.quietHoursStart);
    const end = minutesFromTimeString(props.quietHoursEnd);
    if (start === null || end === null || start === end) return false;
    const current = currentMinutesInTimezone(props.quietHoursTimezone || 'America/New_York');
    return start < end ? current >= start && current < end : current >= start || current < end;
});

const remainingSeconds = computed(() => {
    if (isPaused.value) {
        return props.pausedRemainingSeconds ?? 0;
    }
    if (!props.currentDeadlineAt) return null;
    const deadlineMs = new Date(props.currentDeadlineAt).getTime();
    return Math.max(0, Math.floor((deadlineMs - now.value) / 1000));
});

const formattedRemaining = computed(() => {
    const seconds = remainingSeconds.value;
    if (seconds === null) return '—';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    const pad = (value: number) => value.toString().padStart(2, '0');
    if (hours > 0) {
        return `${hours}:${pad(minutes)}:${pad(secs)}`;
    }
    return `${pad(minutes)}:${pad(secs)}`;
});

const colorClass = computed(() => {
    if (isPaused.value) {
        return 'bg-gray-100 text-gray-700 border-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600';
    }
    if (isQuietHoursActive.value) {
        return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/30 dark:text-blue-300 dark:border-blue-800';
    }
    const seconds = remainingSeconds.value ?? 0;
    if (seconds <= 5 * 60) {
        return 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/30 dark:text-red-300 dark:border-red-800';
    }
    if (seconds <= 30 * 60) {
        return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-300 dark:border-amber-800';
    }
    return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-300 dark:border-emerald-800';
});

const statusLabel = computed(() => {
    if (isPaused.value) return 'Paused by commissioner';
    if (isQuietHoursActive.value) return 'Quiet hours — auto-skip paused';
    return 'Time remaining';
});

const showCard = computed(() => props.pickTimerEnabled);

const isSubmitting = ref(false);

const postTimer = (routeName: string, payload: Record<string, unknown>) => {
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    router.post(route(routeName), { league_id: props.leagueId, ...payload }, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const togglePause = () => {
    if (isPaused.value) {
        postTimer('draft.timer.resume', {});
    } else {
        postTimer('draft.timer.pause', {});
    }
};

const adjust = (deltaSeconds: number) => {
    postTimer('draft.timer.adjust', { delta_seconds: deltaSeconds });
};

const forceSkip = () => {
    if (!window.confirm('Skip the current turn? The next team will be up immediately.')) {
        return;
    }
    postTimer('draft.timer.skip', {});
};
</script>

<template>
    <div v-if="showCard" class="flex flex-col gap-2 rounded-xl border px-4 py-3 shadow-sm" :class="colorClass">
        <div class="flex items-center gap-3">
            <div class="flex flex-col">
                <span class="text-[10px] font-semibold uppercase tracking-wider opacity-75">{{ statusLabel }}</span>
                <span class="font-mono text-2xl font-bold tabular-nums">{{ formattedRemaining }}</span>
            </div>
        </div>
        <div v-if="canManage" class="flex flex-wrap items-center gap-1.5 pt-1">
            <Button size="sm" variant="outline" :disabled="isSubmitting" @click="togglePause">
                <Pause v-if="!isPaused" class="size-3.5" />
                <Play v-else class="size-3.5" />
                {{ isPaused ? 'Resume' : 'Pause' }}
            </Button>
            <ButtonGroup>
                <Button size="sm" variant="outline" :disabled="isSubmitting" @click="adjust(-30 * 60)">-30m</Button>
                <Button size="sm" variant="outline" :disabled="isSubmitting" @click="adjust(30 * 60)">+30m</Button>
                <Button size="sm" variant="outline" :disabled="isSubmitting" @click="adjust(60 * 60)">+1h</Button>
            </ButtonGroup>
            <Button size="sm" variant="destructive" :disabled="isSubmitting" @click="forceSkip">
                <SkipForward class="size-3.5" />
                Skip
            </Button>
        </div>
    </div>
</template>
