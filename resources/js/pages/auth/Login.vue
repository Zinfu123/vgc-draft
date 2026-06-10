<script setup lang="ts">
import AuthAlert from '@/components/auth/AuthAlert.vue';
import DiscordOAuthLink from '@/components/auth/DiscordOAuthLink.vue';
import MarketingAuthShell from '@/components/auth/MarketingAuthShell.vue';
import { authLinkClass } from '@/lib/authLink';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage();
const pageErrors = computed(() => page.props.errors as Record<string, string | undefined>);
</script>

<template>
    <Head title="Log in" />

    <MarketingAuthShell
        title="Log in"
        description="Welcome back. Log in with Discord to access your leagues and drafts."
    >
        <AuthAlert v-if="status" :message="status" class="mb-4" />

        <div class="flex flex-col gap-6">
            <div v-if="pageErrors.email || pageErrors.link_email" class="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                {{ pageErrors.email || pageErrors.link_email }}
            </div>

            <DiscordOAuthLink label="Log in with Discord" />

            <p class="text-center text-sm text-muted-foreground">
                Have an existing account without Discord?
                <Link :href="route('discord.link-form')" :class="authLinkClass">Link your account</Link>
            </p>
        </div>
    </MarketingAuthShell>
</template>
