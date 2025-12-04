<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { ref } from 'vue';

const isOpen: { [key: number]: boolean } = ref({});

interface Set {
    [key: number]: {
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
        };
    };
}
interface props {
    set: Set;
}

const props = defineProps<props>();
</script>

<template>
    <nav class="h-full overflow-y-auto" aria-label="Matches">
        <div v-for="key in Object.keys(props.set)" :key="key" class="relative">
            <Collapsible :open="isOpen[key]" @open-change="isOpen[key] = $event">
                <div
                    class="sticky top-0 z-10 border-y border-t-gray-100 border-b-gray-200 bg-gray-50 px-3 py-1.5 text-sm/6 font-semibold text-gray-900 dark:border-t-white/5 dark:border-b-white/10 dark:bg-gray-900 dark:text-white dark:before:pointer-events-none dark:before:absolute dark:before:inset-0 dark:before:bg-white/5"
                >
                    <h3 class="relative">Round Number: {{ key }}</h3>
                    <CollapsibleTrigger as-child>
                        <Button variant="ghost" size="icon" class="absolute top-0 right-0" @click="isOpen[key] = !isOpen[key]">
                            <ChevronRight class="size-4" v-if="!isOpen[key]" />
                            <ChevronDown class="size-4" v-if="isOpen[key]" />
                        </Button>
                    </CollapsibleTrigger>
                </div>
                <CollapsibleContent>
                    <ul
                        role="list"
                        class="divide-y divide-gray-100 bg-white shadow-xs outline-1 outline-gray-900/5 sm:rounded-xl dark:divide-white/5 dark:bg-gray-800/50 dark:shadow-none dark:outline-white/10 dark:sm:-outline-offset-1"
                    >
                        <li
                            v-for="item in props.set[key]"
                            :key="item.id"
                            class="flex gap-x-4 px-3 py-5 hover:bg-gray-50 sm:px-6 dark:hover:bg-white/2.5"
                        >
                            <img
                                v-if="item.team1.logo"
                                class="size-12 flex-none rounded-full bg-gray-50 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                                :src="item.team1.logo"
                                alt=""
                            />
                            <div class="min-w-0">
                                <p class="text-sm/6 font-semibold text-gray-900 dark:text-white">{{ item.team1.name }}</p>
                                <p class="mt-1 truncate text-xs/5 text-gray-500 dark:text-gray-400">{{ item.team1.user.name }}</p>
                            </div>
                            <div class="flex flex-col items-center justify-center">
                                <p class="text-sm/6 font-semibold text-gray-900 dark:text-white">VS</p>
                            </div>
                            <img
                                v-if="item.team2.logo"
                                class="size-12 flex-none rounded-full bg-gray-50 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                                :src="item.team2.logo"
                                alt=""
                            />
                            <div class="min-w-0">
                                <p class="text-sm/6 font-semibold text-gray-900 dark:text-white">{{ item.team2.name }}</p>
                                <p class="mt-1 truncate text-xs/5 text-gray-500 dark:text-gray-400">{{ item.team2.user.name }}</p>
                            </div>
                        </li>
                    </ul>
                </CollapsibleContent>
            </Collapsible>
        </div>
    </nav>
</template>
