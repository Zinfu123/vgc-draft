<script setup lang="ts">
import NotificationCenter from '@/components/NotificationCenter.vue';
import ReferencesDropdown from '@/components/ReferencesDropdown.vue';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { NotificationCounts, User } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, LayoutDashboard, Trophy } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();

const user = computed(() => page.props.auth.user as User);
const notifications = computed(() => (page.props as { notifications?: NotificationCounts | null }).notifications);

const moduleMap: Record<string, string> = {
    '/dashboard': 'Dashboard',
    '/leagues': 'Leagues',
    '/calendar': 'Calendar',
    '/match-prep': 'Match Prep',
    '/draft': 'Draft',
    '/match': 'Match',
    '/pokedex': 'Pokédex',
    '/team-coverage': 'Team Coverage',
    '/pool-templates': 'Pool Templates',
    '/usage-stats': 'Usage Stats',
    '/pokepaste': 'Poképaste',
    '/pools': 'Pools',
    '/teams': 'Teams',
    '/profile': 'Profile',
};

const moduleName = computed(() => {
    const url = page.url;
    for (const [prefix, name] of Object.entries(moduleMap)) {
        if (url === prefix || url.startsWith(`${prefix}/`) || url.startsWith(`${prefix}?`)) {
            return name;
        }
    }
    return null;
});

const quickNavItems = [
    { title: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    { title: 'Calendar', href: '/calendar', icon: CalendarDays },
    { title: 'Leagues', href: '/leagues', icon: Trophy },
];

const isCurrentRoute = (href: string): boolean => {
    if (page.url === href) {
        return true;
    }
    if (href === '/' || href === '') {
        return false;
    }
    return page.url.startsWith(`${href}/`);
};
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center gap-3 border-b border-sidebar-border/70 bg-background px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 sm:px-6 md:px-4"
    >
        <!-- Left: module name -->
        <div class="flex items-center">
            <span v-if="moduleName" class="text-sm font-semibold text-foreground">
                {{ moduleName }}
            </span>
        </div>

        <!-- Center: slot for page-specific content (e.g. sub-nav, breadcrumbs) -->
        <div class="flex flex-1 items-center justify-center px-2">
            <slot />
        </div>

        <!-- Right: quick nav + references + notification center -->
        <div class="flex items-center gap-1">
            <TooltipProvider :delay-duration="0">
                <template v-for="item in quickNavItems" :key="item.href">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Link
                                :href="item.href"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-md transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                :class="
                                    isCurrentRoute(item.href)
                                        ? 'bg-accent text-accent-foreground'
                                        : 'text-muted-foreground'
                                "
                            >
                                <component :is="item.icon" class="size-5" />
                                <span class="sr-only">{{ item.title }}</span>
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">
                            <p>{{ item.title }}</p>
                        </TooltipContent>
                    </Tooltip>
                </template>
            </TooltipProvider>

            <ReferencesDropdown />

            <NotificationCenter :user="user" :notifications="notifications" />
        </div>
    </header>
</template>
