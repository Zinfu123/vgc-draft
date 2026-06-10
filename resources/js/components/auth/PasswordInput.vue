<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    id: string;
    modelValue: string;
    tabindex?: number;
    autocomplete?: string;
    placeholder?: string;
    required?: boolean;
}>();

defineEmits<{
    'update:modelValue': [value: string];
}>();

const showPassword = ref(false);
</script>

<template>
    <div class="relative">
        <Input
            :id="id"
            :modelValue="modelValue"
            :type="showPassword ? 'text' : 'password'"
            :required="required"
            :tabindex="tabindex"
            :autocomplete="autocomplete"
            :placeholder="placeholder"
            class="pr-10"
            @update:modelValue="$emit('update:modelValue', $event as string)"
        />
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
            tabindex="-1"
            :aria-label="showPassword ? 'Hide password' : 'Show password'"
            @click="showPassword = !showPassword"
        >
            <EyeOff v-if="showPassword" class="h-4 w-4" />
            <Eye v-else class="h-4 w-4" />
        </button>
    </div>
</template>
