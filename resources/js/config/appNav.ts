import type { NavMainGroup } from '@/types';
import { BookOpen, ClipboardList, LayoutDashboard, Trophy } from 'lucide-vue-next';

export const appNavGroups: NavMainGroup[] = [
    {
        label: 'Home & play',
        items: [
            { title: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
            { title: 'Leagues', href: '/leagues', icon: Trophy },
            { title: 'Match prep', href: '/match-prep', icon: ClipboardList },
        ],
    },
    {
        label: 'Reference',
        items: [{ title: 'Pokedex', href: '/pokedex', icon: BookOpen }],
    },
];

export const appNavFlatItems = appNavGroups.flatMap((g) => g.items);
