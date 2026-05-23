<script setup lang="ts">
import MatchPokepasteSection from '@/components/pokepaste/MatchPokepasteSection.vue';
import PokepastePastePanel from '@/components/pokepaste/PokepastePastePanel.vue';
import PokepastePublicTeamGrid, { type PokepasteViewCard } from '@/components/pokepaste/PokepastePublicTeamGrid.vue';
import type { HeldItemOption, NatureOption, RosterOption } from '@/components/pokepaste/PokepasteSlotCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    pokepaste_public_id: string;
    is_owner: boolean;
    edit_mode: boolean;
    paste_has_data: boolean;
    set: { id: number; league_id: number; round: number } | null;
    playoff_match: { id: number; slot: string; round_index: number; league_id: number } | null;
    league: { id: number; name: string } | null;
    team: { id: number; name: string } | null;
    roster: RosterOption[];
    slots: import('@/lib/pokepaste/showdownExport').PokepasteSlot[];
    held_items: HeldItemOption[];
    all_tera_types: string[];
    natures: NatureOption[];
    view_cards: PokepasteViewCard[];
    showdown_export: string;
}>();

const pageTitle = computed(() => {
    if (props.team?.name && props.league?.name) {
        return `${props.team.name} · ${props.league.name}`;
    }
    return 'Team paste';
});

const editHref = computed(() => route('pokepaste.show', { pokepaste: props.pokepaste_public_id, edit: 1 }));
const viewHref = computed(() => route('pokepaste.show', { pokepaste: props.pokepaste_public_id, view: 1 }));

const breadcrumbs = computed<BreadcrumbItemType[]>(() => {
    const items: BreadcrumbItemType[] = [{ title: 'Dashboard', href: route('dashboard') }];
    if (props.league) {
        items.push({ title: props.league.name, href: route('leagues.detail', { league: props.league.id }) });
    }
    if (props.set) {
        items.push({ title: 'Match', href: route('sets.show', { set_id: props.set.id }) });
    } else if (props.playoff_match) {
        items.push({
            title: 'Playoffs',
            href: route('leagues.playoffs', { league: props.playoff_match.league_id }),
        });
    }
    items.push({ title: 'Team paste', href: route('pokepaste.show', { pokepaste: props.pokepaste_public_id }) });
    return items;
});
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout v-if="edit_mode" :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-5xl px-4 py-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Match team paste</h1>
                    <p v-if="set && league" class="text-muted-foreground mt-1 text-sm">
                        {{ league.name }} · Round {{ set.round }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button v-if="paste_has_data" variant="outline" size="sm" as-child>
                        <Link :href="viewHref">View public layout</Link>
                    </Button>
                    <Link
                        v-if="set"
                        :href="route('sets.show', { set_id: set.id })"
                        class="text-primary text-sm font-medium hover:underline"
                    >
                        Back to match
                    </Link>
                </div>
            </div>

            <MatchPokepasteSection
                :pokepaste-public-id="pokepaste_public_id"
                :roster="roster"
                :slots="slots"
                :held-items="held_items"
                :all-tera-types="all_tera_types"
                :natures="natures"
                :showdown-export="showdown_export"
            />
        </div>
    </AppLayout>

    <div
        v-else
        class="from-background via-background min-h-screen bg-gradient-to-b to-zinc-950 text-zinc-100 dark:to-zinc-950"
    >
        <header
            class="border-border/40 bg-background/80 sticky top-0 z-10 border-b backdrop-blur-md dark:border-zinc-800 dark:bg-zinc-950/80"
        >
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4">
                <div class="min-w-0">
                    <p class="text-primary text-xs font-semibold tracking-wider uppercase">VGC Draft</p>
                    <h1 class="truncate text-xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">
                        {{ team?.name ?? 'Team paste' }}
                    </h1>
                    <p v-if="league" class="text-muted-foreground mt-0.5 text-sm dark:text-zinc-400">
                        {{ league.name }}
                        <template v-if="set"> · Round {{ set.round }}</template>
                    </p>
                </div>
                <nav class="flex flex-wrap items-center justify-end gap-2">
                    <Link
                        v-if="$page.props.auth?.user && set"
                        :href="route('sets.show', { set_id: set.id })"
                        class="text-muted-foreground hover:text-foreground rounded-md px-3 py-2 text-sm font-medium dark:text-zinc-400 dark:hover:text-zinc-200"
                    >
                        Match
                    </Link>
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="route('dashboard')"
                        class="text-muted-foreground hover:text-foreground rounded-md px-3 py-2 text-sm font-medium dark:text-zinc-400 dark:hover:text-zinc-200"
                    >
                        Dashboard
                    </Link>
                    <template v-if="!$page.props.auth?.user">
                        <Link
                            :href="route('login')"
                            class="text-muted-foreground hover:text-foreground rounded-md px-3 py-2 text-sm dark:text-zinc-400 dark:hover:text-zinc-200"
                        >
                            Log in
                        </Link>
                    </template>
                    <Button v-if="is_owner" as-child size="sm" class="shrink-0">
                        <Link :href="editHref">Edit paste</Link>
                    </Button>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-10 space-y-8">
            <PokepastePastePanel
                v-if="showdown_export.trim()"
                :model-value="showdown_export"
                :pokepaste-public-id="pokepaste_public_id"
                readonly
            />
            <PokepastePublicTeamGrid :cards="view_cards" />
        </main>

        <footer class="text-muted-foreground mx-auto max-w-6xl px-4 py-8 text-center text-xs dark:text-zinc-600">
            Shared team paste · {{ pageTitle }}
        </footer>
    </div>
</template>
