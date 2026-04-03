<script setup lang="ts">
defineProps<{
    message: string;
}>();
</script>

<template>
    <p class="text-sm text-muted-foreground">{{ message }}</p>
</template>
