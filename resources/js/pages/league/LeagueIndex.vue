<script setup lang="ts">
import LeagueCarousel from '@/components/league/LeagueCarousel.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Leagues',
        href: '/Leagues',
    },
];
interface CurrentLeagues {
    id: number;
    name: string;
    draft_config: { draft_date: string | null; draft_start_at: string | null } | null;
    set_start_date: string;
    logo: string | null;
    winner: string | null;
    status: number;
}

interface PastLeagues {
    id: number;
    name: string;
    draft_config: { draft_date: string | null; draft_start_at: string | null } | null;
    set_start_date: string;
    logo: string | null;
    winner: string | null;
    status: number;
}

// interface ParticipatingLeague {
//     id: number;
//     name: string;
// }

interface props {
    currentLeagues: CurrentLeagues[];
    pastLeagues: PastLeagues[];
}

const props = defineProps<props>();

const createLeagueDialogOpen = ref(false);

function startCreateLeagueWizard(): void {
    createLeagueDialogOpen.value = false;
    router.get(route('leagues.create-edit'), { command: 'create' });
}
</script>

<template>
    <Head title="Leagues" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mt-4 flex justify-end px-4 sm:mt-6 sm:mr-4 sm:px-0">
            <Button variant="outline" class="min-h-11 w-full touch-manipulation sm:w-auto" @click="createLeagueDialogOpen = true">
                Create League
            </Button>
        </div>
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-10 px-4 pb-10 sm:px-6">
            <section>
                <h1 class="mb-4 text-3xl font-bold">Current Leagues</h1>
                <div class="flex flex-wrap justify-center gap-4 sm:justify-start">
                    <LeagueCarousel :leagues="props.currentLeagues" />
                </div>
            </section>
            <section>
                <h1 class="mb-4 text-3xl font-bold">Past Leagues</h1>
                <div class="flex flex-wrap justify-center gap-4 sm:justify-start">
                    <LeagueCarousel :leagues="props.pastLeagues" />
                </div>
            </section>
        </div>

        <Dialog v-model:open="createLeagueDialogOpen">
            <DialogContent class="max-h-[min(90vh,32rem)] gap-0 overflow-y-auto border-border/80 p-0 sm:max-w-lg">
                <DialogHeader class="space-y-2 border-b p-6 pb-4 text-left">
                    <DialogTitle>Create a new league</DialogTitle>
                    <DialogDescription class="text-sm leading-relaxed text-muted-foreground">
                        A short wizard covers branding, schedule, ruleset, draft rules, and season structure. You then finish setup in Admin before the first draft.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-5 px-6 py-4 text-sm leading-relaxed text-muted-foreground">
                    <div>
                        <p class="mb-2 font-medium text-foreground">In the wizard you will</p>
                        <ol class="list-inside list-decimal space-y-1.5 pl-0.5">
                            <li>Name the league and optionally add a logo</li>
                            <li>Set draft date, season start, and how often teams play</li>
                            <li>Pick generation and game ruleset</li>
                            <li>Configure draft budget, roster minimum, and bans</li>
                            <li>Set whether the regular season stops after a fixed number of rounds (optional cap)</li>
                            <li>Choose playoff format and how many teams make the bracket (seeding comes later in Admin)</li>
                            <li>
                                Optionally add Discord webhooks for notifications and Showdown replay links (includes how to create
                                them in Discord)
                            </li>
                            <li>Review everything before creating</li>
                        </ol>
                    </div>
                    <div>
                        <p class="mb-2 font-medium text-foreground">To be ready to draft</p>
                        <ul class="list-inside list-disc space-y-1.5 pl-0.5">
                            <li>Confirm match configuration under Admin</li>
                            <li>
                                Add teams and build the Pokémon pool — each coach needs a Pokémon Showdown username on
                                <strong class="text-foreground">Settings → Profile</strong> and/or on the
                                team form when they join (required for replays and match tools).
                            </li>
                            <li>Set draft pick order and start the draft from Admin → Draft</li>
                        </ul>
                    </div>
                </div>
                <DialogFooter class="gap-2 border-t border-border/80 p-6 pt-4 sm:justify-end">
                    <Button type="button" variant="outline" class="min-h-11 w-full touch-manipulation sm:w-auto" @click="createLeagueDialogOpen = false">
                        Cancel
                    </Button>
                    <Button type="button" class="min-h-11 w-full touch-manipulation sm:w-auto" @click="startCreateLeagueWizard">Start wizard</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
