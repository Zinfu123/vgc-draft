<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 48 48" id="Pokeball--Streamline-Kameleon" height="120" width="120">
        <rect width="100%" height="100%" fill="grey" />
        <path fill="#ffffff" d="M23.9998 37.9621c7.7118 0 13.9636 -6.2515 13.9636 -13.963 0 -7.7115 -6.2518 -13.963 -13.9636 -13.963 -7.7119 0 -13.9637 6.2515 -13.9637 13.963 0 7.7115 6.2518 13.963 13.9637 13.963Z" stroke-width="">
        </path>
        <path fill="#df2c2c" d="M23.9998 10.0361c-7.7119 0 -13.9637 6.2515 -13.9637 13.963h27.9273c0 -7.7115 -6.2518 -13.963 -13.9636 -13.963Z" stroke-width="2">
        </path>
        <path 
        fill="#fa5655" d="M23.9998 10.0361c-7.7119 0 -13.9637 6.2515 -13.9637 13.963h13.9637v-13.963Z" stroke-width="2">
        </path>
        <path 
        fill="#3e3e3f" d="M23.9998 18.7632c-2.2792 0 -4.2127 1.4582 -4.9318 3.4907h-8.9193c-0.0737 0.5789 -0.1113 1.1618 -0.1126 1.7454 0 0.5917 0.0411 1.1733 0.1126 1.7454h8.9193c0.7196 2.0325 2.6531 3.4907 4.9318 3.4907s4.2126 -1.4582 4.9318 -3.4907h8.9192c0.0716 -0.5721 0.1126 -1.1537 0.1126 -1.7454 0 -0.5917 -0.041 -1.1733 -0.1126 -1.7454h-8.9192c-0.7192 -2.0325 -2.6527 -3.4907 -4.9318 -3.4907Z" stroke-width="2">
        </path>
        <path 
        fill="#ffffff" d="M24 26.6165c0.6944 0 1.3603 -0.2758 1.8514 -0.7668 0.491 -0.491 0.7668 -1.1569 0.7668 -1.8513 0 -0.6943 -0.2758 -1.3602 -0.7668 -1.8512 -0.4911 -0.491 -1.157 -0.7668 -1.8514 -0.7668 -0.6944 0 -1.3603 0.2758 -1.8513 0.7668 -0.491 0.491 -0.7669 1.1569 -0.7669 1.8512 0 0.6944 0.2759 1.3603 0.7669 1.8513 0.491 0.491 1.1569 0.7668 1.8513 0.7668Z" stroke-width="2">
        </path>
        </svg>
</template>
