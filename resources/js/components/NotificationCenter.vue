<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';
import type { NotificationCounts, NotificationItem, User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { CalendarClock, MessageSquare, RefreshCw, Swords } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    user: User;
    notifications: NotificationCounts | null | undefined;
}

const props = defineProps<Props>();

const { getInitials } = useInitials();

type FilterType = 'all' | 'message' | 'trade' | 'schedule';
const activeFilter = ref<FilterType>('all');

const avatarSrc = computed(() => {
    if (props.user.avatar && props.user.avatar !== '') {
        return props.user.avatar;
    }
    return props.user.discord_avatar_url || null;
});

const totalUnread = computed(() => {
    if (!props.notifications) {
        return 0;
    }
    return (
        props.notifications.unread_messages + props.notifications.pending_trades + props.notifications.pending_schedules
    );
});

const filteredItems = computed<NotificationItem[]>(() => {
    const items = props.notifications?.items ?? [];
    if (activeFilter.value === 'all') {
        return items;
    }
    return items.filter((item) => item.type === activeFilter.value);
});

const filterCounts = computed(() => ({
    all: totalUnread.value,
    message: props.notifications?.unread_messages ?? 0,
    trade: props.notifications?.pending_trades ?? 0,
    schedule: props.notifications?.pending_schedules ?? 0,
}));

const filters: { value: FilterType; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'message', label: 'Messages' },
    { value: 'trade', label: 'Trades' },
    { value: 'schedule', label: 'Schedules' },
];

function typeIcon(type: NotificationItem['type']) {
    if (type === 'message') {
        return MessageSquare;
    }
    if (type === 'trade') {
        return Swords;
    }
    return CalendarClock;
}

function typeColor(type: NotificationItem['type']): string {
    if (type === 'message') {
        return 'text-blue-500';
    }
    if (type === 'trade') {
        return 'text-orange-500';
    }
    return 'text-green-500';
}

function formatTime(isoString: string): string {
    const date = new Date(isoString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    if (diffMins < 1) {
        return 'just now';
    }
    if (diffMins < 60) {
        return `${diffMins}m ago`;
    }
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) {
        return `${diffHours}h ago`;
    }
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="relative inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <Avatar class="size-8 overflow-hidden rounded-full">
                <AvatarImage v-if="avatarSrc" :src="avatarSrc" :alt="user.name" />
                <AvatarFallback class="rounded-full bg-neutral-200 text-xs font-semibold text-black dark:bg-neutral-700 dark:text-white">
                    {{ getInitials(user.name) }}
                </AvatarFallback>
            </Avatar>

            <span
                v-if="totalUnread > 0"
                class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white"
            >
                {{ totalUnread > 99 ? '99+' : totalUnread }}
            </span>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-80 p-0">
            <!-- Filter tabs -->
            <div class="flex border-b border-border px-2 pt-2">
                <button
                    v-for="filter in filters"
                    :key="filter.value"
                    class="flex items-center gap-1.5 rounded-t-md px-3 py-2 text-xs font-medium transition-colors"
                    :class="
                        activeFilter === filter.value
                            ? 'border-b-2 border-primary text-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="activeFilter = filter.value"
                >
                    {{ filter.label }}
                    <span
                        v-if="filterCounts[filter.value] > 0"
                        class="rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white"
                    >
                        {{ filterCounts[filter.value] }}
                    </span>
                </button>
            </div>

            <!-- Notification items -->
            <div class="max-h-72 overflow-y-auto">
                <div v-if="filteredItems.length === 0" class="flex flex-col items-center gap-2 py-8 text-center">
                    <RefreshCw class="size-5 text-muted-foreground/50" />
                    <p class="text-sm text-muted-foreground">No new notifications</p>
                </div>

                <Link
                    v-for="item in filteredItems"
                    :key="`${item.type}-${item.id}`"
                    :href="item.href"
                    class="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-accent"
                >
                    <span class="mt-0.5 shrink-0" :class="typeColor(item.type)">
                        <component :is="typeIcon(item.type)" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-foreground">{{ item.title }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ item.body }}</p>
                    </div>
                    <span class="shrink-0 text-[10px] text-muted-foreground/70">
                        {{ formatTime(item.created_at) }}
                    </span>
                </Link>
            </div>

            <DropdownMenuSeparator />

            <!-- User menu at bottom -->
            <div class="p-1">
                <UserMenuContent :user="user" />
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
