<script setup lang="ts">
defineProps<{
    id: string;
    label: string;
    modelValue: string;
    isUserInSet: boolean;
    error?: string;
}>();

defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div class="sm:col-span-6">
        <label :for="id" class="block text-sm/6 font-medium text-foreground">{{ label }}</label>
        <div class="mt-2">
            <input
                v-if="isUserInSet"
                :id="id"
                type="url"
                :value="modelValue"
                placeholder="https://replay.pokemonshowdown.com/..."
                class="block w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            />
            <a
                v-else-if="modelValue"
                :href="modelValue"
                target="_blank"
                rel="noopener noreferrer"
                class="block max-w-full truncate text-center text-sm text-muted-foreground transition-colors hover:text-primary"
            >
                {{ modelValue }}
            </a>
            <p v-if="error" class="mt-1 text-sm text-destructive">{{ error }}</p>
        </div>
    </div>
</template>
