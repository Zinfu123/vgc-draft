<script setup lang="ts">
import AppTopNav from '@/components/AppTopNav.vue';
import DraftStartedBanner from '@/components/DraftStartedBanner.vue';
import V2PreviewBanner from '@/components/V2PreviewBanner.vue';
</script>

<template>
    <div class="flex min-h-screen w-full flex-col">
        <AppTopNav>
            <slot name="nav" />
        </AppTopNav>
        <V2PreviewBanner />
        <DraftStartedBanner />
        <main class="flex w-full flex-1 flex-col overflow-x-hidden">
            <slot />
        </main>
    </div>
</template>
