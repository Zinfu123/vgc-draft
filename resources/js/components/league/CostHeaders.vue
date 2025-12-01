<script setup lang="ts">
interface CostHeaders {
    cost: number;
}

const props = defineProps<{
    costHeaders: CostHeaders;
}>();
</script>

<template>
    <div class="mt-4 flex w-full flex-row justify-center">
        <span v-for="cost in props.costHeaders" :key="cost" class="rounded bg-gray-800/85 px-10 py-5 text-center text-xs dark:bg-muted/85">
            {{ cost }}
        </span>
    </div>
</template>
