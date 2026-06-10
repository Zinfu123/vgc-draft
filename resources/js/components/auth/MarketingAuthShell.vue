<script setup lang="ts">
import BlobBackground from '@/components/BlobBackground.vue';
import { authLinkClass } from '@/lib/authLink';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-dvh flex-col items-center justify-start overflow-x-hidden bg-background pl-[max(1rem,env(safe-area-inset-left))] pr-[max(1rem,env(safe-area-inset-right))] pt-[max(1.5rem,env(safe-area-inset-top))] pb-[max(3rem,env(safe-area-inset-bottom))] text-foreground sm:justify-center sm:pl-6 sm:pr-6 sm:pt-16 sm:pb-[max(4rem,env(safe-area-inset-bottom))]"
    >
        <BlobBackground>
            <div class="absolute -top-40 left-1/2 h-[28rem] w-[28rem] -translate-x-1/2 rounded-full bg-dragontype/20 blur-3xl dark:bg-dragontype/25" />
            <div class="absolute -bottom-32 -right-24 h-80 w-80 rounded-full bg-watertype/15 blur-3xl dark:bg-watertype/20" />
            <div class="absolute bottom-1/4 -left-32 h-72 w-72 rounded-full bg-electrictype/10 blur-3xl dark:bg-electrictype/15" />
        </BlobBackground>

        <div class="relative z-10 mx-auto flex w-full max-w-md flex-col items-center">
            <p class="mb-3 text-center text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground sm:text-sm">Pokémon VGC</p>
            <h1 class="mb-2 text-center text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ title }}
            </h1>
            <p v-if="description" class="mb-8 max-w-sm text-center text-sm text-muted-foreground sm:text-base">
                {{ description }}
            </p>
            <div
                class="w-full rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm sm:p-6 dark:shadow-none"
            >
                <slot />
            </div>
            <p class="mt-6 text-center text-sm text-muted-foreground">
                <Link :href="route('home')" :class="authLinkClass">Back to home</Link>
            </p>
        </div>
    </div>
</template>
