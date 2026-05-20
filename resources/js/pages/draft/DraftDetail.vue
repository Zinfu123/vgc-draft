<script setup lang="ts">
import DraftPicksPanel from '@/components/draft/DraftPicksPanel.vue';
import DraftTeamsPanel from '@/components/draft/DraftTeamsPanel.vue';
import DraftPokemonActionDialog from '@/components/draft/DraftPokemonActionDialog.vue';
import DraftTimerCard from '@/components/draft/DraftTimerCard.vue';
import DraftWishlistPanel, { type WishlistRowPokemon } from '@/components/draft/DraftWishlistPanel.vue';
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import PokemonFilter, { type PokemonFilters } from '@/components/draft/PokemonFilter.vue';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { ScrollArea } from '@/components/ui/scroll-area';
import Tabs from '@/components/ui/tabs/Tabs.vue';
import TabsContent from '@/components/ui/tabs/TabsContent.vue';
import TabsList from '@/components/ui/tabs/TabsList.vue';
import TabsTrigger from '@/components/ui/tabs/TabsTrigger.vue';
import { useMobileLayout } from '@/composables/useMobileLayout';
import AppLayout from '@/layouts/AppLayout.vue';
import { isReverbBroadcastClientConfigured } from '@/lib/broadcasting';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { ArrowUp, Ban, CheckCircle, Heart, ShieldBan, Swords } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface League {
    id: number;
    name: string;
}

interface DraftConfig {
    ban_enabled: boolean;
    bans_per_user: number;
    minimum_cost_to_ban: number;
    draft_points: number;
    minimum_drafts: number;
    pick_timer_enabled?: boolean;
    pick_timer_seconds?: number | null;
    quiet_hours_enabled?: boolean;
    quiet_hours_start?: string | null;
    quiet_hours_end?: string | null;
    quiet_hours_timezone?: string | null;
}

interface Pokemon {
    id: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
    cost: number;
    banned: number | boolean;
    is_drafted: number | boolean;
    drafted_by_team_id: number | null;
    drafted_by_team_name: string | null;
}

interface TeamSummary {
    id: number;
    name: string;
    logo: string | null;
    draft_points: number;
}

interface Draft {
    id: number | null;
    round_number: number;
    pick_number: number;
    status: number;
    current_deadline_at?: string | null;
    paused_at?: string | null;
    paused_remaining_seconds?: number | null;
}

interface DraftOrder {
    id: number;
    pick_number: number;
    round_number: number;
    status: number;
    team: TeamSummary | null;
}

interface BanOrderItem {
    id: number;
    ban_number: number;
    round_number: number;
    status: number;
    is_last_ban: number;
    team: TeamSummary | null;
}

interface UserTeam {
    id: number;
    admin_flag: number;
}

interface CurrentPicker {
    id: number;
    pick_number: number;
    round_number: number;
    team: (TeamSummary & { coach?: string | null }) | null;
}

interface CurrentBanner {
    id: number;
    ban_number: number;
    round_number: number;
    team: (TeamSummary & { coach?: string | null }) | null;
}

interface LastPick {
    id: number;
    round_number: number;
    pick_number: number;
    team: TeamSummary | null;
    league_pokemon: {
        id: number;
        name: string;
        cost: number;
        pokemon: {
            id: number;
            name: string;
            sprite_url: string;
            type1: string;
            type2: string;
        };
    };
}

interface LastBan {
    id: number;
    round_number: number;
    team: TeamSummary | null;
    pokedex: {
        id: number;
        name: string;
        sprite_url: string;
        type1: string;
        type2: string;
    } | null;
}

interface Team {
    id: number;
    name: string;
    draft_points: number;
    draft_picks: {
        id: number;
        league_pokemon: {
            id: number;
            cost: number;
            pokemon: {
                id: number;
                name: string;
                sprite_url: string;
                type1: string;
                type2: string;
            };
        };
    }[];
    logo: string | null;
    set_wins: number;
    set_losses: number;
    victory_points: number;
}

interface BanEntry {
    id: number;
    round_number: number;
    team: {
        id: number;
        name: string;
        logo: string | null;
    } | null;
    pokedex: {
        id: number;
        name: string;
        type1: string;
        type2: string;
    } | null;
}

interface Props {
    league: League;
    draftConfig: DraftConfig | null;
    teams: Team[];
    pokemon: Pokemon[];
    draft: Draft | null;
    costHeaders: number[];
    draftOrders: DraftOrder[];
    banOrders: BanOrderItem[];
    currentPicker: CurrentPicker | null;
    currentBanner: CurrentBanner | null;
    userTeam: UserTeam | null;
    canManageDraftAsAdmin?: boolean;
    lastPick: LastPick | null;
    lastBan: LastBan | null;
    allBans: BanEntry[];
    wishlist_league_pokemon_ids?: number[];
}

const props = withDefaults(defineProps<Props>(), {
    wishlist_league_pokemon_ids: () => [],
});

const { isMobile } = useMobileLayout();

const filters = ref<PokemonFilters>({ name: '', minCost: undefined, maxCost: undefined });
const selectedPokemon = ref<Pokemon | null>(null);
const isDialogOpen = ref(false);
const isSubmitting = ref(false);
const pickError = ref<string | null>(null);

const isBanPhase = computed(() => props.draft?.status === 2);
const isDraftPhase = computed(() => props.draft?.status === 1);

const isDraftActive = computed(() => props.draft?.status === 1 || props.draft?.status === 2);

const isPreDraft = computed(() => props.draft === null);

const wishlistIdSet = computed(() => new Set(props.wishlist_league_pokemon_ids));

const isWishlistStolen = (pokemon: Pokemon): boolean => {
    if (!pokemon.is_drafted) {
        return false;
    }
    if (!wishlistIdSet.value.has(pokemon.id)) {
        return false;
    }
    if (pokemon.drafted_by_team_id === null || props.userTeam === null) {
        return false;
    }

    return pokemon.drafted_by_team_id !== props.userTeam.id;
};

const wishlistPanelRows = computed((): WishlistRowPokemon[] =>
    props.wishlist_league_pokemon_ids
        .map((id) => props.pokemon.find((p) => p.id === id))
        .filter((p): p is Pokemon => p !== undefined)
        .map((p) => ({ ...p, wishlistStolen: isWishlistStolen(p) })),
);

const boardShellBorderClass = computed(() => {
    if (isPreDraft.value) {
        return 'border-gray-300 dark:border-gray-600';
    }
    if (isBanPhase.value) {
        return 'border-orange-400 dark:border-orange-600';
    }

    return 'border-blue-400 dark:border-blue-600';
});

const isMyTurnToBan = computed(() => isBanPhase.value && props.currentBanner?.team?.id === props.userTeam?.id);
const isMyTurnToPick = computed(() => isDraftPhase.value && props.currentPicker?.team?.id === props.userTeam?.id);
const isMyTurn = computed(() => isMyTurnToBan.value || isMyTurnToPick.value);

const minCostToBan = computed(() => props.draftConfig?.minimum_cost_to_ban ?? 0);

const timerEnabled = computed(() => Boolean(props.draftConfig?.pick_timer_enabled));
const isManuallyPaused = computed(() => Boolean(props.draft?.paused_at));

const quietHoursClock = ref(Date.now());
let quietHoursTickId: number | null = null;

onMounted(() => {
    quietHoursTickId = window.setInterval(() => {
        quietHoursClock.value = Date.now();
    }, 30 * 1000);
});

onBeforeUnmount(() => {
    if (quietHoursTickId !== null) {
        window.clearInterval(quietHoursTickId);
        quietHoursTickId = null;
    }
});

const minutesFromTimeString = (value: string | null | undefined): number | null => {
    if (!value) return null;
    const match = /^(\d{1,2}):(\d{2})/.exec(value);
    if (!match) return null;
    const hour = Number(match[1]);
    const minute = Number(match[2]);
    if (Number.isNaN(hour) || Number.isNaN(minute)) return null;
    return hour * 60 + minute;
};

const currentMinutesInZone = (timezone: string, atMs: number): number => {
    try {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
        }).formatToParts(new Date(atMs));
        let hour = 0;
        let minute = 0;
        for (const part of parts) {
            if (part.type === 'hour') hour = Number(part.value);
            if (part.type === 'minute') minute = Number(part.value);
        }
        return hour * 60 + minute;
    } catch {
        const d = new Date(atMs);
        return d.getHours() * 60 + d.getMinutes();
    }
};

const isQuietHoursActive = computed(() => {
    if (!props.draftConfig?.quiet_hours_enabled) return false;
    const start = minutesFromTimeString(props.draftConfig?.quiet_hours_start);
    const end = minutesFromTimeString(props.draftConfig?.quiet_hours_end);
    if (start === null || end === null || start === end) return false;
    const current = currentMinutesInZone(
        props.draftConfig?.quiet_hours_timezone || 'America/New_York',
        quietHoursClock.value,
    );
    return start < end ? current >= start && current < end : current >= start || current < end;
});

const userTeamData = computed(() => props.teams.find((t) => t.id === props.userTeam?.id) ?? null);

const minimumPicksRemaining = computed(() => {
    const minimumDrafts = props.draftConfig?.minimum_drafts ?? 0;
    const picksMade = userTeamData.value?.draft_picks.length ?? 0;
    return Math.max(0, minimumDrafts - picksMade);
});

const applyFilters = (pokemon: Pokemon[]) =>
    pokemon.filter((p) => {
        if (filters.value.name && !p.name.toLowerCase().includes(filters.value.name.toLowerCase())) return false;
        if (filters.value.minCost !== undefined && p.cost < filters.value.minCost) return false;
        if (filters.value.maxCost !== undefined && p.cost > filters.value.maxCost) return false;
        return true;
    });

const filteredPokemon = computed(() => applyFilters(props.pokemon));

const filteredCostHeaders = computed(() =>
    [...new Set(filteredPokemon.value.map((p) => p.cost))].sort((a, b) => b - a),
);


const isClickable = (pokemon: Pokemon) => {
    if (isSubmitting.value || pokemon.banned || pokemon.is_drafted) return false;
    if (isBanPhase.value && isMyTurnToBan.value) return pokemon.cost >= minCostToBan.value;
    if (isDraftPhase.value && isMyTurnToPick.value) return true;
    return false;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: props.league.name, href: `/leagues/${props.league.id}` },
    { title: 'Draft', href: `/draft/${props.league.id}` },
];

const reloadKeys = [
    'banOrders',
    'draftOrders',
    'pokemon',
    'costHeaders',
    'teams',
    'currentBanner',
    'currentPicker',
    'lastBan',
    'lastPick',
    'draft',
    'canManageDraftAsAdmin',
    'wishlist_league_pokemon_ids',
];

if (isReverbBroadcastClientConfigured) {
    useEchoPublic(`draft.detail.${props.league.id}`, 'DraftDetailEvent', () => {
        router.visit(route('draft.detail', { league_id: props.league.id }), {
            only: reloadKeys,
            preserveState: true,
            preserveScroll: true,
        });
    });

    if (props.draft?.id) {
        useEchoPublic(`end.draft.${props.draft.id}`, 'EndDraftEvent', () => {
            router.visit(route('leagues.draft', { league: props.league.id }));
        });
    }
}

const scrollToTop = () => window.scrollTo({ top: 0, behavior: 'smooth' });

const revertLastPick = () => router.post(route('draft.revert-last-pick'), { league_id: props.league.id });
const abortDraft = () => router.post(route('draft.abort-draft'), { league_id: props.league.id });

const openPokemonManageDialog = (pokemon: Pokemon) => {
    if (!props.userTeam) {
        return;
    }
    selectedPokemon.value = pokemon;
    pickError.value = null;
    isDialogOpen.value = true;
};

const openActionDialog = (pokemon: Pokemon) => {
    if (pokemon.banned || pokemon.is_drafted) {
        return;
    }
    openPokemonManageDialog(pokemon);
};

const openWishlistRowDialog = (row: WishlistRowPokemon) => {
    const full = props.pokemon.find((p) => p.id === row.id);
    if (full) {
        openPokemonManageDialog(full);
    }
};

const onDialogCancel = () => {
    pickError.value = null;
};

const canConfirmBanOrPick = computed(() => (selectedPokemon.value ? isClickable(selectedPokemon.value) : false));

const isTogglingWishlist = ref(false);

const selectedIsOnWishlist = computed(() =>
    selectedPokemon.value ? wishlistIdSet.value.has(selectedPokemon.value.id) : false,
);

const canToggleWishlist = computed(
    () =>
        !!props.userTeam &&
        !!selectedPokemon.value &&
        !selectedPokemon.value.banned &&
        !selectedPokemon.value.is_drafted,
);

const wishlistPanelError = ref<string | null>(null);
const wishlistRemovingId = ref<number | null>(null);
const wishlistReordering = ref(false);

const reloadDraftDetailProps = () => {
    router.visit(route('draft.detail', { league_id: props.league.id }), {
        only: [...reloadKeys],
        preserveState: true,
        preserveScroll: true,
    });
};

const postWishlistToggle = (leaguePokemonId: number, source: 'dialog' | 'panel') => {
    if (!props.userTeam) {
        return;
    }
    if (source === 'dialog') {
        if (isTogglingWishlist.value) {
            return;
        }
        isTogglingWishlist.value = true;
    } else {
        if (wishlistRemovingId.value !== null) {
            return;
        }
        wishlistRemovingId.value = leaguePokemonId;
        wishlistPanelError.value = null;
    }

    router.post(
        route('draft.wishlist.toggle'),
        {
            league_id: props.league.id,
            league_pokemon_id: leaguePokemonId,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                reloadDraftDetailProps();
            },
            onError: (errors) => {
                const msg =
                    (errors as Record<string, string>).league_pokemon_id ??
                    (errors as Record<string, string>).league_id ??
                    'Could not update wishlist.';
                if (source === 'dialog') {
                    pickError.value = msg;
                } else {
                    wishlistPanelError.value = msg;
                }
            },
            onFinish: () => {
                if (source === 'dialog') {
                    isTogglingWishlist.value = false;
                } else {
                    wishlistRemovingId.value = null;
                }
            },
        },
    );
};

const toggleWishlist = () => {
    if (!selectedPokemon.value || !props.userTeam) {
        return;
    }
    postWishlistToggle(selectedPokemon.value.id, 'dialog');
};

const removeWishlistRow = (row: WishlistRowPokemon) => {
    postWishlistToggle(row.id, 'panel');
};

const reorderWishlist = (leaguePokemonIds: number[]) => {
    if (!props.userTeam || wishlistReordering.value || wishlistRemovingId.value !== null) {
        return;
    }
    wishlistReordering.value = true;
    wishlistPanelError.value = null;

    router.post(
        route('draft.wishlist.reorder'),
        {
            league_id: props.league.id,
            league_pokemon_ids: leaguePokemonIds,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                reloadDraftDetailProps();
            },
            onError: (errors) => {
                wishlistPanelError.value =
                    (errors as Record<string, string>).league_pokemon_ids ??
                    (errors as Record<string, string>).league_id ??
                    'Could not reorder wishlist.';
            },
            onFinish: () => {
                wishlistReordering.value = false;
            },
        },
    );
};

const submitAction = () => {
    if (isSubmitting.value || !selectedPokemon.value) return;
    isSubmitting.value = true;
    isDialogOpen.value = false;

    const routeName = isBanPhase.value ? 'draft.ban' : 'draft.pick';
    const payload = isBanPhase.value
        ? { pokemon_id: selectedPokemon.value.id, league_id: props.league.id }
        : {
              pokemon_id: selectedPokemon.value.id,
              pokemon_name: selectedPokemon.value.name,
              pokemon_cost: selectedPokemon.value.cost,
              league_id: props.league.id,
          };

    router.post(route(routeName), payload, {
        onSuccess: () => {
            selectedPokemon.value = null;
            router.visit(route('draft.detail', { league_id: props.league.id }), {
                only: reloadKeys,
                preserveState: true,
                preserveScroll: true,
            });
        },
        onError: (errors) => {
            pickError.value = (errors as Record<string, string>).error ?? 'Something went wrong. Please try again.';
            isDialogOpen.value = true;
            isSubmitting.value = false;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head>
            <title>{{ props.league.name }} Draft</title>
        </Head>

        <div class="mx-auto max-w-screen-2xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <div
                v-if="isDraftActive && isManuallyPaused"
                class="rounded-lg border border-gray-300 bg-gray-100 px-4 py-2 text-sm font-medium text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                role="status"
            >
                Draft is paused by the commissioner.
            </div>
            <div
                v-else-if="isDraftActive && timerEnabled && isQuietHoursActive"
                class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-800 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-200"
                role="status"
            >
                Quiet hours — auto-skip is paused. Picks are still allowed; the timer resumes after quiet hours end.
            </div>
            <!-- Header -->
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div class="flex flex-col gap-1.5">
                    <Link
                        :href="`/leagues/${props.league.id}`"
                        class="inline-flex w-fit items-center gap-1 text-sm font-medium text-gray-500 transition-colors hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        {{ props.league.name }}
                    </Link>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl dark:text-white">{{ props.league.name }} Draft</h1>
                    <span
                        v-if="isBanPhase"
                        class="inline-flex items-center gap-1.5 rounded-full bg-orange-100 px-3 py-1 text-sm font-semibold text-orange-800 dark:bg-orange-900/30 dark:text-orange-400"
                    >
                        <ShieldBan class="size-4" /> Ban Phase
                    </span>
                    <span
                        v-else-if="isDraftPhase"
                        class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-800 dark:bg-blue-900/30 dark:text-blue-400"
                    >
                        <Swords class="size-4" /> Draft Phase
                    </span>
                    <span
                        v-else-if="isPreDraft"
                        class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-800 dark:bg-gray-800/80 dark:text-gray-200"
                    >
                        Pre-draft
                    </span>
                    <span
                        v-if="userTeamData"
                        class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1 text-sm font-semibold text-gray-700 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200"
                    >
                        {{ userTeamData.name }} &mdash; {{ userTeamData.draft_points }} pts
                    </span>
                    <span
                        v-if="userTeamData && isDraftPhase && minimumPicksRemaining > 0"
                        class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-400"
                    >
                        {{ minimumPicksRemaining }} min. pick{{ minimumPicksRemaining === 1 ? '' : 's' }} remaining
                    </span>
                </div>
                </div>
                <div v-if="!isMobile" class="flex flex-wrap items-center gap-2">
                    <DraftPicksPanel :teams="props.teams" :bans="props.allBans" />
                    <DraftTeamsPanel :teams="props.teams" :bans="props.allBans" />
                    <ButtonGroup v-if="props.canManageDraftAsAdmin === true && isDraftActive">
                        <Button variant="outline" size="sm" @click="revertLastPick">Revert Last Pick</Button>
                        <Button variant="destructive" size="sm" @click="abortDraft">Abort Draft</Button>
                    </ButtonGroup>
                </div>
            </div>

            <Tabs v-if="isMobile" default-value="board" class="w-full gap-4">
                <TabsList
                    class="grid h-auto w-full gap-1 p-1"
                    :class="props.userTeam ? 'grid-cols-4' : 'grid-cols-3'"
                >
                    <TabsTrigger value="overview" class="touch-manipulation px-0.5 text-[10px] sm:px-1 sm:text-xs">Overview</TabsTrigger>
                    <TabsTrigger value="board" class="touch-manipulation px-0.5 text-[10px] sm:px-1 sm:text-xs">Board</TabsTrigger>
                    <TabsTrigger value="teams" class="touch-manipulation px-0.5 text-[10px] sm:px-1 sm:text-xs">Teams</TabsTrigger>
                    <TabsTrigger
                        v-if="props.userTeam"
                        value="wishlist"
                        class="touch-manipulation px-0.5 text-[10px] sm:px-1 sm:text-xs"
                    >
                        <span class="inline-flex items-center gap-0.5 sm:gap-1">
                            <Heart class="size-3 shrink-0 text-red-500 sm:size-3.5" />
                            Wishlist
                        </span>
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="overview" class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 gap-4">
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800/50">
                            <div class="border-b border-gray-100 px-5 py-3 dark:border-white/10">
                                <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ isBanPhase ? 'Currently Banning' : 'Currently Picking' }}
                                </h2>
                            </div>
                            <div class="flex items-center gap-4 px-5 py-4">
                                <template v-if="isBanPhase">
                                    <img
                                        v-if="props.currentBanner?.team?.logo"
                                        :src="props.currentBanner.team.logo"
                                        class="size-14 rounded-full bg-gray-200 object-cover ring-2 ring-orange-400 dark:bg-gray-700"
                                        alt=""
                                    />
                                    <div
                                        v-else
                                        class="flex size-14 shrink-0 items-center justify-center rounded-full bg-orange-100 ring-2 ring-orange-400 dark:bg-orange-900/30"
                                    >
                                        <ShieldBan class="size-6 text-orange-500" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-base font-semibold text-gray-900 dark:text-white">
                                            {{ props.currentBanner?.team?.name ?? 'N/A' }}
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Ban {{ props.currentBanner?.ban_number }} &mdash; Round {{ props.currentBanner?.round_number }} of
                                            {{ props.draftConfig?.bans_per_user }}
                                        </p>
                                    </div>
                                </template>
                                <template v-else>
                                    <img
                                        v-if="props.currentPicker?.team?.logo"
                                        :src="props.currentPicker.team.logo"
                                        class="size-14 rounded-full bg-gray-200 object-cover ring-2 ring-blue-400 dark:bg-gray-700"
                                        alt=""
                                    />
                                    <div
                                        v-else
                                        class="flex size-14 shrink-0 items-center justify-center rounded-full bg-blue-100 ring-2 ring-blue-400 dark:bg-blue-900/30"
                                    >
                                        <Swords class="size-6 text-blue-500" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-base font-semibold text-gray-900 dark:text-white">
                                            {{ props.currentPicker?.team?.name ?? 'N/A' }}
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Round {{ props.currentPicker?.round_number }} &mdash; Pick {{ props.draft?.pick_number }}
                                        </p>
                                    </div>
                                </template>
                                <span
                                    v-if="isMyTurn"
                                    class="shrink-0 animate-pulse rounded-full px-3 py-1 text-xs font-bold"
                                    :class="
                                        isBanPhase
                                            ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                                            : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                    "
                                >
                                    YOUR TURN
                                </span>
                            </div>
                            <div v-if="timerEnabled && isDraftActive" class="border-t border-gray-100 px-5 py-3 dark:border-white/10">
                                <DraftTimerCard
                                    :league-id="props.league.id"
                                    :pick-timer-enabled="Boolean(props.draftConfig?.pick_timer_enabled)"
                                    :pick-timer-seconds="props.draftConfig?.pick_timer_seconds ?? null"
                                    :current-deadline-at="props.draft?.current_deadline_at ?? null"
                                    :paused-at="props.draft?.paused_at ?? null"
                                    :paused-remaining-seconds="props.draft?.paused_remaining_seconds ?? null"
                                    :quiet-hours-enabled="Boolean(props.draftConfig?.quiet_hours_enabled)"
                                    :quiet-hours-start="props.draftConfig?.quiet_hours_start ?? null"
                                    :quiet-hours-end="props.draftConfig?.quiet_hours_end ?? null"
                                    :quiet-hours-timezone="props.draftConfig?.quiet_hours_timezone ?? null"
                                    :can-manage="Boolean(props.canManageDraftAsAdmin)"
                                />
                            </div>
                        </div>
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800/50">
                            <div class="border-b border-gray-100 px-5 py-3 dark:border-white/10">
                                <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Action</h2>
                            </div>
                            <div v-if="isBanPhase && props.lastBan" class="flex items-center gap-4 px-5 py-4">
                                <img
                                    v-if="props.lastBan.team?.logo"
                                    :src="props.lastBan.team.logo"
                                    class="size-12 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                                    alt=""
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ props.lastBan.team?.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">banned</p>
                                    <p class="font-semibold capitalize text-red-600 dark:text-red-400">{{ props.lastBan.pokedex?.name }}</p>
                                </div>
                                <img
                                    v-if="props.lastBan.pokedex"
                                    :src="'https://raw.githubusercontent.com/Autumnchi/coloured-home-sprites/main/' + props.lastBan.pokedex.name + '.png'"
                                    :alt="props.lastBan.pokedex.name"
                                    class="size-16 rounded-lg bg-gray-100 object-contain dark:bg-gray-700"
                                />
                            </div>
                            <div v-else-if="!isBanPhase && props.lastPick" class="flex items-center gap-4 px-5 py-4">
                                <img
                                    v-if="props.lastPick.team?.logo"
                                    :src="props.lastPick.team.logo"
                                    class="size-12 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                                    alt=""
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ props.lastPick.team?.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">picked</p>
                                    <p class="font-semibold capitalize text-blue-600 dark:text-blue-400">
                                        {{ props.lastPick.league_pokemon.pokemon.name }}
                                    </p>
                                </div>
                                <div class="w-40 shrink-0">
                                    <PokemonCard
                                        :pokemon="{
                                            ...props.lastPick.league_pokemon.pokemon,
                                            cost: props.lastPick.league_pokemon.cost,
                                        }"
                                    />
                                </div>
                            </div>
                            <div v-else class="flex items-center justify-center px-5 py-8 text-sm text-gray-400 dark:text-gray-500">No actions yet</div>
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800/50">
                        <div class="border-b border-gray-100 px-5 py-3 dark:border-white/10">
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <span v-if="isBanPhase">Ban Order &mdash; Round {{ props.banOrders[0]?.round_number ?? 1 }}</span>
                                <span v-else>Draft Order &mdash; Round {{ props.draft?.round_number ?? 1 }}</span>
                            </h2>
                        </div>
                        <ScrollArea class="w-full">
                            <div class="flex gap-3 px-5 py-4">
                                <template v-if="isBanPhase">
                                    <div
                                        v-for="banOrder in props.banOrders"
                                        :key="banOrder.id"
                                        class="flex min-w-[90px] flex-col items-center gap-1.5 rounded-lg p-3 text-center transition-colors"
                                        :class="{
                                            'opacity-50 bg-gray-100 dark:bg-gray-700/50': banOrder.status === 0,
                                            'bg-orange-50 ring-2 ring-orange-400 dark:bg-orange-900/20':
                                                banOrder.status === 1 && banOrder.team?.id === props.currentBanner?.team?.id,
                                            'bg-gray-50 dark:bg-gray-800/30':
                                                banOrder.status === 1 && banOrder.team?.id !== props.currentBanner?.team?.id,
                                        }"
                                    >
                                        <img
                                            v-if="banOrder.team?.logo"
                                            :src="banOrder.team.logo"
                                            class="size-10 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                                            alt=""
                                        />
                                        <div v-else class="size-10 rounded-full bg-gray-200 dark:bg-gray-700" />
                                        <p class="w-full truncate text-xs font-medium text-gray-900 dark:text-white">{{ banOrder.team?.name }}</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ banOrder.team?.draft_points }} pts</span>
                                        <span v-if="banOrder.status === 0" class="text-xs text-gray-400 dark:text-gray-500">✓ Done</span>
                                        <span
                                            v-else-if="banOrder.team?.id === props.currentBanner?.team?.id"
                                            class="text-xs font-bold text-orange-600 dark:text-orange-400"
                                            >Banning</span
                                        >
                                        <span v-else class="text-xs text-gray-400 dark:text-gray-500">Waiting</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <div
                                        v-for="draftOrder in props.draftOrders"
                                        :key="draftOrder.id"
                                        class="flex min-w-[90px] flex-col items-center gap-1.5 rounded-lg p-3 text-center transition-colors"
                                        :class="{
                                            'opacity-50 bg-gray-100 dark:bg-gray-700/50': draftOrder.status === 0,
                                            'bg-blue-50 ring-2 ring-blue-400 dark:bg-blue-900/20':
                                                draftOrder.status === 1 && draftOrder.team?.id === props.currentPicker?.team?.id,
                                            'bg-gray-50 dark:bg-gray-800/30':
                                                draftOrder.status === 1 && draftOrder.team?.id !== props.currentPicker?.team?.id,
                                        }"
                                    >
                                        <img
                                            v-if="draftOrder.team?.logo"
                                            :src="draftOrder.team.logo"
                                            class="size-10 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                                            alt=""
                                        />
                                        <div v-else class="size-10 rounded-full bg-gray-200 dark:bg-gray-700" />
                                        <p class="w-full truncate text-xs font-medium text-gray-900 dark:text-white">{{ draftOrder.team?.name }}</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ draftOrder.team?.draft_points }} pts</span>
                                        <span v-if="draftOrder.status === 0" class="text-xs text-gray-400 dark:text-gray-500">✓ Done</span>
                                        <span
                                            v-else-if="draftOrder.team?.id === props.currentPicker?.team?.id"
                                            class="text-xs font-bold text-blue-600 dark:text-blue-400"
                                            >Picking</span
                                        >
                                        <span v-else class="text-xs text-gray-400 dark:text-gray-500">Waiting</span>
                                    </div>
                                </template>
                            </div>
                        </ScrollArea>
                    </div>
                </TabsContent>
                <TabsContent value="board" class="mt-4 space-y-4">
                    <PokemonFilter
                        v-model="filters"
                        :pokemon="props.pokemon"
                        :is-ban-phase="isBanPhase"
                        :min-cost-to-ban="minCostToBan"
                    />
                    <div class="overflow-hidden rounded-xl border-2 shadow-sm" :class="boardShellBorderClass">
                        <div v-if="isPreDraft" class="bg-muted/40 px-5 py-3 dark:bg-muted/20">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Draft has not started yet</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Tap an available Pokémon to manage your wishlist. When the draft begins, you can draft here on your turn.
                            </p>
                        </div>
                        <div
                            v-else-if="isMyTurn"
                            class="px-5 py-3"
                            :class="isBanPhase ? 'bg-orange-50 dark:bg-orange-900/20' : 'bg-blue-50 dark:bg-blue-900/20'"
                        >
                            <h2
                                class="flex items-center gap-2 font-semibold"
                                :class="isBanPhase ? 'text-orange-800 dark:text-orange-300' : 'text-blue-800 dark:text-blue-300'"
                            >
                                <ShieldBan v-if="isBanPhase" class="size-5" />
                                <Swords v-else class="size-5" />
                                <span v-if="isBanPhase">Your turn to ban &mdash; tap a Pokémon (min cost: {{ minCostToBan }})</span>
                                <span v-else>Your turn to pick &mdash; tap a Pokémon to draft it</span>
                            </h2>
                        </div>
                        <div
                            v-else
                            class="px-5 py-3"
                            :class="isBanPhase ? 'bg-orange-50 dark:bg-orange-900/20' : 'bg-blue-50 dark:bg-blue-900/20'"
                        >
                            <p
                                class="text-sm"
                                :class="isBanPhase ? 'text-orange-700 dark:text-orange-400' : 'text-blue-700 dark:text-blue-400'"
                            >
                                Waiting for
                                <span class="font-semibold">
                                    {{ isBanPhase ? (props.currentBanner?.team?.name ?? 'N/A') : (props.currentPicker?.team?.name ?? 'N/A') }}
                                </span>
                                to {{ isBanPhase ? 'ban' : 'pick' }}...
                            </p>
                        </div>
                        <div class="bg-white p-4 dark:bg-gray-900/20">
                            <div v-if="filteredPokemon.length === 0" class="py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                                No Pokémon match your filters.
                            </div>
                            <div v-else class="flex flex-col gap-6">
                                <div v-for="costHeader in filteredCostHeaders" :key="costHeader" class="flex flex-col gap-3">
                                    <div
                                        class="sticky top-0 z-10 rounded-md border border-gray-200 bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-white/10 dark:bg-gray-800 dark:text-gray-300"
                                    >
                                        Cost: {{ costHeader }}
                                        <span
                                            v-if="isBanPhase && costHeader < minCostToBan"
                                            class="ml-2 text-xs font-normal text-orange-500 dark:text-orange-400"
                                            >(not eligible to ban)</span
                                        >
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                        <div
                                            v-for="pokemon in filteredPokemon.filter((p) => p.cost === costHeader)"
                                            :key="pokemon.id"
                                            class="relative overflow-hidden rounded-lg"
                                            :class="
                                                isClickable(pokemon) || (!!props.userTeam && !pokemon.banned && !pokemon.is_drafted)
                                                    ? 'cursor-pointer transition-all active:scale-[0.98] sm:hover:scale-105 sm:hover:shadow-md'
                                                    : 'cursor-default'
                                            "
                                            @click="openActionDialog(pokemon)"
                                        >
                                            <PokemonCard :pokemon="pokemon" />
                                            <div
                                                v-if="pokemon.banned"
                                                class="absolute inset-0 flex flex-col items-center justify-center gap-1 rounded-lg bg-red-900/75"
                                            >
                                                <Ban class="size-7 text-white" />
                                                <span class="text-xs font-bold uppercase tracking-wide text-white">Banned</span>
                                            </div>
                                            <div
                                                v-else-if="pokemon.is_drafted"
                                                class="absolute inset-0 flex flex-col items-center justify-center gap-1 rounded-lg px-1"
                                                :class="isWishlistStolen(pokemon) ? 'bg-red-950/70' : 'bg-gray-900/60'"
                                            >
                                                <CheckCircle
                                                    class="size-7"
                                                    :class="isWishlistStolen(pokemon) ? 'text-red-400' : 'text-green-400'"
                                                />
                                                <span class="text-center text-xs font-bold uppercase leading-tight tracking-wide text-white">
                                                    Drafted By<br />{{ pokemon.drafted_by_team_name ?? '' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </TabsContent>
                <TabsContent value="teams" class="mt-4 flex flex-col gap-3">
                    <DraftPicksPanel :teams="props.teams" :bans="props.allBans" />
                    <DraftTeamsPanel :teams="props.teams" :bans="props.allBans" />
                    <ButtonGroup v-if="props.canManageDraftAsAdmin === true && isDraftActive" class="flex flex-col gap-2 sm:flex-row">
                        <Button variant="outline" size="sm" class="min-h-11 touch-manipulation" @click="revertLastPick">Revert Last Pick</Button>
                        <Button variant="destructive" size="sm" class="min-h-11 touch-manipulation" @click="abortDraft">Abort Draft</Button>
                    </ButtonGroup>
                </TabsContent>
                <TabsContent v-if="props.userTeam" value="wishlist" class="mt-4 space-y-3">
                    <p v-if="wishlistPanelError" class="rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive dark:bg-destructive/20">
                        {{ wishlistPanelError }}
                    </p>
                    <DraftWishlistPanel
                        :rows="wishlistPanelRows"
                        :removing-league-pokemon-id="wishlistRemovingId"
                        :wishlist-reorder-busy="wishlistReordering || wishlistRemovingId !== null"
                        @remove="removeWishlistRow"
                        @select="openWishlistRowDialog"
                        @reorder="reorderWishlist"
                    />
                </TabsContent>
            </Tabs>

            <!-- Desktop: main column + wishlist sidebar -->
            <div v-if="!isMobile" class="flex flex-col gap-6 lg:flex-row lg:items-start">
                <div class="min-w-0 flex-1 space-y-6">
            <!-- Status Cards (desktop) -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- Current Actor -->
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800/50">
                    <div class="border-b border-gray-100 px-5 py-3 dark:border-white/10">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            {{ isBanPhase ? 'Currently Banning' : 'Currently Picking' }}
                        </h2>
                    </div>
                    <div class="flex items-center gap-4 px-5 py-4">
                        <template v-if="isBanPhase">
                            <img
                                v-if="props.currentBanner?.team?.logo"
                                :src="props.currentBanner.team.logo"
                                class="size-14 rounded-full bg-gray-200 object-cover ring-2 ring-orange-400 dark:bg-gray-700"
                                alt=""
                            />
                            <div
                                v-else
                                class="flex size-14 shrink-0 items-center justify-center rounded-full bg-orange-100 ring-2 ring-orange-400 dark:bg-orange-900/30"
                            >
                                <ShieldBan class="size-6 text-orange-500" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-base font-semibold text-gray-900 dark:text-white">
                                    {{ props.currentBanner?.team?.name ?? 'N/A' }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Ban {{ props.currentBanner?.ban_number }} &mdash; Round {{ props.currentBanner?.round_number }} of
                                    {{ props.draftConfig?.bans_per_user }}
                                </p>
                            </div>
                        </template>
                        <template v-else>
                            <img
                                v-if="props.currentPicker?.team?.logo"
                                :src="props.currentPicker.team.logo"
                                class="size-14 rounded-full bg-gray-200 object-cover ring-2 ring-blue-400 dark:bg-gray-700"
                                alt=""
                            />
                            <div
                                v-else
                                class="flex size-14 shrink-0 items-center justify-center rounded-full bg-blue-100 ring-2 ring-blue-400 dark:bg-blue-900/30"
                            >
                                <Swords class="size-6 text-blue-500" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-base font-semibold text-gray-900 dark:text-white">
                                    {{ props.currentPicker?.team?.name ?? 'N/A' }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Round {{ props.currentPicker?.round_number }} &mdash; Pick {{ props.draft?.pick_number }}
                                </p>
                            </div>
                        </template>
                        <span
                            v-if="isMyTurn"
                            class="shrink-0 animate-pulse rounded-full px-3 py-1 text-xs font-bold"
                            :class="
                                isBanPhase
                                    ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                                    : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                            "
                        >
                            YOUR TURN
                        </span>
                    </div>
                    <div v-if="timerEnabled && isDraftActive" class="border-t border-gray-100 px-5 py-3 dark:border-white/10">
                        <DraftTimerCard
                            :league-id="props.league.id"
                            :pick-timer-enabled="Boolean(props.draftConfig?.pick_timer_enabled)"
                            :pick-timer-seconds="props.draftConfig?.pick_timer_seconds ?? null"
                            :current-deadline-at="props.draft?.current_deadline_at ?? null"
                            :paused-at="props.draft?.paused_at ?? null"
                            :paused-remaining-seconds="props.draft?.paused_remaining_seconds ?? null"
                            :quiet-hours-enabled="Boolean(props.draftConfig?.quiet_hours_enabled)"
                            :quiet-hours-start="props.draftConfig?.quiet_hours_start ?? null"
                            :quiet-hours-end="props.draftConfig?.quiet_hours_end ?? null"
                            :quiet-hours-timezone="props.draftConfig?.quiet_hours_timezone ?? null"
                            :can-manage="Boolean(props.canManageDraftAsAdmin)"
                        />
                    </div>
                </div>

                <!-- Last Action -->
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800/50">
                    <div class="border-b border-gray-100 px-5 py-3 dark:border-white/10">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Action</h2>
                    </div>
                    <!-- Last Ban -->
                    <div v-if="isBanPhase && props.lastBan" class="flex items-center gap-4 px-5 py-4">
                        <img
                            v-if="props.lastBan.team?.logo"
                            :src="props.lastBan.team.logo"
                            class="size-12 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                            alt=""
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ props.lastBan.team?.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">banned</p>
                            <p class="font-semibold capitalize text-red-600 dark:text-red-400">{{ props.lastBan.pokedex?.name }}</p>
                        </div>
                        <img
                            v-if="props.lastBan.pokedex"
                            :src="'https://raw.githubusercontent.com/Autumnchi/coloured-home-sprites/main/' + props.lastBan.pokedex.name + '.png'"
                            :alt="props.lastBan.pokedex.name"
                            class="size-16 rounded-lg bg-gray-100 object-contain dark:bg-gray-700"
                        />
                    </div>
                    <!-- Last Pick -->
                    <div v-else-if="!isBanPhase && props.lastPick" class="flex items-center gap-4 px-5 py-4">
                        <img
                            v-if="props.lastPick.team?.logo"
                            :src="props.lastPick.team.logo"
                            class="size-12 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                            alt=""
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ props.lastPick.team?.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">picked</p>
                            <p class="font-semibold capitalize text-blue-600 dark:text-blue-400">
                                {{ props.lastPick.league_pokemon.pokemon.name }}
                            </p>
                        </div>
                        <div class="w-40 shrink-0">
                            <PokemonCard
                                :pokemon="{
                                    ...props.lastPick.league_pokemon.pokemon,
                                    cost: props.lastPick.league_pokemon.cost,
                                }"
                            />
                        </div>
                    </div>
                    <div v-else class="flex items-center justify-center px-5 py-8 text-sm text-gray-400 dark:text-gray-500">No actions yet</div>
                </div>
            </div>

            <!-- Order Row (desktop) -->
            <div v-if="!isMobile" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800/50">
                <div class="border-b border-gray-100 px-5 py-3 dark:border-white/10">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <span v-if="isBanPhase">Ban Order &mdash; Round {{ props.banOrders[0]?.round_number ?? 1 }}</span>
                        <span v-else>Draft Order &mdash; Round {{ props.draft?.round_number ?? 1 }}</span>
                    </h2>
                </div>
                <ScrollArea class="w-full">
                    <div class="flex gap-3 px-5 py-4">
                        <!-- Ban order items -->
                        <template v-if="isBanPhase">
                            <div
                                v-for="banOrder in props.banOrders"
                                :key="banOrder.id"
                                class="flex min-w-[90px] flex-col items-center gap-1.5 rounded-lg p-3 text-center transition-colors"
                                :class="{
                                    'opacity-50 bg-gray-100 dark:bg-gray-700/50': banOrder.status === 0,
                                    'bg-orange-50 ring-2 ring-orange-400 dark:bg-orange-900/20': banOrder.status === 1 && banOrder.team?.id === props.currentBanner?.team?.id,
                                    'bg-gray-50 dark:bg-gray-800/30': banOrder.status === 1 && banOrder.team?.id !== props.currentBanner?.team?.id,
                                }"
                            >
                                <img
                                    v-if="banOrder.team?.logo"
                                    :src="banOrder.team.logo"
                                    class="size-10 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                                    alt=""
                                />
                                <div v-else class="size-10 rounded-full bg-gray-200 dark:bg-gray-700" />
                                <p class="w-full truncate text-xs font-medium text-gray-900 dark:text-white">{{ banOrder.team?.name }}</p>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ banOrder.team?.draft_points }} pts</span>
                                <span v-if="banOrder.status === 0" class="text-xs text-gray-400 dark:text-gray-500">✓ Done</span>
                                <span
                                    v-else-if="banOrder.team?.id === props.currentBanner?.team?.id"
                                    class="text-xs font-bold text-orange-600 dark:text-orange-400"
                                    >Banning</span
                                >
                                <span v-else class="text-xs text-gray-400 dark:text-gray-500">Waiting</span>
                            </div>
                        </template>
                        <!-- Draft order items -->
                        <template v-else>
                            <div
                                v-for="draftOrder in props.draftOrders"
                                :key="draftOrder.id"
                                class="flex min-w-[90px] flex-col items-center gap-1.5 rounded-lg p-3 text-center transition-colors"
                                :class="{
                                    'opacity-50 bg-gray-100 dark:bg-gray-700/50': draftOrder.status === 0,
                                    'bg-blue-50 ring-2 ring-blue-400 dark:bg-blue-900/20': draftOrder.status === 1 && draftOrder.team?.id === props.currentPicker?.team?.id,
                                    'bg-gray-50 dark:bg-gray-800/30': draftOrder.status === 1 && draftOrder.team?.id !== props.currentPicker?.team?.id,
                                }"
                            >
                                <img
                                    v-if="draftOrder.team?.logo"
                                    :src="draftOrder.team.logo"
                                    class="size-10 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                                    alt=""
                                />
                                <div v-else class="size-10 rounded-full bg-gray-200 dark:bg-gray-700" />
                                <p class="w-full truncate text-xs font-medium text-gray-900 dark:text-white">{{ draftOrder.team?.name }}</p>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ draftOrder.team?.draft_points }} pts</span>
                                <span v-if="draftOrder.status === 0" class="text-xs text-gray-400 dark:text-gray-500">✓ Done</span>
                                <span
                                    v-else-if="draftOrder.team?.id === props.currentPicker?.team?.id"
                                    class="text-xs font-bold text-blue-600 dark:text-blue-400"
                                    >Picking</span
                                >
                                <span v-else class="text-xs text-gray-400 dark:text-gray-500">Waiting</span>
                            </div>
                        </template>
                    </div>
                </ScrollArea>
            </div>

            <!-- Shared Pokémon Filter (desktop) -->
            <PokemonFilter
                v-if="!isMobile"
                v-model="filters"
                :pokemon="props.pokemon"
                :is-ban-phase="isBanPhase"
                :min-cost-to-ban="minCostToBan"
            />

            <!-- Pokémon Section (desktop) -->
            <div v-if="!isMobile" class="overflow-hidden rounded-xl border-2 shadow-sm" :class="boardShellBorderClass">
                <div v-if="isPreDraft" class="bg-muted/40 px-5 py-3 dark:bg-muted/20">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Draft has not started yet</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Click an available Pokémon to manage your wishlist. When the draft begins, you can draft here on your turn.
                    </p>
                </div>
                <div
                    v-else-if="isMyTurn"
                    class="px-5 py-3"
                    :class="isBanPhase ? 'bg-orange-50 dark:bg-orange-900/20' : 'bg-blue-50 dark:bg-blue-900/20'"
                >
                    <h2
                        class="flex items-center gap-2 font-semibold"
                        :class="isBanPhase ? 'text-orange-800 dark:text-orange-300' : 'text-blue-800 dark:text-blue-300'"
                    >
                        <ShieldBan v-if="isBanPhase" class="size-5" />
                        <Swords v-else class="size-5" />
                        <span v-if="isBanPhase">Your turn to ban &mdash; click a Pokémon to ban it (min cost: {{ minCostToBan }})</span>
                        <span v-else>Your turn to pick &mdash; click a Pokémon to draft it</span>
                    </h2>
                </div>
                <div
                    v-else
                    class="px-5 py-3"
                    :class="isBanPhase ? 'bg-orange-50 dark:bg-orange-900/20' : 'bg-blue-50 dark:bg-blue-900/20'"
                >
                    <p
                        class="text-sm"
                        :class="isBanPhase ? 'text-orange-700 dark:text-orange-400' : 'text-blue-700 dark:text-blue-400'"
                    >
                        Waiting for
                        <span class="font-semibold">
                            {{ isBanPhase ? (props.currentBanner?.team?.name ?? 'N/A') : (props.currentPicker?.team?.name ?? 'N/A') }}
                        </span>
                        to {{ isBanPhase ? 'ban' : 'pick' }}...
                    </p>
                </div>
                <!-- Pokémon Grid -->
                <div class="bg-white p-4 dark:bg-gray-900/20">
                    <div v-if="filteredPokemon.length === 0" class="py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                        No Pokémon match your filters.
                    </div>
                    <div v-else class="flex flex-col gap-6">
                        <div v-for="costHeader in filteredCostHeaders" :key="costHeader" class="flex flex-col gap-3">
                            <div
                                class="sticky top-0 z-10 rounded-md border border-gray-200 bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-white/10 dark:bg-gray-800 dark:text-gray-300"
                            >
                                Cost: {{ costHeader }}
                                <span v-if="isBanPhase && costHeader < minCostToBan" class="ml-2 text-xs font-normal text-orange-500 dark:text-orange-400"
                                    >(not eligible to ban)</span
                                >
                            </div>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                                <div
                                    v-for="pokemon in filteredPokemon.filter((p) => p.cost === costHeader)"
                                    :key="pokemon.id"
                                    @click="openActionDialog(pokemon)"
                                    class="relative overflow-hidden rounded-lg"
                                    :class="
                                        isClickable(pokemon) || (!!props.userTeam && !pokemon.banned && !pokemon.is_drafted)
                                            ? 'cursor-pointer transition-all hover:scale-105 hover:shadow-md'
                                            : 'cursor-default'
                                    "
                                >
                                    <PokemonCard :pokemon="pokemon" />
                                    <!-- Banned overlay -->
                                    <div
                                        v-if="pokemon.banned"
                                        class="absolute inset-0 flex flex-col items-center justify-center gap-1 rounded-lg bg-red-900/75"
                                    >
                                        <Ban class="size-7 text-white" />
                                        <span class="text-xs font-bold uppercase tracking-wide text-white">Banned</span>
                                    </div>
                                    <!-- Drafted overlay -->
                                    <div
                                        v-else-if="pokemon.is_drafted"
                                        class="absolute inset-0 flex flex-col items-center justify-center gap-1 rounded-lg px-1"
                                        :class="isWishlistStolen(pokemon) ? 'bg-red-950/70' : 'bg-gray-900/60'"
                                    >
                                        <CheckCircle
                                            class="size-7"
                                            :class="isWishlistStolen(pokemon) ? 'text-red-400' : 'text-green-400'"
                                        />
                                        <span class="text-center text-xs font-bold uppercase leading-tight tracking-wide text-white">
                                            Drafted By<br />{{ pokemon.drafted_by_team_name ?? '' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                </div>

                <aside
                    v-if="props.userTeam"
                    class="w-full shrink-0 space-y-2 lg:sticky lg:top-4 lg:w-64 xl:w-72"
                >
                    <p
                        v-if="wishlistPanelError"
                        class="rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive dark:bg-destructive/20"
                    >
                        {{ wishlistPanelError }}
                    </p>
                    <DraftWishlistPanel
                        :rows="wishlistPanelRows"
                        :removing-league-pokemon-id="wishlistRemovingId"
                        :wishlist-reorder-busy="wishlistReordering || wishlistRemovingId !== null"
                        @remove="removeWishlistRow"
                        @select="openWishlistRowDialog"
                        @reorder="reorderWishlist"
                    />
                </aside>
            </div>
        </div>

        <!-- Sticky Points Card -->
        <div
            v-if="userTeamData"
            class="fixed bottom-4 left-1/2 z-50 flex -translate-x-1/2 items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-lg dark:border-white/10 dark:bg-gray-800"
        >
            <div class="flex flex-col items-center">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Your</span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Points</span>
                </div>
                <span class="text-lg font-bold text-gray-900 dark:text-white">{{ userTeamData.draft_points }}</span>
            </div>
            <button
                class="ml-2 flex size-8 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-white/10 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600 dark:hover:text-gray-200"
                title="Back to top"
                @click="scrollToTop"
            >
                <ArrowUp class="size-4" />
            </button>
        </div>

        <DraftPokemonActionDialog
            v-model:open="isDialogOpen"
            :selected-pokemon="selectedPokemon"
            :pick-error="pickError"
            :is-ban-phase="isBanPhase"
            :is-pre-draft="isPreDraft"
            :can-confirm-ban-or-pick="canConfirmBanOrPick"
            :can-toggle-wishlist="canToggleWishlist"
            :selected-is-on-wishlist="selectedIsOnWishlist"
            :is-submitting="isSubmitting"
            :is-toggling-wishlist="isTogglingWishlist"
            @cancel="onDialogCancel"
            @toggle-wishlist="toggleWishlist"
            @submit="submitAction"
        />
    </AppLayout>
</template>
