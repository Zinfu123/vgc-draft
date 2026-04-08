<script setup lang="ts">
import PokepasteItemCombobox from '@/components/pokepaste/PokepasteItemCombobox.vue';
import { type MoveOption } from '@/components/pokepaste/PokepasteMovePicker.vue';
import PokepasteMovePicker from '@/components/pokepaste/PokepasteMovePicker.vue';
import PokepasteSearchableSelect from '@/components/pokepaste/PokepasteSearchableSelect.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PokepasteSlot } from '@/lib/pokepaste/showdownExport';
import { cn } from '@/lib/utils';
import { computed } from 'vue';

type EvStat = 'hp' | 'atk' | 'def' | 'spa' | 'spd' | 'spe';

const evStatMeta: { key: EvStat; label: string }[] = [
    { key: 'hp', label: 'HP' },
    { key: 'atk', label: 'Atk' },
    { key: 'def', label: 'Def' },
    { key: 'spa', label: 'SpA' },
    { key: 'spd', label: 'SpD' },
    { key: 'spe', label: 'Spe' },
];

export interface RosterOption {
    league_pokemon_id: number;
    name: string;
    pokedex_name: string;
    sprite_url: string | null;
    abilities: string[];
    moves: MoveOption[];
    tera_capable: boolean;
    game_data_missing: boolean;
}

export interface HeldItemOption {
    id: number;
    label: string;
}

export interface NatureOption {
    value: number;
    /** Dropdown label, e.g. "Jolly (+Spe, -SpA)" */
    label: string;
    /** Plain name for Showdown export lines */
    export_label?: string;
}

const props = defineProps<{
    index: number;
    modelValue: PokepasteSlot;
    roster: RosterOption[];
    heldItems: HeldItemOption[];
    allTeraTypes: string[];
    natures: NatureOption[];
    excludedPokemonIds: number[];
    excludedItemIds: number[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: PokepasteSlot];
}>();

const selectClass = cn(
    'border-input flex h-9 w-full rounded-md border bg-background px-3 py-1 text-sm text-foreground shadow-xs',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none',
);

const rosterChoices = computed(() =>
    props.roster.filter(
        (r) => !props.excludedPokemonIds.includes(r.league_pokemon_id) || r.league_pokemon_id === props.modelValue.league_pokemon_id,
    ),
);

function rosterOptionLabel(r: RosterOption): string {
    const draft = r.name.trim();
    const species = r.pokedex_name.trim();
    if (draft.localeCompare(species, undefined, { sensitivity: 'accent' }) === 0) {
        return draft;
    }
    return `${draft} (${species})`;
}

const selected = computed(() => props.roster.find((r) => r.league_pokemon_id === props.modelValue.league_pokemon_id));

function patch(partial: Partial<PokepasteSlot>): void {
    emit('update:modelValue', { ...props.modelValue, ...partial });
}

function onRosterChange(e: Event): void {
    const v = (e.target as HTMLSelectElement).value;
    if (!v) {
        patch({ league_pokemon_id: null, ability: '', moves: ['', '', '', ''], tera_type: null, evs: null });
        return;
    }
    patch({ league_pokemon_id: Number(v), ability: '', moves: ['', '', '', ''], tera_type: null, evs: null });
}

function setMove(i: number, slug: string): void {
    const next = [...(props.modelValue.moves ?? ['', '', '', ''])];
    next[i] = slug;
    patch({ moves: next });
}

const natureOptions = computed(() => props.natures.map((n) => ({ value: n.value, label: n.label })));
const teraTypeOptions = computed(() => props.allTeraTypes.map((t) => ({ value: t, label: t })));

// Per-move-slot options: excludes moves picked in other slots
const moveOptionsPerSlot = computed((): MoveOption[][] => {
    const s = selected.value;
    if (!s) return [[], [], [], []];
    const moves = props.modelValue.moves ?? ['', '', '', ''];
    return [0, 1, 2, 3].map((mi) => {
        const usedElsewhere = new Set<string>();
        for (let i = 0; i < 4; i++) {
            if (i === mi) continue;
            const slug = moves[i];
            if (slug != null && slug !== '') usedElsewhere.add(slug);
        }
        return s.moves.filter((m) => !usedElsewhere.has(m.slug) || m.slug === (moves[mi] ?? ''));
    });
});

const evsDisplay = computed<Record<EvStat, number>>(() => ({
    hp: props.modelValue.evs?.hp ?? 0,
    atk: props.modelValue.evs?.atk ?? 0,
    def: props.modelValue.evs?.def ?? 0,
    spa: props.modelValue.evs?.spa ?? 0,
    spd: props.modelValue.evs?.spd ?? 0,
    spe: props.modelValue.evs?.spe ?? 0,
}));

const evTotal = computed(() =>
    (['hp', 'atk', 'def', 'spa', 'spd', 'spe'] as const).reduce((acc, k) => acc + evsDisplay.value[k], 0),
);

function updateEv(stat: EvStat, raw: string): void {
    const n = Math.max(0, Math.min(252, Number.parseInt(raw, 10) || 0));
    const next: Record<EvStat, number> = { ...evsDisplay.value, [stat]: n };
    const sum = (['hp', 'atk', 'def', 'spa', 'spd', 'spe'] as const).reduce((acc, k) => acc + next[k], 0);
    patch({ evs: sum === 0 ? null : next });
}

const inputClass = cn(
    'bg-background text-foreground border-input h-9 min-w-0 shadow-xs',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
);
</script>

<template>
    <div class="space-y-5">
        <!-- Pokemon selector + sprite header -->
        <div class="flex items-start gap-4">
            <div
                class="bg-muted/40 dark:bg-zinc-800/60 flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-transparent"
            >
                <img
                    v-if="selected?.sprite_url"
                    :src="selected.sprite_url"
                    :alt="selected.pokedex_name"
                    class="max-h-full max-w-full object-contain"
                    loading="lazy"
                />
                <span v-else class="text-muted-foreground text-xs">?</span>
            </div>

            <div class="min-w-0 flex-1 space-y-1.5">
                <Label :for="`poke-${index}-lp`" class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                    Pokémon
                </Label>
                <select
                    :id="`poke-${index}-lp`"
                    :class="selectClass"
                    :value="modelValue.league_pokemon_id ?? ''"
                    @change="onRosterChange"
                >
                    <option value="">— Choose from roster —</option>
                    <option v-for="r in rosterChoices" :key="r.league_pokemon_id" :value="r.league_pokemon_id">
                        {{ rosterOptionLabel(r) }}
                    </option>
                </select>
                <p v-if="selected?.game_data_missing" class="text-destructive text-xs">
                    Game data missing for this species.
                </p>
            </div>
        </div>

        <template v-if="selected && !selected.game_data_missing">
            <!-- Ability -->
            <div class="space-y-1.5">
                <Label :for="`poke-${index}-ab`" class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                    Ability
                </Label>
                <select
                    :id="`poke-${index}-ab`"
                    :class="selectClass"
                    :value="modelValue.ability"
                    @change="patch({ ability: ($event.target as HTMLSelectElement).value })"
                >
                    <option value="">— Choose ability —</option>
                    <option v-for="a in selected.abilities" :key="a" :value="a">{{ a }}</option>
                </select>
            </div>

            <!-- Moves -->
            <div class="space-y-2">
                <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Moves</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div v-for="mi in 4" :key="mi" class="space-y-1">
                        <Label :for="`poke-${index}-mv-${mi}`" class="text-muted-foreground text-xs">Move {{ mi }}</Label>
                        <PokepasteMovePicker
                            :input-id="`poke-${index}-mv-${mi}`"
                            :model-value="modelValue.moves[mi - 1] ?? ''"
                            :options="moveOptionsPerSlot[mi - 1] ?? []"
                            :excluded-slugs="
                                (modelValue.moves ?? [])
                                    .filter((_, i) => i !== mi - 1)
                                    .filter((s) => s !== '' && s != null)
                            "
                            @update:model-value="setMove(mi - 1, $event)"
                        />
                    </div>
                </div>
            </div>

            <!-- Item + Nature -->
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <Label :for="`poke-${index}-item`" class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                        Held Item
                    </Label>
                    <PokepasteItemCombobox
                        :input-id="`poke-${index}-item`"
                        :model-value="modelValue.version_group_held_item_id"
                        :items="heldItems"
                        :excluded-ids="excludedItemIds"
                        @update:model-value="patch({ version_group_held_item_id: $event })"
                    />
                </div>
                <div class="space-y-1.5">
                    <Label :for="`poke-${index}-nat`" class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                        Nature
                    </Label>
                    <PokepasteSearchableSelect
                        :input-id="`poke-${index}-nat`"
                        :model-value="modelValue.nature ?? null"
                        :options="natureOptions"
                        placeholder="— None —"
                        search-placeholder="Search nature…"
                        empty-message="No nature found."
                        none-label="— None —"
                        @update:model-value="patch({ nature: $event === null ? null : Number($event) })"
                    />
                </div>
            </div>

            <!-- Tera Type -->
            <div v-if="selected.tera_capable && allTeraTypes.length" class="space-y-1.5">
                <Label :for="`poke-${index}-tera`" class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                    Tera Type
                </Label>
                <PokepasteSearchableSelect
                    :input-id="`poke-${index}-tera`"
                    :model-value="modelValue.tera_type ?? null"
                    :options="teraTypeOptions"
                    placeholder="— None —"
                    search-placeholder="Search Tera type…"
                    empty-message="No type found."
                    none-label="— None —"
                    @update:model-value="patch({ tera_type: $event === null || $event === '' ? null : String($event) })"
                />
            </div>

            <!-- EVs -->
            <div class="space-y-2">
                <div class="flex flex-wrap items-end justify-between gap-1">
                    <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Effort Values (EVs)</p>
                    <span
                        class="text-xs tabular-nums"
                        :class="evTotal > 510 ? 'text-destructive font-medium' : 'text-muted-foreground'"
                    >
                        {{ evTotal }} / 510
                    </span>
                </div>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                    <div v-for="ev in evStatMeta" :key="ev.key" class="space-y-1">
                        <Label
                            :for="`poke-${index}-ev-${ev.key}`"
                            class="text-muted-foreground block text-center text-xs font-normal"
                        >
                            {{ ev.label }}
                        </Label>
                        <Input
                            :id="`poke-${index}-ev-${ev.key}`"
                            type="number"
                            min="0"
                            max="252"
                            :class="cn(inputClass, 'text-center')"
                            :model-value="String(evsDisplay[ev.key])"
                            @update:model-value="updateEv(ev.key, String($event))"
                        />
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
