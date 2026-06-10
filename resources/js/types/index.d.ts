import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface NavMainGroup {
    label: string;
    items: NavItem[];
}

export interface NotificationItem {
    id: number;
    type: 'message' | 'trade' | 'schedule';
    title: string;
    body: string;
    href: string;
    created_at: string;
}

export interface NotificationCounts {
    unread_messages: number;
    pending_trades: number;
    pending_schedules: number;
    items: NotificationItem[];
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    awsUrl?: string | null;
    notifications?: NotificationCounts | null;
    flash?: {
        success?: string | null;
    };
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    discord_id: string | null;
    discord_username: string | null;
    discord_avatar_url: string | null;
    showdown_username: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
