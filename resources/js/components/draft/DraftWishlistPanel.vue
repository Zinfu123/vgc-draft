<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { GripVertical, Heart, LoaderCircle, X } from 'lucide-vue-next';
import { ref } from 'vue';

export interface WishlistRowPokemon {
    id: number;
    name: string;
    cost: number;
    sprite_url: string;
    banned: number | boolean;
    is_drafted: number | boolean;
    drafted_by_team_name: string | null;
    wishlistStolen?: boolean;
}

const props = defineProps<{
    rows: WishlistRowPokemon[];
    removingLeaguePokemonId: number | null;
    wishlistReorderBusy: boolean;
}>();

const emit = defineEmits<{
    remove: [pokemon: WishlistRowPokemon];
    select: [pokemon: WishlistRowPokemon];
    reorder: [leaguePokemonIds: number[]];
}>();

const dragFromId = ref<number | null>(null);

const onRowActivate = (row: WishlistRowPokemon) => {
    if (props.removingLeaguePokemonId !== null || props.wishlistReorderBusy) {
        return;
    }
    emit('select', row);
};

const spriteSrc = (name: string) =>
    `https://raw.githubusercontent.com/Autumnchi/coloured-home-sprites/main/${name}.png`;

const onHandleDragStart = (event: DragEvent, row: WishlistRowPokemon) => {
    if (props.wishlistReorderBusy || props.removingLeaguePokemonId !== null || props.rows.length < 2) {
        event.preventDefault();
        return;
    }
    dragFromId.value = row.id;
    event.dataTransfer?.setData('text/plain', String(row.id));
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
};

const onRowDragOver = (event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
};

const onRowDrop = (event: DragEvent, targetRow: WishlistRowPokemon) => {
    event.preventDefault();
    const fromId = dragFromId.value;
    dragFromId.value = null;
    if (props.wishlistReorderBusy || props.removingLeaguePokemonId !== null || fromId === null) {
        return;
    }
    if (fromId === targetRow.id) {
        return;
    }
    const ids = props.rows.map((r) => r.id);
    const fromIdx = ids.indexOf(fromId);
    const toIdx = ids.indexOf(targetRow.id);
    if (fromIdx === -1 || toIdx === -1) {
        return;
    }
    const next = [...ids];
    next.splice(fromIdx, 1);
    next.splice(toIdx, 0, fromId);
    if (next.every((id, i) => id === props.rows[i]?.id)) {
        return;
    }
    emit('reorder', next);
};

const onHandleDragEnd = () => {
    dragFromId.value = null;
};
</script>

<template>
    <div
        class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800/50"
    >
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10">
            <h2 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                <Heart class="size-3.5 text-red-500" />
                Wishlist
            </h2>
            <p v-if="rows.length > 1" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Drag the grip to set priority (top = highest).
            </p>
        </div>
        <div class="max-h-[min(70vh,32rem)] overflow-y-auto">
            <div v-if="rows.length === 0" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                No Pokémon on your wishlist yet. Tap the board to add some.
            </div>
            <ul v-else class="flex flex-col divide-y divide-gray-100 dark:divide-white/10">
                <li
                    v-for="row in rows"
                    :key="row.id"
                    role="button"
                    tabindex="0"
                    class="flex items-center gap-1 px-2 py-2.5 outline-none transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring sm:gap-2 sm:px-3"
                    :class="{
                        'opacity-70': row.banned || row.is_drafted,
                        'opacity-50': dragFromId === row.id,
                    }"
                    @click="onRowActivate(row)"
                    @keydown.enter.prevent="onRowActivate(row)"
                    @keydown.space.prevent="onRowActivate(row)"
                    @dragover="onRowDragOver"
                    @drop="onRowDrop($event, row)"
                >
                    <div
                        class="flex shrink-0 cursor-grab touch-none items-center rounded-md p-0.5 text-gray-400 active:cursor-grabbing dark:text-gray-500"
                        :class="{
                            'pointer-events-none opacity-40': wishlistReorderBusy || removingLeaguePokemonId !== null || rows.length < 2,
                        }"
                        draggable="true"
                        title="Drag to reorder"
                        @click.stop
                        @dragstart="onHandleDragStart($event, row)"
                        @dragend="onHandleDragEnd"
                    >
                        <GripVertical class="size-5" aria-hidden="true" />
                    </div>
                    <img
                        :src="spriteSrc(row.name)"
                        :alt="row.name"
                        class="size-10 shrink-0 rounded-md bg-gray-100 object-contain dark:bg-gray-700"
                        loading="lazy"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium capitalize text-gray-900 dark:text-white">{{ row.name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ row.cost }} pts</p>
                        <p v-if="row.banned" class="text-xs font-medium text-red-600 dark:text-red-400">Banned</p>
                        <p v-else-if="row.is_drafted && row.wishlistStolen" class="truncate text-xs font-medium text-red-500 dark:text-red-400">
                            Taken — {{ row.drafted_by_team_name }}
                        </p>
                        <p v-else-if="row.is_drafted" class="truncate text-xs font-medium text-green-600 dark:text-green-400">
                            You drafted this
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="shrink-0 text-gray-500 hover:text-destructive dark:text-gray-400"
                        :disabled="removingLeaguePokemonId !== null"
                        title="Remove from wishlist"
                        @click.stop="emit('remove', row)"
                    >
                        <LoaderCircle v-if="removingLeaguePokemonId === row.id" class="size-4 animate-spin" />
                        <X v-else class="size-4" />
                    </Button>
                </li>
            </ul>
        </div>
    </div>
</template>
