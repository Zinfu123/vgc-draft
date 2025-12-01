<script setup lang="ts">
interface Pool {
    number: number;
    team: Teams[];
}

interface props {
    pool: Pool;
}

const props = defineProps<props>();
</script>

<template>
    <div class="flex flex-col items-center justify-center"></div>
</template>
