<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { onUnmounted, ref, watch } from 'vue';

interface Team {
    id: number;
    name: string;
    user: { id: number; name: string };
}

interface Battle {
    id: number;
    set_id: number;
    status: 'awaiting_teams' | 'team_preview' | 'active' | 'finished';
    winner: string | null;
    battle_log: string[];
    p1_packed_team: string | null;
    p2_packed_team: string | null;
    p1_team: Team;
    p2_team: Team;
}

interface Set {
    id: number;
    league_id: number;
}

const props = defineProps<{
    set: Set;
    battle: Battle;
    myPlayer: 'p1' | 'p2' | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Matches', href: route('leagues.matches', { league: props.set.league_id }) },
    { title: 'Match', href: route('sets.show', { set_id: props.set.id }) },
    { title: 'Battle', href: '#' },
];

// ─── Reactive state ──────────────────────────────────────────────────────────

const battleStatus = ref(props.battle.status);
const battleLog = ref<string[]>(props.battle.battle_log ?? []);
const winner = ref(props.battle.winner);
const latestOutput = ref<string[]>([]);

// ─── Reverb / Echo ──────────────────────────────────────────────────────────

const { stopListening } = useEchoPublic(`battle.${props.battle.id}`, 'BattleUpdatedEvent', (e: {
    status: Battle['status'];
    winner: string | null;
    output: string[];
    battle_log: string[];
}) => {
    battleStatus.value = e.status;
    battleLog.value = e.battle_log;
    winner.value = e.winner;
    latestOutput.value = e.output;
});

onUnmounted(() => stopListening());

// ─── Team submission ─────────────────────────────────────────────────────────

const teamForm = useForm({ packed_team: '' });

function submitTeam() {
    teamForm.post(route('battles.team', { battle: props.battle.id }), {
        preserveScroll: true,
    });
}

// ─── Action submission ───────────────────────────────────────────────────────

const actionForm = useForm({ action: '' });

function submitAction(action: string) {
    actionForm.action = action;
    actionForm.post(route('battles.action', { battle: props.battle.id }), {
        preserveScroll: true,
    });
}

// ─── Log parsing helpers ─────────────────────────────────────────────────────

/**
 * Extract the |request| JSON payload for a given player from output lines.
 * Returns null if no request is present for this player.
 */
function extractRequest(lines: string[], player: 'p1' | 'p2'): Record<string, unknown> | null {
    for (const line of lines) {
        // PS prefixes sidedata with the player slot, e.g. ">p1\n|request|{...}"
        if (line.includes('|request|')) {
            try {
                const json = line.split('|request|')[1];
                return JSON.parse(json);
            } catch {
                return null;
            }
        }
    }
    return null;
}

const currentRequest = ref<Record<string, unknown> | null>(null);

// Update currentRequest whenever new output arrives
watch(latestOutput, (lines) => {
    if (props.myPlayer) {
        currentRequest.value = extractRequest(lines, props.myPlayer);
    }
});

// ─── Display helpers ─────────────────────────────────────────────────────────

function formatLogLine(line: string): string {
    return line.replace(/^\|/, '').replace(/\|/g, ' ');
}

function isImportantLine(line: string): boolean {
    return (
        line.includes('|move|') ||
        line.includes('|damage|') ||
        line.includes('|faint|') ||
        line.includes('|win|') ||
        line.includes('|turn|') ||
        line.includes('|switch|')
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Battle" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">
                        {{ battle.p1_team.name }}
                        <span class="text-muted-foreground mx-2 font-normal">vs</span>
                        {{ battle.p2_team.name }}
                    </h1>
                    <p class="text-muted-foreground text-sm capitalize">{{ battleStatus?.replace(/_/g, ' ') }}</p>
                </div>
                <div v-if="winner" class="rounded-full bg-green-100 px-4 py-1 text-sm font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">
                    Winner: {{ winner }}
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Battle log -->
                <div class="lg:col-span-2 flex flex-col gap-4">
                    <div class="bg-card rounded-lg border p-4">
                        <h2 class="mb-3 font-semibold">Battle Log</h2>
                        <div class="max-h-96 overflow-y-auto space-y-1 font-mono text-sm">
                            <div
                                v-for="(line, i) in battleLog"
                                :key="i"
                                :class="[
                                    'px-2 py-0.5 rounded',
                                    isImportantLine(line) ? 'bg-muted font-medium' : 'text-muted-foreground',
                                ]"
                            >
                                {{ formatLogLine(line) }}
                            </div>
                            <div v-if="battleLog.length === 0" class="text-muted-foreground italic">
                                Waiting for battle to start...
                            </div>
                        </div>
                    </div>

                    <!-- Action panel (active battle) -->
                    <div v-if="myPlayer && battleStatus === 'active'" class="bg-card rounded-lg border p-4">
                        <h2 class="mb-3 font-semibold">Your Turn</h2>

                        <!-- Move buttons from request -->
                        <div v-if="currentRequest?.active" class="flex flex-wrap gap-2">
                            <Button
                                v-for="(move, idx) in (currentRequest.active as any[])[0]?.moves ?? []"
                                :key="idx"
                                :disabled="(move as any).disabled || actionForm.processing"
                                variant="outline"
                                @click="submitAction(`move ${idx + 1}`)"
                            >
                                {{ (move as any).move }}
                            </Button>
                        </div>

                        <!-- Switch buttons from request -->
                        <div v-if="currentRequest?.forceSwitch" class="mt-3 flex flex-wrap gap-2">
                            <template
                                v-for="(pokemon, idx) in (currentRequest as any).side?.pokemon ?? []"
                                :key="idx"
                            >
                                <Button
                                    v-if="!(pokemon as any).active && !(pokemon as any).fainted"
                                    variant="outline"
                                    :disabled="actionForm.processing"
                                    @click="submitAction(`switch ${idx + 1}`)"
                                >
                                    {{ (pokemon as any).ident }}
                                </Button>
                            </template>
                        </div>
                    </div>

                    <!-- Team preview pick -->
                    <div v-if="myPlayer && battleStatus === 'team_preview'" class="bg-card rounded-lg border p-4">
                        <h2 class="mb-1 font-semibold">Team Preview</h2>
                        <p class="text-muted-foreground mb-3 text-sm">Choose your lead order (e.g. "team 1 2 3 4")</p>
                        <div class="flex gap-2">
                            <Button
                                :disabled="actionForm.processing"
                                @click="submitAction('team 1 2 3 4')"
                            >
                                Lead 1-4
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Team submission sidebar -->
                <div class="flex flex-col gap-4">
                    <!-- P1 team status -->
                    <div class="bg-card rounded-lg border p-4">
                        <h2 class="mb-1 font-semibold">{{ battle.p1_team.name }}</h2>
                        <p v-if="battle.p1_packed_team" class="text-sm text-green-600 dark:text-green-400">Team submitted</p>
                        <p v-else class="text-muted-foreground text-sm">Waiting for team...</p>
                    </div>

                    <div class="bg-card rounded-lg border p-4">
                        <h2 class="mb-1 font-semibold">{{ battle.p2_team.name }}</h2>
                        <p v-if="battle.p2_packed_team" class="text-sm text-green-600 dark:text-green-400">Team submitted</p>
                        <p v-else class="text-muted-foreground text-sm">Waiting for team...</p>
                    </div>

                    <!-- Submit team form (only if awaiting and user is a participant) -->
                    <div v-if="myPlayer && battleStatus === 'awaiting_teams'" class="bg-card rounded-lg border p-4">
                        <h2 class="mb-2 font-semibold">Submit Your Team</h2>
                        <p class="text-muted-foreground mb-3 text-sm">
                            Paste your Pokémon Showdown packed team string below.
                        </p>
                        <form @submit.prevent="submitTeam" class="flex flex-col gap-3">
                            <Textarea
                                v-model="teamForm.packed_team"
                                placeholder="Paste packed team..."
                                rows="6"
                                class="font-mono text-xs"
                            />
                            <p v-if="teamForm.errors.packed_team" class="text-sm text-red-500">
                                {{ teamForm.errors.packed_team }}
                            </p>
                            <Button type="submit" :disabled="teamForm.processing">
                                Submit Team
                            </Button>
                        </form>
                    </div>

                    <div v-if="!myPlayer" class="bg-card rounded-lg border p-4">
                        <p class="text-muted-foreground text-sm">You are spectating this battle.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
