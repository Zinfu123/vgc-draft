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

const sidebarNavItems = computed(() => [
    { title: 'Match Config', href: `/leagues/${props.leagueId}/admin/match-config` },
    { title: 'Draft', href: `/leagues/${props.leagueId}/admin/draft` },
    { title: 'League admins', href: `/leagues/${props.leagueId}/admin/league-admins` },
    { title: 'Playoffs', href: `/leagues/${props.leagueId}/admin/playoffs` },
    { title: 'Discord', href: `/leagues/${props.leagueId}/admin/discord` },
    { title: 'Trades', href: `/leagues/${props.leagueId}/admin/trades` },
    { title: 'Reopen match', href: `/leagues/${props.leagueId}/admin/reopen-match` },
    { title: 'Winner', href: `/leagues/${props.leagueId}/admin/winner` },
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
                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-11 w-full min-h-11 rounded-md border px-3 py-2 text-base shadow-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                :value="currentPath"
                aria-label="Choose admin section"
                @change="navigateAdminSection"
            >
                <option v-for="item in sidebarNavItems" :key="item.href" :value="item.href">
                    {{ item.title }}
                </option>
            </select>
        </div>

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-y-0 lg:space-x-12">
            <aside class="hidden w-full max-w-xl md:block lg:w-48">
                <nav class="flex flex-col space-y-1 space-x-0">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start touch-manipulation', { 'bg-muted': currentPath === item.href }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
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
