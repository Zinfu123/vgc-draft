<script setup lang="ts">
import PokepasteItemCombobox from '@/components/pokepaste/PokepasteItemCombobox.vue';
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
    abilities: string[];
    moves: { slug: string; label: string }[];
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
    props.roster.filter((r) => !props.excludedPokemonIds.includes(r.league_pokemon_id) || r.league_pokemon_id === props.modelValue.league_pokemon_id),
);

function rosterOptionLabel(r: RosterOption): string {
    const draft = r.name.trim();
    const species = r.pokedex_name.trim();
    if (draft.localeCompare(species, undefined, { sensitivity: 'accent' }) === 0) {
        return draft;
    }

    return `${draft} (${species})`;
}

const selected = computed(() =>
    props.roster.find((r) => r.league_pokemon_id === props.modelValue.league_pokemon_id),
);

function patch(partial: Partial<PokepasteSlot>): void {
    emit('update:modelValue', { ...props.modelValue, ...partial });
}

function onRosterChange(e: Event): void {
    const v = (e.target as HTMLSelectElement).value;
    if (!v) {
        patch({
            league_pokemon_id: null,
            ability: '',
            moves: ['', '', '', ''],
            tera_type: null,
            evs: null,
        });
        return;
    }
    patch({
        league_pokemon_id: Number(v),
        ability: '',
        moves: ['', '', '', ''],
        tera_type: null,
        evs: null,
    });
}

function setMove(i: number, slug: string): void {
    const next = [...(props.modelValue.moves ?? ['', '', '', ''])];
    next[i] = slug;
    patch({ moves: next });
}

const natureOptions = computed(() =>
    props.natures.map((n) => ({
        value: n.value,
        label: n.label,
    })),
);

const teraTypeOptions = computed(() => props.allTeraTypes.map((t) => ({ value: t, label: t })));

const moveOptionsPerMoveSlot = computed((): { value: string; label: string }[][] => {
    const s = selected.value;
    if (!s) {
        return [[], [], [], []];
    }

    const moves = props.modelValue.moves ?? ['', '', '', ''];

    return [0, 1, 2, 3].map((moveIndex) => {
        const currentSlug = moves[moveIndex] ?? '';
        const usedElsewhere = new Set<string>();
        for (let i = 0; i < 4; i++) {
            if (i === moveIndex) {
                continue;
            }
            const slug = moves[i];
            if (slug != null && slug !== '') {
                usedElsewhere.add(slug);
            }
        }

        return s.moves
            .filter((m) => !usedElsewhere.has(m.slug) || m.slug === currentSlug)
            .map((m) => ({ value: m.slug, label: m.label }));
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
    <div
        class="border-border bg-card space-y-4 rounded-lg border p-4 shadow-xs dark:bg-card/40"
        :data-slot-index="index"
    >
        <h3 class="text-sm font-semibold">Slot {{ index + 1 }}</h3>

        <div class="space-y-2">
            <Label :for="`poke-${index}-lp`">Pokémon</Label>
            <select
                :id="`poke-${index}-lp`"
                :class="selectClass"
                :value="modelValue.league_pokemon_id ?? ''"
                @change="onRosterChange"
            >
                <option value="">— Choose —</option>
                <option v-for="r in rosterChoices" :key="r.league_pokemon_id" :value="r.league_pokemon_id">
                    {{ rosterOptionLabel(r) }}
                </option>
            </select>
            <p v-if="selected?.game_data_missing" class="text-destructive text-xs">
                Game data missing for this species — import version group data or pick another Pokémon.
            </p>
        </div>

        <template v-if="selected && !selected.game_data_missing">
            <div class="space-y-2">
                <Label :for="`poke-${index}-ab`">Ability</Label>
                <select
                    :id="`poke-${index}-ab`"
                    :class="selectClass"
                    :value="modelValue.ability"
                    @change="patch({ ability: ($event.target as HTMLSelectElement).value })"
                >
                    <option value="">— Choose —</option>
                    <option v-for="a in selected.abilities" :key="a" :value="a">{{ a }}</option>
                </select>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div v-for="mi in 4" :key="mi" class="space-y-2">
                    <Label :for="`poke-${index}-mv-${mi}`">Move {{ mi }}</Label>
                    <PokepasteSearchableSelect
                        :input-id="`poke-${index}-mv-${mi}`"
                        :model-value="modelValue.moves[mi - 1] ?? ''"
                        :options="moveOptionsPerMoveSlot[mi - 1] ?? []"
                        placeholder="— Choose move —"
                        search-placeholder="Search moves…"
                        empty-message="No move found."
                        none-label="— No move —"
                        treat-empty-string-as-empty
                        :clear-value="''"
                        :search-hint="(o) => String(o.value)"
                        @update:model-value="setMove(mi - 1, $event === null || $event === undefined ? '' : String($event))"
                    />
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <Label class="text-foreground">Effort values (EVs)</Label>
                    <span
                        class="text-xs tabular-nums"
                        :class="evTotal > 510 ? 'text-destructive font-medium' : 'text-muted-foreground'"
                    >
                        Total {{ evTotal }} / 510
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div v-for="ev in evStatMeta" :key="ev.key" class="space-y-1">
                        <Label :for="`poke-${index}-ev-${ev.key}`" class="text-muted-foreground text-xs font-normal">{{
                            ev.label
                        }}</Label>
                        <Input
                            :id="`poke-${index}-ev-${ev.key}`"
                            type="number"
                            min="0"
                            max="252"
                            :class="inputClass"
                            :model-value="String(evsDisplay[ev.key])"
                            @update:model-value="updateEv(ev.key, String($event))"
                        />
                    </div>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label :for="`poke-${index}-item`">Item</Label>
                    <PokepasteItemCombobox
                        :input-id="`poke-${index}-item`"
                        :model-value="modelValue.version_group_held_item_id"
                        :items="heldItems"
                        :excluded-ids="excludedItemIds"
                        @update:model-value="patch({ version_group_held_item_id: $event })"
                    />
                </div>
                <div class="space-y-2">
                    <Label :for="`poke-${index}-nat`">Nature</Label>
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

            <div v-if="selected.tera_capable && allTeraTypes.length" class="space-y-2">
                <Label :for="`poke-${index}-tera`">Tera Type</Label>
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
        </template>
    </div>
</template>
