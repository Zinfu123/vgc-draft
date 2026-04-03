<script setup lang="ts">
import AuthAlert from '@/components/auth/AuthAlert.vue';
import DiscordOAuthLink from '@/components/auth/DiscordOAuthLink.vue';
import MarketingAuthShell from '@/components/auth/MarketingAuthShell.vue';
import PasswordInput from '@/components/auth/PasswordInput.vue';
import InputError from '@/components/InputError.vue';
import { authLinkClass } from '@/lib/authLink';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage();
const pageErrors = computed(() => page.props.errors as Record<string, string | undefined>);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const linkForm = useForm({
    link_email: '',
    link_password: '',
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const submitLinkDiscord = () => {
    linkForm.post(route('discord.prepare-link'), {
        onFinish: () => linkForm.reset('link_password'),
    });
};

</script>

<template>
    <Head title="Log in" />

    <MarketingAuthShell
        title="Log in"
        description="Welcome back. Use Discord or your email to access your leagues and drafts."
    >
        <AuthAlert v-if="status" :message="status" class="mb-4" />

        <div class="flex flex-col gap-6">
            <DiscordOAuthLink label="Log in with Discord" />

            <form @submit.prevent="submit" class="flex flex-col gap-4">
                <InputError :message="pageErrors.email || form.errors.email" />
                <InputError :message="form.errors.password" />

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="you@example.com"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <PasswordInput
                        id="password"
                        v-model="form.password"
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Password"
                        required
                    />
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 sm:gap-y-2">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-foreground">
                        <Checkbox id="remember" v-model:checked="form.remember" :tabindex="3" />
                        <span>Remember me</span>
                    </label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        :class="authLinkClass"
                        :tabindex="5"
                    >
                        Forgot password?
                    </Link>
                </div>

                <Button type="submit" class="w-full" :tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Log in
                </Button>
            </form>

            <div class="rounded-lg border border-border bg-muted/30 p-4 dark:bg-muted/15">
                <h2 class="mb-1 text-sm font-semibold text-foreground">Link Discord to an existing account</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Enter the email and password for the account you want to use, then authorize Discord—we’ll connect them and
                    sign you in.
                </p>
                <form @submit.prevent="submitLinkDiscord" class="flex flex-col gap-3">
                    <InputError :message="pageErrors.link_email || linkForm.errors.link_email" />
                    <InputError :message="pageErrors.link_password || linkForm.errors.link_password" />
                    <div class="grid gap-2">
                        <Label for="link_email">Account email</Label>
                        <Input
                            id="link_email"
                            v-model="linkForm.link_email"
                            type="email"
                            required
                            :tabindex="7"
                            autocomplete="email"
                            placeholder="you@example.com"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="link_password">Password</Label>
                        <PasswordInput
                            id="link_password"
                            v-model="linkForm.link_password"
                            :tabindex="8"
                            autocomplete="current-password"
                            placeholder="Your password"
                            required
                        />
                    </div>
                    <Button type="submit" variant="secondary" class="w-full" :tabindex="9" :disabled="linkForm.processing">
                        <LoaderCircle v-if="linkForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Continue to Discord
                    </Button>
                </form>
            </div>

            <p class="text-center text-sm text-muted-foreground">
                Don’t have an account?
                <Link :href="route('register')" :class="authLinkClass" :tabindex="6">Register</Link>
            </p>
        </div>
    </MarketingAuthShell>
</template>
