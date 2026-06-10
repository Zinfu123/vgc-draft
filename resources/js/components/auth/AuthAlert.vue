<script setup lang="ts">
defineProps<{
    message: string;
}>();
</script>

<template>
    <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
        {{ message }}
    </div>
</template>
