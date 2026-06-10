<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import CommissionerSubNav from '@/components/league/CommissionerSubNav.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, useForm } from '@inertiajs/vue3';

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
    league_owner: number;
    status: number;
    playoffs_enabled: boolean;
    discord_webhook_url: string | null;
    discord_replay_webhook_url: string | null;
}

interface Team {
    id: number;
    name: string;
    coach: string;
    user_id: number;
}

interface Draft {
    id: number | null;
    round_number: number;
    pick_number: number;
    status: number;
}

interface MatchConfig {
    id: number;
    league_id: number;
    number_of_pools: number;
    frequency_type: number;
    frequency_value: number;
    status: number;
}

const props = defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
}>();

const discordForm = useForm({
    discord_webhook_url: props.league.discord_webhook_url ?? '',
    discord_replay_webhook_url: props.league.discord_replay_webhook_url ?? '',
});

const handleDiscordSubmit = () => {
    discordForm.post(route('leagues.discord-webhook', { league: props.league.id }));
};
</script>

<template>
    <LeagueDetailLayout
        :league="league"
        section="commissioner"
        :teams="teams"
        :draft="draft"
        :adminFlag="adminFlag"
        :matchConfig="matchConfig"
    >
        <Head :title="`Discord · ${league.name}`" />

        <div class="flex flex-col gap-8">
            <CommissionerSubNav :league="league" />

            <section class="flex flex-col gap-6">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">Discord Notifications</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Add Discord webhook URLs to receive notifications for draft start/end, pick/ban turn reminders, and match results.
                    </p>
                </div>

                <form class="flex flex-col gap-5" @submit.prevent="handleDiscordSubmit">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium" for="discord_webhook_url">Notifications Webhook URL</label>
                        <Input
                            id="discord_webhook_url"
                            v-model="discordForm.discord_webhook_url"
                            type="url"
                            placeholder="https://discord.com/api/webhooks/…"
                            class="max-w-xl"
                        />
                        <p class="text-xs text-muted-foreground">
                            Receives draft start/end, next-player pick/ban pings (with draft link), and match result notifications.
                        </p>
                        <p v-if="discordForm.errors.discord_webhook_url" class="text-sm text-destructive">
                            {{ discordForm.errors.discord_webhook_url }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium" for="discord_replay_webhook_url">Replays Webhook URL</label>
                        <Input
                            id="discord_replay_webhook_url"
                            v-model="discordForm.discord_replay_webhook_url"
                            type="url"
                            placeholder="https://discord.com/api/webhooks/…"
                            class="max-w-xl"
                        />
                        <p class="text-xs text-muted-foreground">
                            Receives Pokémon Showdown replay links. Falls back to the notifications webhook if left empty.
                        </p>
                        <p v-if="discordForm.errors.discord_replay_webhook_url" class="text-sm text-destructive">
                            {{ discordForm.errors.discord_replay_webhook_url }}
                        </p>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Create webhooks under <strong>Channel Settings → Integrations → Webhooks</strong> in Discord.
                    </p>

                    <div class="flex pt-2">
                        <Button type="submit" :disabled="discordForm.processing">Save Webhooks</Button>
                    </div>
                </form>
            </section>
        </div>
    </LeagueDetailLayout>
</template>
