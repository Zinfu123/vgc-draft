<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { ChevronsUpDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type OptionValue = string | number;

const props = withDefaults(
    defineProps<{
        modelValue: OptionValue | null;
        options: { value: OptionValue; label: string }[];
        inputId?: string;
        placeholder?: string;
        searchPlaceholder?: string;
        emptyMessage?: string;
        noneLabel?: string;
        disabled?: boolean;
        /** When true, treat `modelValue === ''` as no selection (for move slugs). */
        treatEmptyStringAsEmpty?: boolean;
        /** Emitted when the user picks the none row; default null. Use '' for move slots. */
        clearValue?: OptionValue | null;
        /** Extra text included for search/filter (e.g. move slug). Not shown visually. */
        searchHint?: (option: { value: OptionValue; label: string }) => string;
    }>(),
    {
        placeholder: '— Choose —',
        searchPlaceholder: 'Search…',
        emptyMessage: 'No results.',
        noneLabel: '— None —',
        disabled: false,
        treatEmptyStringAsEmpty: false,
        clearValue: null,
        searchHint: () => '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: OptionValue | null];
}>();

const open = ref(false);

function valuesEqual(a: OptionValue | null | undefined, b: OptionValue | null | undefined): boolean {
    return a === b;
}

const showPlaceholder = computed(() => {
    const v = props.modelValue;
    if (v === null || v === undefined) {
        return true;
    }
    if (props.treatEmptyStringAsEmpty && v === '') {
        return true;
    }

    return false;
});

const displayLabel = computed(() => {
    if (showPlaceholder.value) {
        return props.placeholder;
    }
    const row = props.options.find((o) => valuesEqual(o.value, props.modelValue));
    return row?.label ?? props.placeholder;
});

function select(value: OptionValue | null): void {
    emit('update:modelValue', value);
    open.value = false;
}

function clear(): void {
    emit('update:modelValue', props.clearValue);
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
                :class="cn(triggerClass, showPlaceholder && 'text-muted-foreground')"
            >
                <span class="truncate">{{ displayLabel }}</span>
                <ChevronsUpDown class="size-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="min-w-[240px] w-72 max-w-[calc(100vw-2rem)] p-0" align="start">
            <Command class="bg-popover text-popover-foreground">
                <CommandInput class="h-9 border-0" :placeholder="searchPlaceholder" />
                <CommandList>
                    <CommandEmpty>{{ emptyMessage }}</CommandEmpty>
                    <CommandItem value="__clear__" @select="() => clear()">
                        <span class="text-muted-foreground">{{ noneLabel }}</span>
                    </CommandItem>
                    <CommandItem
                        v-for="row in options"
                        :key="`${row.value}`"
                        :value="String(row.value)"
                        @select="() => select(row.value)"
                    >
                        {{ row.label }}
                        <span v-if="searchHint(row)" class="sr-only">{{ searchHint(row) }}</span>
                    </CommandItem>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
