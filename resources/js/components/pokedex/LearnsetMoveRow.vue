<script setup lang="ts">
import { CircleSlash, Sparkles, Swords } from 'lucide-vue-next';
import { computed } from 'vue';

interface LearnsetMoveRow {
    move_id: number;
    move_name: string;
    method: string;
    level: number;
    type_slug?: string | null;
    damage_class?: string | null;
    power?: number | null;
    accuracy?: number | null;
    ailment_name?: string | null;
}

const props = defineProps<{
    move: LearnsetMoveRow;
}>();

function formatMoveName(name: string): string {
    return name
        .split('-')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

const typeSlug = computed(() => (props.move.type_slug ? props.move.type_slug.toLowerCase() : ''));

const typeStyle = computed(() => {
    const s = typeSlug.value;
    if (!s || s === 'unknown') {
        return { backgroundColor: 'var(--muted)' };
    }
    const cssVar = `--${s}type`;
    return { backgroundColor: `var(${cssVar}, var(--muted))` };
});

const damageIcon = computed(() => {
    const c = (props.move.damage_class || '').toLowerCase();
    if (c === 'physical') {
        return Swords;
    }
    if (c === 'special') {
        return Sparkles;
    }
    return CircleSlash;
});

const damageLabel = computed(() => {
    const c = (props.move.damage_class || '').toLowerCase();
    if (c === 'physical') {
        return 'Physical';
    }
    if (c === 'special') {
        return 'Special';
    }
    return 'Status';
});

function formatNum(n: number | null | undefined): string {
    if (n === null || n === undefined) {
        return '—';
    }
    return String(n);
}

const ailmentDisplay = computed(() => {
    const a = props.move.ailment_name;
    if (!a || a === 'none') {
        return null;
    }
    return a.split('-').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
});
</script>

<template>
    <li class="flex flex-wrap items-center gap-2 py-1 text-sm">
        <span
            class="inline-flex min-w-[4.5rem] justify-center rounded px-1.5 py-0.5 text-xs font-medium text-white capitalize shadow-sm"
            :style="typeStyle"
        >
            {{ typeSlug || '?' }}
        </span>
        <component
            :is="damageIcon"
            class="size-4 shrink-0 text-muted-foreground"
            :title="damageLabel"
            aria-hidden="true"
        />
        <span class="font-medium">{{ formatMoveName(move.move_name) }}</span>
        <span v-if="move.method === 'level-up' && move.level > 0" class="text-muted-foreground">— Lv. {{ move.level }}</span>
        <span class="ml-auto flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
            <span title="Power">Pow {{ formatNum(move.power) }}</span>
            <span title="Accuracy">Acc {{ formatNum(move.accuracy) }}</span>
            <span v-if="ailmentDisplay" class="text-amber-700 dark:text-amber-400" title="Secondary effect / ailment">{{
                ailmentDisplay
            }}</span>
        </span>
    </li>
</template>
