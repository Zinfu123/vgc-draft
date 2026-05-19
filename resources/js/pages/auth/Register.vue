<script setup lang="ts">
import DiscordOAuthLink from '@/components/auth/DiscordOAuthLink.vue';
import MarketingAuthShell from '@/components/auth/MarketingAuthShell.vue';
import { authLinkClass } from '@/lib/authLink';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Register" />

    <MarketingAuthShell
        title="Create an account"
        description="Join with Discord to manage your VGC league season."
    >
        <div class="flex flex-col gap-6">
            <DiscordOAuthLink label="Register with Discord" intent="register" />

            <p class="text-center text-sm text-muted-foreground">
                Already have an account?
                <Link :href="route('login')" :class="authLinkClass" :tabindex="1">Log in</Link>
            </p>
        </div>
    </MarketingAuthShell>
</template>
