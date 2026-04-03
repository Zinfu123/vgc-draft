<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useMobileLayout } from '@/composables/useMobileLayout';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const { isMobile } = useMobileLayout();

function navigateAdminSection(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    if (value) {
        router.visit(value);
    }
}

const props = defineProps<{
    leagueId: number;
    leagueName: string;
}>();

const sidebarNavGroups = computed(() => [
    {
        label: 'Setup',
        items: [
            { title: 'Match Config', href: `/leagues/${props.leagueId}/admin/match-config` },
            { title: 'Pokémon Pool', href: `/leagues/${props.leagueId}/admin/pokemon-pool` },
            { title: 'Draft', href: `/leagues/${props.leagueId}/admin/draft` },
        ],
    },
    {
        label: 'Season',
        items: [
            { title: 'Trades', href: `/leagues/${props.leagueId}/admin/trades` },
            { title: 'Playoffs', href: `/leagues/${props.leagueId}/admin/playoffs` },
        ],
    },
    {
        label: 'League',
        items: [
            { title: 'User Management', href: `/leagues/${props.leagueId}/admin/league-admins` },
            { title: 'Discord', href: `/leagues/${props.leagueId}/admin/discord` },
        ],
    },
]);

const page = usePage();
const currentPath = computed(() => (page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname : ''));
</script>

<template>
    <div class="px-4 py-6">
        <Heading :title="`${leagueName} — Admin`" description="Manage league configuration and settings" />

        <div v-if="isMobile" class="mb-6">
            <label class="mb-2 block text-sm font-medium text-foreground" for="admin-section-nav">Admin section</label>
            <select
                id="admin-section-nav"
                class="flex h-11 min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                :value="currentPath"
                aria-label="Choose admin section"
                @change="navigateAdminSection"
            >
                <optgroup v-for="group in sidebarNavGroups" :key="group.label" :label="group.label">
                    <option v-for="item in group.items" :key="item.href" :value="item.href">
                        {{ item.title }}
                    </option>
                </optgroup>
            </select>
        </div>

        <div class="flex flex-col space-y-8 lg:flex-row lg:space-x-12 lg:space-y-0">
            <aside class="hidden w-48 shrink-0 md:block">
                <nav class="flex flex-col gap-6">
                    <div v-for="group in sidebarNavGroups" :key="group.label">
                        <p class="mb-1 px-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            {{ group.label }}
                        </p>
                        <div class="flex flex-col space-y-1">
                            <Button
                                v-for="item in group.items"
                                :key="item.href"
                                variant="ghost"
                                :class="['w-full justify-start touch-manipulation', { 'bg-muted': currentPath === item.href }]"
                                as-child
                            >
                                <Link :href="item.href">{{ item.title }}</Link>
                            </Button>
                        </div>
                    </div>
                </nav>
            </aside>

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
