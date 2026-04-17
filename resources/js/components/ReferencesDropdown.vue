<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { appNavGroups } from '@/config/appNav';
import { Link } from '@inertiajs/vue3';
import { Library } from 'lucide-vue-next';
import { computed } from 'vue';

const referenceItems = computed(() => appNavGroups.find((g) => g.label === 'Reference')?.items ?? []);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <Library class="size-5" />
            <span class="sr-only">References</span>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-52">
            <DropdownMenuLabel class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                References
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem v-for="item in referenceItems" :key="item.href" as-child>
                <Link :href="item.href" class="flex w-full items-center gap-2">
                    <component :is="item.icon" class="size-4 text-muted-foreground" />
                    <span>{{ item.title }}</span>
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
