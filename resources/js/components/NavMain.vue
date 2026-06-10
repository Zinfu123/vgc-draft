<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavMainGroup } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    groups: NavMainGroup[];
}>();

const page = usePage();

function isNavActive(href: string): boolean {
    const url = page.url;
    if (url === href) {
        return true;
    }
    if (href === '/' || href === '') {
        return false;
    }

    return url.startsWith(`${href}/`);
}
</script>

<template>
    <template v-for="group in groups" :key="group.label">
        <SidebarGroup class="px-2 py-1.5">
            <SidebarGroupLabel
                class="text-muted-foreground mb-1 px-2 text-[0.65rem] font-semibold uppercase tracking-wider"
            >
                {{ group.label }}
            </SidebarGroupLabel>
            <SidebarMenu class="gap-0.5">
                <SidebarMenuItem v-for="item in group.items" :key="item.title">
                    <SidebarMenuButton as-child :is-active="isNavActive(item.href)" :tooltip="item.title">
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </template>
</template>
