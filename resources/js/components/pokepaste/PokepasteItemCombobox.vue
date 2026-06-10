<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { ChevronsUpDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        items: { id: number; label: string }[];
        excludedIds?: number[];
        inputId?: string;
        placeholder?: string;
        disabled?: boolean;
    }>(),
    {
        excludedIds: () => [],
        placeholder: '— Choose item —',
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const open = ref(false);

const visibleItems = computed(() => {
    const ex = new Set(props.excludedIds);
    return props.items.filter((i) => !ex.has(i.id) || i.id === props.modelValue);
});

const displayLabel = computed(() => {
    if (props.modelValue == null) {
        return props.placeholder;
    }
    const row = props.items.find((i) => i.id === props.modelValue);
    return row?.label ?? props.placeholder;
});

function select(id: number | null): void {
    emit('update:modelValue', id);
    open.value = false;
}

const triggerClass = cn(
    'border-input flex h-9 w-full items-center justify-between gap-2 rounded-md border bg-background px-3 py-1 text-left text-sm text-foreground shadow-xs',
    'hover:bg-accent/50 focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none',
    'disabled:cursor-not-allowed disabled:opacity-50',
);
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="inputId"
                variant="outline"
                role="combobox"
                type="button"
                :aria-expanded="open"
                :disabled="disabled"
                :class="triggerClass"
            >
                <span class="truncate">{{ displayLabel }}</span>
                <ChevronsUpDown class="size-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="min-w-[240px] w-72 max-w-[calc(100vw-2rem)] p-0" align="start">
            <Command class="bg-popover text-popover-foreground">
                <CommandInput class="h-9 border-0" placeholder="Search item…" />
                <CommandList>
                    <CommandEmpty>No item found.</CommandEmpty>
                    <CommandItem value="__clear__" @select="() => select(null)">
                        <span class="text-muted-foreground">No item</span>
                    </CommandItem>
                    <CommandItem
                        v-for="row in visibleItems"
                        :key="row.id"
                        :value="`${row.id}-${row.label}`"
                        @select="() => select(row.id)"
                    >
                        {{ row.label }}
                    </CommandItem>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
