<script setup lang="ts">
export interface PokepasteViewCard {
    filled: boolean;
    species_label: string | null;
    nickname_label: string | null;
    sprite_url: string | null;
    item_label: string | null;
    item_sprite_url: string | null;
    ability: string | null;
    tera_type: string | null;
    nature_label: string | null;
    evs_line: string | null;
    moves: string[];
}

defineProps<{
    cards: PokepasteViewCard[];
}>();

function titleLine(card: PokepasteViewCard): string {
    const species = card.species_label ?? 'Pokémon';
    const nick = card.nickname_label;
    const item = card.item_label;
    const core = nick ? `${nick} (${species})` : species;
    return item ? `${core} @ ${item}` : core;
}
</script>

<template>
    <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
        <article
            v-for="(card, idx) in cards"
            :key="idx"
            class="border-border/60 bg-card/40 overflow-hidden rounded-xl border shadow-sm backdrop-blur-sm dark:border-zinc-700/80 dark:bg-zinc-900/60"
        >
            <template v-if="card.filled">
                <div class="flex gap-4 p-4">
                    <div
                        class="bg-muted/30 dark:bg-zinc-800/80 flex size-24 shrink-0 items-center justify-center rounded-lg sm:size-28"
                    >
                        <img
                            v-if="card.sprite_url"
                            :src="card.sprite_url"
                            :alt="card.species_label ?? ''"
                            class="max-h-full max-w-full object-contain"
                            loading="lazy"
                        />
                        <span v-else class="text-muted-foreground text-xs">No sprite</span>
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex items-start gap-2">
                            <h2
                                class="text-foreground text-base leading-snug font-semibold tracking-tight dark:text-zinc-50"
                            >
                                {{ titleLine(card) }}
                            </h2>
                            <img
                                v-if="card.item_sprite_url"
                                :src="card.item_sprite_url"
                                alt=""
                                class="mt-0.5 size-6 shrink-0 object-contain opacity-90"
                                loading="lazy"
                            />
                        </div>
                        <dl class="text-muted-foreground space-y-1 text-sm dark:text-zinc-400">
                            <div v-if="card.ability" class="flex gap-2">
                                <dt class="shrink-0 font-medium text-zinc-500 dark:text-zinc-500">Ability</dt>
                                <dd class="text-foreground min-w-0 dark:text-zinc-200">{{ card.ability }}</dd>
                            </div>
                            <div v-if="card.tera_type" class="flex gap-2">
                                <dt class="shrink-0 font-medium text-zinc-500 dark:text-zinc-500">Tera</dt>
                                <dd class="text-foreground capitalize dark:text-zinc-200">{{ card.tera_type }}</dd>
                            </div>
                            <div v-if="card.evs_line" class="font-mono text-xs">
                                {{ card.evs_line }}
                            </div>
                            <div v-if="card.nature_label" class="flex gap-2">
                                <dt class="shrink-0 font-medium text-zinc-500 dark:text-zinc-500">Nature</dt>
                                <dd class="text-foreground dark:text-zinc-200">{{ card.nature_label }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
                <ul
                    class="border-border/50 text-foreground divide-border/50 border-t px-4 py-3 text-sm dark:border-zinc-800 dark:text-zinc-200"
                >
                    <li v-for="(move, mi) in card.moves" :key="mi" class="flex gap-2 py-0.5">
                        <span class="text-emerald-600/90 dark:text-emerald-400/90 font-medium">-</span>
                        <span>{{ move || '—' }}</span>
                    </li>
                </ul>
            </template>
            <div
                v-else
                class="text-muted-foreground flex min-h-[8rem] items-center justify-center p-6 text-sm italic dark:text-zinc-500"
            >
                Empty slot
            </div>
        </article>
    </div>
</template>
