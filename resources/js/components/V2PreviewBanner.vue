<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const preview = computed(() => {
    const props = page.props as {
        v2Preview?: { visible: boolean; links: { module: string; href: string }[] };
    };

    return props.v2Preview ?? { visible: false, links: [] };
});
</script>

<template>
    <div v-if="preview.visible && preview.links.length" class="flex items-center gap-2 border-b border-amber-500/30 bg-amber-500/10 px-4 py-1.5 text-xs">
        <span class="font-medium text-amber-800 dark:text-amber-200">V2 preview</span>
        <Link
            v-for="link in preview.links"
            :key="link.module"
            :href="link.href"
            class="rounded px-2 py-0.5 text-amber-900 underline-offset-2 hover:underline dark:text-amber-100"
        >
            {{ link.module }}
        </Link>
    </div>
</template>
