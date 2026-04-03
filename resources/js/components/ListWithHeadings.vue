<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface SetRow {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1: {
        id: number;
        name: string;
        logo: string;
        user: {
            name: string;
        };
    };
    team2: {
        id: number;
        name: string;
        logo: string;
        user: {
            name: string;
        };
    } | null;
}

type SetMap = Record<number, SetRow[]>;

interface Props {
    set: SetMap;
}

const props = defineProps<Props>();

const roundKeys = computed(() =>
    Object.keys(props.set)
        .map((k) => Number(k))
        .filter((n) => !Number.isNaN(n))
        .sort((a, b) => a - b)
        .map(String),
);

const selectedRound = ref<string>('');

watch(
    roundKeys,
    (keys) => {
        if (keys.length === 0) {
            selectedRound.value = '';

            return;
        }

        if (!keys.includes(selectedRound.value)) {
            selectedRound.value = keys[keys.length - 1]!;
        }
    },
    { immediate: true },
);

const currentMatches = computed(() => props.set[Number(selectedRound.value)] ?? []);
</script>

<template>
    <div v-if="roundKeys.length > 0" class="flex w-full flex-col gap-3" role="region" aria-label="Match schedule by round">
        <div class="flex items-center gap-3">
            <select
                v-model="selectedRound"
                aria-label="Select round"
                class="flex h-9 w-full max-w-[14rem] rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm ring-offset-background focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
            >
                <option v-for="key in roundKeys" :key="key" :value="key">
                    Round {{ key }} &nbsp;·&nbsp; {{ set[Number(key)]?.length ?? 0 }} match{{ (set[Number(key)]?.length ?? 0) === 1 ? '' : 'es' }}
                </option>
            </select>

            <p class="text-xs text-muted-foreground">of {{ roundKeys.length }}</p>
        </div>

        <ul
            role="list"
            class="divide-y divide-border overflow-hidden rounded-xl border border-border/80 bg-card shadow-sm"
        >
            <li
                v-for="item in currentMatches"
                :key="item.id"
                class="transition-colors hover:bg-accent/40"
            >
                <Link
                    :href="`/match/set/${item.id}`"
                    class="flex w-full items-center gap-x-4 px-4 py-4 sm:px-5 sm:py-5"
                >
                    <img
                        v-if="item.team1.logo"
                        class="size-12 shrink-0 rounded-full bg-muted object-cover ring-1 ring-border/60"
                        :src="item.team1.logo"
                        alt=""
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-foreground">
                            {{ item.team1.name }}
                        </p>
                        <p class="mt-0.5 truncate text-xs text-muted-foreground">
                            {{ item.team1.user.name }}
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-col items-center justify-center px-1">
                        <span
                            class="text-xs font-bold uppercase tracking-wider text-muted-foreground"
                            aria-hidden="true"
                        >
                            {{ item.team2 ? 'vs' : 'bye' }}
                        </span>
                    </div>
                    <template v-if="item.team2">
                        <img
                            v-if="item.team2.logo"
                            class="size-12 shrink-0 rounded-full bg-muted object-cover ring-1 ring-border/60"
                            :src="item.team2.logo"
                            alt=""
                        />
                        <div class="min-w-0 flex-1 text-right sm:text-left">
                            <p class="text-sm font-semibold text-foreground">
                                {{ item.team2.name }}
                            </p>
                            <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                {{ item.team2.user.name }}
                            </p>
                        </div>
                    </template>
                    <div v-else class="min-w-0 flex-1 text-sm text-muted-foreground">Bye week</div>
                </Link>
            </li>
        </ul>
    </div>
</template>
