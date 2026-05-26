<script setup lang="ts">
import PokepastePastePanel from '@/components/pokepaste/PokepastePastePanel.vue';
import PokepasteSlotCard from '@/components/pokepaste/PokepasteSlotCard.vue';
import type { HeldItemOption, NatureOption, RosterOption } from '@/components/pokepaste/PokepasteSlotCard.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { buildShowdownExport, type PokepasteSlot } from '@/lib/pokepaste/showdownExport';
import { router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const showdownFieldText = ref('');

const props = defineProps<{
    pokepastePublicId: string;
    roster: RosterOption[];
    slots: PokepasteSlot[];
    heldItems: HeldItemOption[];
    allTeraTypes: string[];
    natures: NatureOption[];
    showdownExport: string;
    detailsVisible: boolean;
}>();

const saveBanner = ref(false);
const saving = ref(false);
const activeSlot = ref(0);
const detailsVisible = ref(props.detailsVisible);

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

watch(
    () => props.detailsVisible,
    (next) => {
        detailsVisible.value = next;
    },
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

function syncShowdownFieldText(): void {
    if (form.isDirty) {
        showdownFieldText.value = exportFromSlots.value;
        return;
    }

    const saved = props.showdownExport.trim();
    showdownFieldText.value = saved || exportFromSlots.value;
}

watch(() => form.slots, syncShowdownFieldText, { deep: true });
watch(() => props.showdownExport, syncShowdownFieldText);
watch(() => props.slots, syncShowdownFieldText, { deep: true });
watch(exportFromSlots, syncShowdownFieldText);
watch(() => form.isDirty, syncShowdownFieldText);

syncShowdownFieldText();

// Per-slot helpers
function excludedPokemonIds(slotIndex: number): number[] {
    return form.slots
        .map((s, i) => (i !== slotIndex ? s.league_pokemon_id : null))
        .filter((id): id is number => id != null && id > 0);
}

function excludedItemIds(slotIndex: number): number[] {
    const taken = new Set<number>();
    for (let i = 0; i < form.slots.length; i++) {
        if (i === slotIndex) continue;
        const id = form.slots[i]?.version_group_held_item_id;
        if (id != null && id > 0) taken.add(id);
    }
    return [...taken];
}

function updateSlot(index: number, slot: PokepasteSlot): void {
    form.slots = form.slots.map((s, i) => (i === index ? { ...slot } : s));
}

function onDetailsVisibleChange(checked: boolean | 'indeterminate'): void {
    detailsVisible.value = checked === true;
}

function submit(): void {
    saving.value = true;

    router.put(
        route('pokepaste.update', { pokepaste: props.pokepastePublicId }),
        {
            slots: form.slots,
            details_visible: detailsVisible.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                syncShowdownFieldText();
                saveBanner.value = true;
                setTimeout(() => {
                    saveBanner.value = false;
                }, 4000);
            },
            onError: (errors) => {
                form.clearErrors().setError(errors);
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
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
        form.slots.push({ league_pokemon_id: null, ability: '', moves: ['', '', '', ''], version_group_held_item_id: null, nature: null, tera_type: null, evs: null });
    }
    form.slots = form.slots.slice(0, 6);
}

// Slot completion check
function isSlotComplete(slot: PokepasteSlot): boolean {
    if (!slot.league_pokemon_id || !slot.ability?.trim()) return false;
    const m = slot.moves ?? [];
    return m.length === 4 && m.every((x) => String(x).trim() !== '');
}

function slotLabel(index: number): string {
    const slot = form.slots[index];
    if (!slot?.league_pokemon_id) return `Slot ${index + 1}`;
    const r = props.roster.find((r) => r.league_pokemon_id === slot.league_pokemon_id);
    return r?.name ?? `Slot ${index + 1}`;
}

function slotSprite(index: number): string | null {
    const slot = form.slots[index];
    if (!slot?.league_pokemon_id) return null;
    const r = props.roster.find((r) => r.league_pokemon_id === slot.league_pokemon_id);
    return r?.sprite_url ?? null;
}

function navigate(delta: number): void {
    activeSlot.value = Math.max(0, Math.min(5, activeSlot.value + delta));
}

const completedCount = computed(() => form.slots.filter(isSlotComplete).length);
</script>

<template>
    <div class="space-y-4">
        <!-- Header row -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">Match team paste</h2>
                <p class="text-muted-foreground text-sm">
                    Build your six from your drafted roster. Only you can see and edit this paste.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-muted-foreground text-sm">{{ completedCount }}/6 complete</span>
                <Button type="button" :disabled="saving" @click="submit">
                    {{ saving ? 'Saving…' : 'Save paste' }}
                </Button>
            </div>
        </div>

        <!-- Save banner -->
        <div v-if="saveBanner" class="bg-primary/10 text-primary rounded-md px-4 py-2 text-sm">Match team paste saved.</div>

        <!-- Errors -->
        <div v-if="Object.keys(form.errors).length" class="text-destructive space-y-1 text-sm">
            <p v-for="(msg, key) in form.errors" :key="key">{{ Array.isArray(msg) ? msg.join(' ') : msg }}</p>
        </div>

        <!-- Public visibility -->
        <div
            class="border-border/60 bg-muted/20 flex flex-wrap items-start gap-3 rounded-lg border px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/40"
        >
            <Checkbox
                id="pokepaste-details-visible"
                :checked="detailsVisible"
                :disabled="saving"
                @update:checked="onDetailsVisibleChange"
            />
            <div class="min-w-0 flex-1 space-y-0.5">
                <label for="pokepaste-details-visible" class="cursor-pointer text-sm font-medium">
                    Show full team details on public paste
                </label>
                <p class="text-muted-foreground text-xs">
                    When off, viewers only see which Pokémon you brought and each Tera type—not moves, items, EVs, or
                    abilities.
                </p>
            </div>
        </div>

        <!-- Import from Showdown text -->
        <PokepastePastePanel
            v-model="showdownFieldText"
            :pokepaste-public-id="pokepastePublicId"
            @applied="onPasteApplied"
        />

        <!-- Wizard: team list + focused editor -->
        <div class="border-border overflow-hidden rounded-xl border">
            <div class="grid grid-cols-1 md:grid-cols-[220px_1fr]">
                <!-- Team list sidebar -->
                <nav class="border-border/60 md:border-r">
                    <p class="text-muted-foreground border-border/60 border-b px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                        Team
                    </p>
                    <ul class="py-1">
                        <li v-for="n in 6" :key="n">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition-colors"
                                :class="[
                                    activeSlot === n - 1
                                        ? 'bg-primary/10 text-primary font-medium'
                                        : 'text-foreground hover:bg-muted/50',
                                ]"
                                @click="activeSlot = n - 1"
                            >
                                <!-- Sprite or number badge -->
                                <span class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-lg">
                                    <img
                                        v-if="slotSprite(n - 1)"
                                        :src="slotSprite(n - 1)!"
                                        :alt="slotLabel(n - 1)"
                                        class="max-h-full max-w-full object-contain"
                                        loading="lazy"
                                    />
                                    <span v-else class="text-muted-foreground bg-muted flex size-8 items-center justify-center rounded-lg text-xs font-semibold">
                                        {{ n }}
                                    </span>
                                </span>

                                <span class="min-w-0 flex-1 truncate">{{ slotLabel(n - 1) }}</span>

                                <!-- Completion check -->
                                <CheckCircle2
                                    v-if="isSlotComplete(form.slots[n - 1]!)"
                                    class="text-primary size-3.5 shrink-0 opacity-80"
                                />
                            </button>
                        </li>
                    </ul>
                </nav>

                <!-- Focused slot editor -->
                <div class="bg-card/30 p-5">
                    <!-- Slot nav header -->
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-sm font-semibold">
                            Slot {{ activeSlot + 1 }}
                            <span v-if="form.slots[activeSlot]?.league_pokemon_id" class="text-muted-foreground font-normal">
                                · {{ slotLabel(activeSlot) }}
                            </span>
                        </p>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                :disabled="activeSlot === 0"
                                class="hover:bg-muted rounded p-1 disabled:opacity-30"
                                title="Previous"
                                @click="navigate(-1)"
                            >
                                <ChevronLeft class="size-4" />
                            </button>
                            <span class="text-muted-foreground text-xs tabular-nums">{{ activeSlot + 1 }} / 6</span>
                            <button
                                type="button"
                                :disabled="activeSlot === 5"
                                class="hover:bg-muted rounded p-1 disabled:opacity-30"
                                title="Next"
                                @click="navigate(1)"
                            >
                                <ChevronRight class="size-4" />
                            </button>
                        </div>
                    </div>

                    <PokepasteSlotCard
                        :index="activeSlot"
                        :roster="roster"
                        :held-items="heldItems"
                        :all-tera-types="allTeraTypes"
                        :natures="natures"
                        :excluded-pokemon-ids="excludedPokemonIds(activeSlot)"
                        :excluded-item-ids="excludedItemIds(activeSlot)"
                        :model-value="form.slots[activeSlot]!"
                        @update:model-value="updateSlot(activeSlot, $event)"
                    />

                    <!-- Next slot button -->
                    <div v-if="activeSlot < 5" class="mt-5 flex justify-end">
                        <Button type="button" variant="secondary" size="sm" @click="navigate(1)">
                            Next slot <ChevronRight class="ml-1 size-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
