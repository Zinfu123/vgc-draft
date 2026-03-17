<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Search, ShieldBan, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Pokemon {
    id: number;
    name: string;
    cost: number;
    banned: number | boolean;
    is_drafted: number | boolean;
}

export interface PokemonFilters {
    name: string;
    minCost: number | undefined;
    maxCost: number | undefined;
}

const props = defineProps<{
    pokemon: Pokemon[];
    modelValue: PokemonFilters;
    isBanPhase?: boolean;
    minCostToBan?: number;
}>();

const emit = defineEmits<{
    'update:modelValue': [PokemonFilters];
}>();

const nameInputRef = ref<HTMLInputElement | null>(null);
const showSuggestions = ref(false);

// Local string refs for cost inputs so v-model works naturally with keyboard input.
// Number inputs bound via :value/:input can swallow keystrokes during re-renders.
const localMinCost = ref<string>(props.modelValue.minCost !== undefined ? String(props.modelValue.minCost) : '');
const localMaxCost = ref<string>(props.modelValue.maxCost !== undefined ? String(props.modelValue.maxCost) : '');

watch(localMinCost, (val) => {
    emit('update:modelValue', { ...props.modelValue, minCost: val !== '' ? Number(val) : undefined });
});

watch(localMaxCost, (val) => {
    emit('update:modelValue', { ...props.modelValue, maxCost: val !== '' ? Number(val) : undefined });
});

const nameProp = computed({
    get: () => props.modelValue.name,
    set: (val: string) => emit('update:modelValue', { ...props.modelValue, name: val }),
});

const suggestions = computed(() => {
    if (!nameProp.value) return [];
    const search = nameProp.value.toLowerCase();
    return props.pokemon
        .filter((p) => !p.banned && !p.is_drafted && p.name.toLowerCase().includes(search))
        .sort((a, b) => {
            const aName = a.name.toLowerCase();
            const bName = b.name.toLowerCase();
            const aStarts = aName.startsWith(search);
            const bStarts = bName.startsWith(search);
            if (aStarts !== bStarts) return aStarts ? -1 : 1;
            return aName.localeCompare(bName);
        })
        .slice(0, 10);
});

const onNameInput = (e: Event) => {
    const value = (e.target as HTMLInputElement).value;
    nameProp.value = value;
    showSuggestions.value = value.length > 0;
};

const selectSuggestion = (name: string) => {
    nameProp.value = name;
    showSuggestions.value = false;
    nameInputRef.value?.blur();
};

const clearName = () => {
    nameProp.value = '';
    showSuggestions.value = false;
    nameInputRef.value?.focus();
};

const hideSuggestions = () => {
    setTimeout(() => {
        showSuggestions.value = false;
    }, 150);
};
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-800/50">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <!-- Name search with autocomplete -->
            <div class="relative min-w-0 flex-[2]">
                <Label for="pokemon-name-filter" class="mb-1 block">Search Pokémon</Label>
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <input
                        id="pokemon-name-filter"
                        ref="nameInputRef"
                        type="text"
                        :value="nameProp"
                        @input="onNameInput"
                        @focus="showSuggestions = nameProp.length > 0 && suggestions.length > 0"
                        @blur="hideSuggestions"
                        placeholder="Search by name…"
                        autocomplete="off"
                        spellcheck="false"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent py-1 pl-9 pr-8 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                    <button
                        v-if="nameProp"
                        type="button"
                        @click="clearName"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-gray-400 transition-colors hover:text-gray-700 dark:hover:text-gray-200"
                        aria-label="Clear search"
                    >
                        <X class="size-4" />
                    </button>

                    <!-- Suggestions dropdown -->
                    <ul
                        v-if="showSuggestions && suggestions.length > 0"
                        class="absolute left-0 right-0 top-full z-50 mt-1 max-h-60 overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-white/10 dark:bg-gray-800"
                    >
                        <li
                            v-for="p in suggestions"
                            :key="p.id"
                            @mousedown.prevent="selectSuggestion(p.name)"
                            class="flex cursor-pointer items-center justify-between px-3 py-2 text-sm capitalize hover:bg-gray-100 dark:hover:bg-gray-700"
                        >
                            <span>{{ p.name }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ p.cost }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Min cost -->
            <div class="flex-1">
                <Label for="filter-min-cost" class="mb-1 block">Min Cost</Label>
                <Input id="filter-min-cost" v-model="localMinCost" type="number" placeholder="Min" min="0" />
            </div>

            <!-- Max cost -->
            <div class="flex-1">
                <Label for="filter-max-cost" class="mb-1 block">Max Cost</Label>
                <Input id="filter-max-cost" v-model="localMaxCost" type="number" placeholder="Max" min="0" />
            </div>
        </div>

        <!-- Legend -->
        <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500 dark:text-gray-400">
            <span class="flex items-center gap-1.5">
                <span class="inline-block size-3 rounded-full bg-green-500"></span>Available
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block size-3 rounded-full bg-red-500"></span>Banned
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block size-3 rounded-full bg-gray-400"></span>Drafted
            </span>
            <span v-if="isBanPhase" class="flex items-center gap-1.5 text-orange-600 dark:text-orange-400">
                <ShieldBan class="size-3" />Min ban cost: {{ minCostToBan }}
            </span>
        </div>
    </div>
</template>
