<script setup lang="ts">
import Tabs from '@/components/ui/tabs/Tabs.vue';
import TabsContent from '@/components/ui/tabs/TabsContent.vue';
import TabsList from '@/components/ui/tabs/TabsList.vue';
import TabsTrigger from '@/components/ui/tabs/TabsTrigger.vue';
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

function matchCount(roundKey: string): number {
    return props.set[Number(roundKey)]?.length ?? 0;
}
</script>

<template>
    <div v-if="roundKeys.length > 0" class="flex w-full flex-col gap-3" role="region" aria-label="Match schedule by round">
        <Tabs v-model="selectedRound" class="w-full gap-0">
            <div class="sticky top-0 z-10 -mx-0.5 pb-1 pt-0.5">
                <TabsList
                    class="h-auto min-h-10 w-full max-w-full flex-nowrap justify-start gap-1 overflow-x-auto overscroll-x-contain rounded-xl border border-border/70 bg-muted/40 p-1.5 shadow-sm [scrollbar-width:thin] dark:bg-muted/25 dark:shadow-inner [&::-webkit-scrollbar]:h-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-muted-foreground/25"
                >
                    <TabsTrigger
                        v-for="key in roundKeys"
                        :key="key"
                        :value="key"
                        class="group shrink-0 snap-start gap-2 px-3.5 py-2 text-sm font-semibold shadow-none data-[state=active]:border-border/80 data-[state=active]:shadow-sm sm:px-4"
                    >
                        <span class="tabular-nums">Round {{ key }}</span>
                        <span
                            class="rounded-full bg-muted-foreground/15 px-2 py-0.5 text-xs font-medium tabular-nums text-muted-foreground group-data-[state=active]:bg-primary/15 group-data-[state=active]:text-foreground"
                        >
                            {{ matchCount(key) }}
                        </span>
                    </TabsTrigger>
                </TabsList>
            </div>

            <TabsContent
                v-for="key in roundKeys"
                :key="`content-${key}`"
                :value="key"
                class="mt-3 focus-visible:outline-none"
            >
                <ul
                    role="list"
                    class="divide-y divide-border overflow-hidden rounded-xl border border-border/80 bg-card shadow-sm"
                >
                    <li
                        v-for="item in props.set[Number(key)]"
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
            </TabsContent>
        </Tabs>
    </div>
</template>
