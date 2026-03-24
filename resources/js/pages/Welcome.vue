<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const features = [
    {
        title: 'Leagues & drafts',
        body: 'Curate a Pokémon pool, run snake drafts, and keep every team’s roster organized in one place.',
        accentClass: 'border-l-dragontype bg-dragontype/5 dark:bg-dragontype/10',
    },
    {
        title: 'Matches & pokepaste',
        body: 'Prep sets with Showdown pokepastes, enforce pool rules, and log results as your season plays out.',
        accentClass: 'border-l-watertype bg-watertype/5 dark:bg-watertype/10',
    },
    {
        title: 'Trades & playoffs',
        body: 'Handle trades cleanly, then seed brackets and record knockout rounds when it’s time for playoffs.',
        accentClass: 'border-l-electrictype bg-electrictype/5 dark:bg-electrictype/10',
    },
    {
        title: 'Dex & usage',
        body: 'Browse the dex and usage stats so captains and admins can agree on costs and meta before the draft.',
        accentClass: 'border-l-grasstype bg-grasstype/5 dark:bg-grasstype/10',
    },
] as const;
</script>

<template>
    <Head title="VGC Draft" />

    <div
        class="relative flex min-h-dvh flex-col items-center justify-start overflow-x-hidden bg-background pl-[max(1rem,env(safe-area-inset-left))] pr-[max(1rem,env(safe-area-inset-right))] pt-[max(1.5rem,env(safe-area-inset-top))] pb-[max(3rem,env(safe-area-inset-bottom))] text-foreground sm:justify-center sm:pl-6 sm:pr-6 sm:pt-16 sm:pb-[max(4rem,env(safe-area-inset-bottom))] lg:pl-8 lg:pr-8"
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

        <div class="relative z-10 mx-auto flex w-full max-w-3xl flex-col items-center text-center">
            <p
                class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground sm:text-sm"
            >
                Pokémon VGC
            </p>
            <h1 class="mb-4 text-balance text-2xl font-semibold tracking-tight sm:text-4xl lg:text-5xl">
                Run your league draft from pool to playoffs
            </h1>
            <p class="mb-10 max-w-xl text-pretty text-base text-muted-foreground sm:text-lg">
                VGC Draft helps organizers and players manage league pools, snake drafts, team rosters, match
                prep, pokepaste validation, and playoff brackets—built for competitive doubles seasons.
            </p>

            <div v-if="$page.props.auth.user" class="flex w-full max-w-md flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap sm:justify-center">
                <Link
                    :href="route('dashboard')"
                    class="inline-flex w-full min-w-0 items-center justify-center rounded-md bg-[oklch(0.53_0.195_25)] px-6 py-2.5 text-sm font-medium text-[oklch(0.99_0_0)] shadow-sm ring-offset-background transition-colors hover:bg-[oklch(0.48_0.19_25)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 sm:w-auto sm:min-w-[10rem] dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary/90"
                >
                    Go to dashboard
                </Link>
            </div>
            <div v-else class="flex w-full max-w-md flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap sm:justify-center">
                <Link
                    :href="route('register')"
                    class="inline-flex w-full min-w-0 items-center justify-center rounded-md bg-[oklch(0.53_0.195_25)] px-6 py-2.5 text-sm font-medium text-[oklch(0.99_0_0)] shadow-sm ring-offset-background transition-colors hover:bg-[oklch(0.48_0.19_25)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 sm:w-auto sm:min-w-[10rem] dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary/90"
                >
                    Register
                </Link>
                <Link
                    :href="route('login')"
                    class="inline-flex w-full min-w-0 items-center justify-center rounded-md border border-border bg-card px-6 py-2.5 text-sm font-medium text-card-foreground shadow-sm ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 sm:w-auto sm:min-w-[10rem]"
                >
                    Log in
                </Link>
            </div>

            <div
                class="mt-14 w-full max-w-2xl space-y-3 text-left sm:mt-16 sm:grid sm:grid-cols-2 sm:gap-4 sm:space-y-0"
            >
                <div
                    v-for="item in features"
                    :key="item.title"
                    class="rounded-lg border border-border p-4 shadow-sm dark:shadow-none"
                    :class="item.accentClass"
                >
                    <h2 class="mb-1 text-sm font-semibold text-foreground">{{ item.title }}</h2>
                    <p class="text-sm leading-relaxed text-muted-foreground">{{ item.body }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
