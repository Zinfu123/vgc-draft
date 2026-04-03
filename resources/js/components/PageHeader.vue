<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    eyebrow: string;
    title: string;
    icon?: Component;
}>();
</script>

<template>
    <div class="space-y-2">
        <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
            <component :is="icon" v-if="icon" class="size-3.5 opacity-70" aria-hidden="true" />
            {{ eyebrow }}
        </p>
        <h2 class="text-balance text-2xl font-bold tracking-tight sm:text-3xl">{{ title }}</h2>
        <div v-if="$slots.default" class="max-w-2xl text-sm text-muted-foreground sm:text-base">
            <slot />
        </div>
    </div>
</template>
