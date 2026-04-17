<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';

defineProps<{
    // Accepted for backward compatibility — breadcrumbs are no longer rendered in the top nav
    breadcrumbs?: { title: string; href?: string }[];
}>();
</script>

<template>
    <AppSidebarLayout>
        <template v-if="$slots.nav" #nav>
            <slot name="nav" />
        </template>
        <slot />
    </AppSidebarLayout>
</template>
