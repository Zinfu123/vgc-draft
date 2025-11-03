<script setup lang="ts">

interface CostHeaders {
    cost: number;
}

const props = defineProps<{
    costHeaders: CostHeaders;
}>();

</script>

<template>

    <div class="flex flex-row justify-center w-full mt-4">
        <span v-for="cost in props.costHeaders" :key="cost" class="rounded bg-gray-800/85 dark:bg-muted/85 px-10 py-5 text-xs text-center">
            {{ cost }}
        </span>
    </div>
</template>
