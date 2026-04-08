<script setup lang="ts">
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { ChevronsUpDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';

export interface MoveOption {
    slug: string;
    label: string;
    type_slug: string | null;
    damage_class: string | null;
    power: number | null;
    accuracy: number | null;
    short_effect: string | null;
}

const props = defineProps<{
    modelValue: string;
    options: MoveOption[];
    excludedSlugs?: string[];
    inputId?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const open = ref(false);
const hoveredSlug = ref<string | null>(null);

const visibleOptions = computed(() => {
    const excluded = new Set(props.excludedSlugs ?? []);
    return props.options.filter((m) => !excluded.has(m.slug) || m.slug === props.modelValue);
});

const selectedMove = computed(() => props.options.find((m) => m.slug === props.modelValue) ?? null);

const hoveredMove = computed(() => {
    if (!hoveredSlug.value) return selectedMove.value;
    return props.options.find((m) => m.slug === hoveredSlug.value) ?? null;
});

function select(slug: string): void {
    emit('update:modelValue', slug);
    open.value = false;
    hoveredSlug.value = null;
}

function clear(): void {
    emit('update:modelValue', '');
    open.value = false;
    hoveredSlug.value = null;
}

// Pokemon type colors matching the official palette
const TYPE_BG: Record<string, string> = {
    normal: '#A8A878',
    fire: '#F08030',
    water: '#6890F0',
    electric: '#F8D030',
    grass: '#78C850',
    ice: '#98D8D8',
    fighting: '#C03028',
    poison: '#A040A0',
    ground: '#E0C068',
    flying: '#A890F0',
    psychic: '#F85888',
    bug: '#A8B820',
    rock: '#B8A038',
    ghost: '#705898',
    dragon: '#7038F8',
    dark: '#705848',
    steel: '#B8B8D0',
    fairy: '#EE99AC',
    stellar: '#3DC7E0',
};

const DARK_TEXT_TYPES = new Set(['electric', 'ice', 'bug', 'ground', 'steel', 'fairy', 'normal']);

function typeStyle(slug: string | null): string {
    if (!slug) return 'background:#888;color:#fff';
    const bg = TYPE_BG[slug] ?? '#888';
    const color = DARK_TEXT_TYPES.has(slug) ? '#1a1a1a' : '#fff';
    return `background:${bg};color:${color}`;
}

function typeLabel(slug: string | null): string {
    if (!slug) return '?';
    return slug.charAt(0).toUpperCase() + slug.slice(1);
}

// Damage class: small colored dot
const CATEGORY_COLOR: Record<string, string> = {
    physical: '#C03028',
    special: '#6890F0',
    status: '#6888B8',
};

function categoryStyle(cls: string | null): string {
    const color = cls ? (CATEGORY_COLOR[cls] ?? '#888') : '#888';
    return `background:${color}`;
}

function categoryLabel(cls: string | null): string {
    if (cls === 'physical') return 'Phys';
    if (cls === 'special') return 'Spec';
    if (cls === 'status') return 'Status';
    return '—';
}

const triggerClass = cn(
    'border-input flex h-9 w-full items-center justify-between gap-1.5 rounded-md border bg-background px-2 py-1 text-left text-sm shadow-xs',
    'hover:bg-accent/50 focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none',
    'disabled:cursor-not-allowed disabled:opacity-50',
);
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <button
                :id="inputId"
                type="button"
                :disabled="disabled"
                :class="cn(triggerClass, !selectedMove && 'text-muted-foreground')"
                :aria-expanded="open"
            >
                <template v-if="selectedMove">
                    <span class="min-w-0 flex-1 truncate font-medium">{{ selectedMove.label }}</span>
                    <span class="flex shrink-0 items-center gap-1">
                        <span
                            class="inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold leading-none"
                            :style="typeStyle(selectedMove.type_slug)"
                        >
                            {{ typeLabel(selectedMove.type_slug) }}
                        </span>
                        <span
                            class="inline-block size-2 shrink-0 rounded-full"
                            :style="categoryStyle(selectedMove.damage_class)"
                            :title="categoryLabel(selectedMove.damage_class)"
                        />
                        <span class="text-muted-foreground min-w-[40px] text-right text-[11px] tabular-nums">
                            <template v-if="selectedMove.damage_class !== 'status'">
                                {{ selectedMove.power ?? '—' }}
                            </template>
                            <template v-else>—</template>
                        </span>
                    </span>
                </template>
                <template v-else>
                    <span class="flex-1">— No move —</span>
                </template>
                <ChevronsUpDown class="size-3.5 shrink-0 opacity-40" />
            </button>
        </PopoverTrigger>

        <PopoverContent class="w-[400px] max-w-[calc(100vw-2rem)] p-0" align="start">
            <Command class="bg-popover text-popover-foreground">
                <CommandInput class="h-9 border-0" placeholder="Search moves…" />
                <CommandList class="max-h-60 overflow-y-auto">
                    <CommandEmpty>No move found.</CommandEmpty>
                    <CommandItem
                        value="__clear__"
                        class="text-muted-foreground"
                        @select="clear"
                        @mouseover="hoveredSlug = null"
                    >
                        — No move —
                    </CommandItem>
                    <CommandItem
                        v-for="m in visibleOptions"
                        :key="m.slug"
                        :value="m.slug"
                        class="flex cursor-pointer items-center gap-2 px-2 py-1.5"
                        @select="() => select(m.slug)"
                        @mouseover="hoveredSlug = m.slug"
                        @mouseleave="hoveredSlug = null"
                    >
                        <!-- Move name -->
                        <span class="min-w-0 flex-1 truncate text-sm">{{ m.label }}</span>
                        <!-- Type badge -->
                        <span
                            class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold leading-none"
                            :style="typeStyle(m.type_slug)"
                        >
                            {{ typeLabel(m.type_slug) }}
                        </span>
                        <!-- Category dot -->
                        <span
                            class="size-2 shrink-0 rounded-full"
                            :style="categoryStyle(m.damage_class)"
                            :title="categoryLabel(m.damage_class)"
                        />
                        <!-- BP -->
                        <span class="text-muted-foreground w-8 text-right text-[11px] tabular-nums">
                            {{ m.damage_class !== 'status' ? (m.power ?? '—') : '—' }}
                        </span>
                        <!-- ACC -->
                        <span class="text-muted-foreground w-10 text-right text-[11px] tabular-nums">
                            {{ m.accuracy != null ? `${m.accuracy}%` : '—' }}
                        </span>
                    </CommandItem>
                </CommandList>

                <!-- Description footer -->
                <div
                    v-if="hoveredMove?.short_effect"
                    class="border-border/60 text-muted-foreground border-t px-3 py-2 text-xs leading-relaxed"
                >
                    {{ hoveredMove.short_effect }}
                </div>
                <div
                    v-else-if="hoveredMove"
                    class="border-border/60 text-muted-foreground border-t px-3 py-2 text-xs italic"
                >
                    No description available.
                </div>
            </Command>
        </PopoverContent>
    </Popover>
</template>
