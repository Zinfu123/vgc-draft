<script setup lang="ts">
import { isReverbBroadcastClientConfigured } from '@/lib/broadcasting';
import { Link, usePage } from '@inertiajs/vue3';
import { useEchoNotification } from '@laravel/echo-vue';
import { ref } from 'vue';

interface DraftStartedPayload {
    league_id: number;
    league_name: string;
}

interface ActiveDraft {
    leagueId: number;
    leagueName: string;
}

const activeDraft = ref<ActiveDraft | null>(null);
const page = usePage();
const userId = page.props.auth?.user?.id;

if (isReverbBroadcastClientConfigured && userId) {
    useEchoNotification<DraftStartedPayload>(
        `App.Models.User.${userId}`,
        (payload) => {
            activeDraft.value = {
                leagueId: payload.league_id,
                leagueName: payload.league_name,
            };
        },
        'DraftStartedBroadcastNotification',
    );
}

function dismiss() {
    activeDraft.value = null;
}
</script>

<template>
    <div
        v-if="activeDraft"
        class="relative flex items-center justify-between gap-3 border-b border-primary/20 bg-primary/10 px-4 py-2.5 text-sm dark:border-primary/30 dark:bg-primary/15"
    >
        <div class="flex items-center gap-2">
            <span class="font-semibold text-primary">Draft started!</span>
            <span class="text-foreground/80">
                The draft for <span class="font-medium text-foreground">{{ activeDraft.leagueName }}</span> has begun.
            </span>
        </div>
        <div class="flex shrink-0 items-center gap-3">
            <Link
                :href="route('draft.detail', { league_id: activeDraft.leagueId })"
                class="rounded-md bg-primary px-3 py-1 text-xs font-semibold text-primary-foreground transition-opacity hover:opacity-90"
            >
                Go to draft
            </Link>
            <button
                type="button"
                class="text-foreground/50 transition-colors hover:text-foreground"
                aria-label="Dismiss"
                @click="dismiss"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
</template>
