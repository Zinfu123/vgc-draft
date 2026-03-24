<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/league/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface League {
    id: number;
    name: string;
    logo?: string;
    discord_webhook_url: string | null;
    discord_replay_webhook_url: string | null;
}

const props = defineProps<{
    league: League;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leagues', href: '/leagues' },
    { title: props.league.name, href: `/leagues/${props.league.id}` },
    { title: 'Admin', href: '#' },
];

const discordForm = useForm({
    discord_webhook_url: props.league.discord_webhook_url ?? '',
    discord_replay_webhook_url: props.league.discord_replay_webhook_url ?? '',
});

const handleDiscordSubmit = () => {
    discordForm.post(route('leagues.discord-webhook', { league: props.league.id }));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${props.league.name} — Admin`" />

        <AdminLayout :league-id="props.league.id" :league-name="props.league.name">
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Discord Notifications"
                    description="Add Discord webhook URLs to receive notifications for draft start/end, pick/ban turn reminders, and match results."
                />

                <form @submit.prevent="handleDiscordSubmit" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium" for="discord_webhook_url">Notifications Webhook URL</label>
                        <Input
                            id="discord_webhook_url"
                            type="url"
                            v-model="discordForm.discord_webhook_url"
                            placeholder="https://discord.com/api/webhooks/..."
                        />
                        <p class="text-xs text-muted-foreground">
                            Receives draft start/end, next-player pick/ban pings (with draft link), and match result notifications.
                        </p>
                        <p v-if="discordForm.errors.discord_webhook_url" class="text-sm text-destructive">{{ discordForm.errors.discord_webhook_url }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium" for="discord_replay_webhook_url">Replays Webhook URL</label>
                        <Input
                            id="discord_replay_webhook_url"
                            type="url"
                            v-model="discordForm.discord_replay_webhook_url"
                            placeholder="https://discord.com/api/webhooks/..."
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
                    <div class="flex justify-end pt-2">
                        <Button type="submit" :disabled="discordForm.processing">Save Webhooks</Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    </AppLayout>
</template>
