<script setup lang="ts">
import PokepastePastePanel from '@/components/pokepaste/PokepastePastePanel.vue';
import PokepasteSlotCard from '@/components/pokepaste/PokepasteSlotCard.vue';
import type { HeldItemOption, NatureOption, RosterOption } from '@/components/pokepaste/PokepasteSlotCard.vue';
import { Button } from '@/components/ui/button';
import { buildShowdownExport, type PokepasteSlot } from '@/lib/pokepaste/showdownExport';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const showdownFieldText = ref('');

const props = defineProps<{
    pokepastePublicId: string;
    roster: RosterOption[];
    slots: PokepasteSlot[];
    heldItems: HeldItemOption[];
    allTeraTypes: string[];
    natures: NatureOption[];
}>();

const saveBanner = ref(false);

const form = useForm({
    slots: [...props.slots] as PokepasteSlot[],
});

watch(
    () => props.slots,
    (next) => {
        form.slots = [...next] as PokepasteSlot[];
    },
    { deep: true },
);

const rosterMap = computed(() => {
    const m: Record<number, { pokedex_name: string }> = {};
    for (const r of props.roster) {
        m[r.league_pokemon_id] = { pokedex_name: r.pokedex_name };
    }
    return m;
});

const heldItemLabelById = computed(() => {
    const m: Record<number, string> = {};
    for (const h of props.heldItems) {
        m[h.id] = h.label;
    }
    return m;
});

const natureLabelByValue = computed(() => {
    const m: Record<number, string> = {};
    for (const n of props.natures) {
        m[n.value] = n.export_label ?? n.label;
    }
    return m;
});

const exportFromSlots = computed(() =>
    buildShowdownExport(form.slots, rosterMap.value, heldItemLabelById.value, natureLabelByValue.value),
);

watch(
    exportFromSlots,
    (t) => {
        showdownFieldText.value = t;
    },
    { immediate: true },
);

function excludedPokemonIds(slotIndex: number): number[] {
    return form.slots
        .map((s, i) => (i !== slotIndex ? s.league_pokemon_id : null))
        .filter((id): id is number => id != null && id > 0);
}

function excludedItemIds(slotIndex: number): number[] {
    const taken = new Set<number>();
    for (let i = 0; i < form.slots.length; i++) {
        if (i === slotIndex) {
            continue;
        }
        const id = form.slots[i]?.version_group_held_item_id;
        if (id != null && id > 0) {
            taken.add(id);
        }
    }

    return [...taken];
}

function updateSlot(index: number, slot: PokepasteSlot): void {
    form.slots[index] = { ...slot };
}

function submit(): void {
    form.put(route('pokepaste.update', { pokepaste: props.pokepastePublicId }), {
        preserveScroll: true,
        onSuccess: () => {
            saveBanner.value = true;
            setTimeout(() => {
                saveBanner.value = false;
            }, 4000);
        },
    });
}

function onPasteApplied(slots: PokepasteSlot[]): void {
    form.slots = slots.map((s) => ({
        league_pokemon_id: s.league_pokemon_id ?? null,
        ability: s.ability ?? '',
        moves: Array.isArray(s.moves) ? [...s.moves].slice(0, 4) : ['', '', '', ''],
        version_group_held_item_id: s.version_group_held_item_id ?? null,
        nature: s.nature ?? null,
        tera_type: s.tera_type ?? null,
        evs: s.evs && typeof s.evs === 'object' ? { ...s.evs } : null,
    }));
    while (form.slots.length < 6) {
        form.slots.push({
            league_pokemon_id: null,
            ability: '',
            moves: ['', '', '', ''],
            version_group_held_item_id: null,
            nature: null,
            tera_type: null,
            evs: null,
        });
    }
    form.slots = form.slots.slice(0, 6);
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold">Match team paste (Showdown)</h2>
            <p class="text-muted-foreground mt-1 text-sm">
                Build your six for this match from your drafted roster. Only you can edit this paste. Parsed Pokémon must be on
                your team in this league.
            </p>
        </div>

        <div v-if="saveBanner" class="bg-primary/10 text-primary rounded-md px-4 py-2 text-sm">Match team paste saved.</div>

        <div v-if="Object.keys(form.errors).length" class="text-destructive space-y-1 text-sm">
            <p v-for="(msg, key) in form.errors" :key="key">
                {{ Array.isArray(msg) ? msg.join(' ') : msg }}
            </p>
        </div>

        <PokepastePastePanel
            v-model="showdownFieldText"
            :pokepaste-public-id="pokepastePublicId"
            @applied="onPasteApplied"
        />

        <div class="space-y-4">
            <h3 class="text-base font-medium">Slots</h3>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <PokepasteSlotCard
                    v-for="n in 6"
                    :key="n"
                    :index="n - 1"
                    :roster="roster"
                    :held-items="heldItems"
                    :all-tera-types="allTeraTypes"
                    :natures="natures"
                    :excluded-pokemon-ids="excludedPokemonIds(n - 1)"
                    :excluded-item-ids="excludedItemIds(n - 1)"
                    :model-value="form.slots[n - 1]!"
                    @update:model-value="updateSlot(n - 1, $event)"
                />
            </div>
            <Button type="button" :disabled="form.processing" @click="submit">Save match paste</Button>
        </div>
    </div>
</template>
