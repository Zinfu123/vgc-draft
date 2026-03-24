<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useMobileLayout } from '@/composables/useMobileLayout';
import { type NavItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const { isMobile } = useMobileLayout();

function navigateSettingsSection(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    if (value) {
        router.visit(value);
    }
}

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: '/settings/profile',
    },
    {
        title: 'Password',
        href: '/settings/password',
    },
    {
        title: 'Appearance',
        href: '/settings/appearance',
    },
];

const page = usePage();

const currentPath = computed(() => (page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname : ''));
</script>

<template>
    <div class="px-4 py-6">
        <Heading title="Settings" description="Manage your profile and account settings" />

        <div v-if="isMobile" class="mb-6">
            <label class="mb-2 block text-sm font-medium text-foreground" for="settings-section-nav">Settings section</label>
            <select
                id="settings-section-nav"
                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-11 w-full min-h-11 rounded-md border px-3 py-2 text-base shadow-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                :value="currentPath"
                aria-label="Choose settings section"
                @change="navigateSettingsSection"
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
