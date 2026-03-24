<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-background px-4 py-12 text-foreground sm:px-6 sm:py-16"
    >
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div
                class="absolute -top-40 left-1/2 h-[28rem] w-[28rem] -translate-x-1/2 rounded-full bg-dragontype/20 blur-3xl dark:bg-dragontype/25"
            />
            <div
                class="absolute -bottom-32 -right-24 h-80 w-80 rounded-full bg-watertype/15 blur-3xl dark:bg-watertype/20"
            />
            <div
                class="absolute bottom-1/4 -left-32 h-72 w-72 rounded-full bg-electrictype/10 blur-3xl dark:bg-electrictype/15"
            />
        </div>

        <div class="relative z-10 mx-auto flex w-full max-w-md flex-col items-center">
            <p class="mb-3 text-center text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground sm:text-sm">Pokémon VGC</p>
            <h1 class="mb-2 text-center text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ title }}
            </h1>
            <p v-if="description" class="mb-8 max-w-sm text-center text-sm text-muted-foreground sm:text-base">
                {{ description }}
            </p>
            <div
                class="w-full rounded-lg border border-border bg-card p-6 text-card-foreground shadow-sm dark:shadow-none"
            >
                <slot />
            </div>
            <p class="mt-6 text-center text-sm text-muted-foreground">
                <Link
                    :href="route('home')"
                    class="font-medium text-[oklch(0.48_0.19_25)] underline decoration-[oklch(0.53_0.195_25/0.4)] underline-offset-4 transition-colors hover:text-[oklch(0.4_0.17_25)] dark:text-primary dark:decoration-primary/45 dark:hover:text-primary/90"
                >
                    Back to home
                </Link>
            </p>
        </div>
    </div>
</template>
