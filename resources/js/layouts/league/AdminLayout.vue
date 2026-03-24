<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    leagueId: number;
    leagueName: string;
}>();

const sidebarNavItems = computed(() => [
    { title: 'Match Config', href: `/leagues/${props.leagueId}/admin/match-config` },
    { title: 'Discord', href: `/leagues/${props.leagueId}/admin/discord` },
    { title: 'Trades', href: `/leagues/${props.leagueId}/admin/trades` },
    { title: 'Winner', href: `/leagues/${props.leagueId}/admin/winner` },
]);

const page = usePage();
const currentPath = computed(() => (page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname : ''));
</script>

<template>
    <div class="px-4 py-6">
        <Heading :title="`${leagueName} — Admin`" description="Manage league configuration and settings" />

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-y-0 lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-y-1 space-x-0">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': currentPath === item.href }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
