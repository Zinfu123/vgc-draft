import type { NavMainGroup } from '@/types';
import { BarChart3, BookOpen, ClipboardList, LayoutDashboard, LayoutList, Shield, Trophy } from 'lucide-vue-next';

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
        items: [
            { title: 'Pokedex', href: '/pokedex', icon: BookOpen },
            { title: 'Team coverage', href: '/team-coverage', icon: Shield },
            { title: 'Pool templates', href: '/pool-templates', icon: LayoutList },
            { title: 'Usage stats', href: '/usage-stats', icon: BarChart3 },
        ],
    },
];

export const appNavFlatItems = appNavGroups.flatMap((g) => g.items);
