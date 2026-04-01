<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { ChevronRight } from 'lucide-vue-next';

interface Teams {
    id: number;
    name: string;
    logo: string | null;
    set_wins: number;
    set_losses: number;
    victory_points: number;
    coach: string;
}

defineProps<{
    team: Teams;
}>();

function winPct(wins: number, losses: number): string {
    const total = wins + losses;
    if (total === 0) {
        return '—';
    }

    return (wins / total).toLocaleString('en-US', { style: 'percent', minimumFractionDigits: 1 });
}
</script>

<template>
    <Card
        class="relative h-full overflow-hidden border-border/80 bg-gradient-to-b from-primary/[0.07] via-card to-card py-0 shadow-sm transition-all duration-200 group-hover:-translate-y-0.5 group-hover:border-primary/30 group-hover:shadow-lg dark:from-primary/[0.12] dark:via-card dark:to-card"
    >
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-watertype/70 via-electrictype/60 to-dragontype/70 opacity-90 dark:opacity-100"
            aria-hidden="true"
        />
        <CardContent class="flex flex-col gap-4 p-5 sm:p-6">
            <div class="flex items-start gap-4">
                <div
                    v-if="team.logo"
                    class="ring-offset-background shrink-0 rounded-full ring-2 ring-border/80 ring-offset-2 shadow-md"
                >
                    <img :src="team.logo" alt="" class="size-14 rounded-full object-cover sm:size-16" />
                </div>
                <div
                    v-else
                    class="flex size-14 shrink-0 items-center justify-center rounded-full bg-muted text-base font-bold text-muted-foreground ring-2 ring-border/80 ring-offset-2 ring-offset-background shadow-md sm:size-16 sm:text-lg"
                    aria-hidden="true"
                >
                    {{ team.name.charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0 flex-1 pt-0.5">
                    <h3 class="text-balance font-semibold leading-tight tracking-tight sm:text-lg">{{ team.name }}</h3>
                    <p class="mt-1 line-clamp-2 text-xs text-muted-foreground sm:text-sm">
                        Coach <span class="font-medium text-foreground/90">{{ team.coach }}</span>
                    </p>
                </div>
            </div>

            <div class="space-y-3 rounded-xl border border-border/60 bg-muted/25 px-3 py-3 dark:bg-muted/15">
                <div class="text-center">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Set record</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums tracking-tight sm:text-4xl">
                        <span class="text-green-600 dark:text-green-400">{{ team.set_wins }}</span>
                        <span class="text-muted-foreground/80">–</span>
                        <span class="text-red-600 dark:text-red-400">{{ team.set_losses }}</span>
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ winPct(team.set_wins, team.set_losses) }} won</p>
                </div>
                <div class="flex items-center justify-center border-t border-border/50 pt-3">
                    <div class="inline-flex flex-col items-center gap-0.5">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Points</span>
                        <span
                            class="rounded-full bg-orange-500/15 px-3 py-1 text-lg font-bold tabular-nums text-orange-600 dark:bg-orange-500/20 dark:text-orange-400 sm:text-xl"
                        >
                            {{ team.victory_points }}
                        </span>
                    </div>
                </div>
            </div>

            <p
                class="flex items-center justify-center gap-1 text-center text-xs font-medium text-muted-foreground transition-colors group-hover:text-primary"
            >
                View roster
                <ChevronRight class="size-3.5 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
            </p>
        </CardContent>
    </Card>
</template>
