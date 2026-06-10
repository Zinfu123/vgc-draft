<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface League {
    id: number;
    playoffs_enabled: boolean;
    status: number;
}

const props = defineProps<{ league: League }>();

const page = usePage();
const currentPath = computed(() => {
    try {
        return new URL((page.props as { ziggy?: { location?: string } }).ziggy?.location ?? '').pathname;
    } catch {
        return '';
    }
});

const items = computed(() => {
    const all = [
        { label: 'Overview', href: route('leagues.admin.league-admins', { league: props.league.id }) },
        { label: 'Match Config', href: route('leagues.admin.match-config', { league: props.league.id }) },
        { label: 'Pokémon Pool', href: route('leagues.admin.pokemon-pool', { league: props.league.id }) },
        { label: 'Draft', href: route('leagues.admin.draft', { league: props.league.id }) },
        { label: 'Trade Slots', href: route('leagues.admin.trades', { league: props.league.id }) },
        ...(props.league.playoffs_enabled
            ? [{ label: 'Playoffs', href: route('leagues.admin.playoffs', { league: props.league.id }) }]
            : []),
        { label: 'Reopen Match', href: route('leagues.admin.reopen-match', { league: props.league.id }) },
        { label: 'Discord', href: route('leagues.admin.discord', { league: props.league.id }) },
        { label: 'Finalize', href: route('leagues.admin.winner', { league: props.league.id }) },
    ];

    return all;
});

function isActive(href: string): boolean {
    try {
        return new URL(href).pathname === currentPath.value;
    } catch {
        return false;
    }
}
</script>

<template>
    <nav class="flex flex-wrap gap-1 border-b border-border pb-4" aria-label="Commissioner settings">
        <Link
            v-for="item in items"
            :key="item.href"
            :href="item.href"
            :class="[
                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                isActive(item.href)
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground dark:hover:bg-muted/30',
            ]"
        >
            {{ item.label }}
        </Link>
    </nav>
</template>
