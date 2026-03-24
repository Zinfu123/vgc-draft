<script setup lang="ts">
import DiscordOAuthLink from '@/components/auth/DiscordOAuthLink.vue';
import MarketingAuthShell from '@/components/auth/MarketingAuthShell.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const pageErrors = computed(() => page.props.errors as Record<string, string | undefined>);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const authLinkClass =
    'text-sm font-medium text-[oklch(0.48_0.19_25)] underline decoration-[oklch(0.53_0.195_25/0.4)] underline-offset-4 transition-colors hover:text-[oklch(0.4_0.17_25)] dark:text-primary dark:decoration-primary/45 dark:hover:text-primary/90';
</script>

<template>
    <Head title="Register" />

    <MarketingAuthShell
        title="Create an account"
        description="Join with Discord or sign up with email to manage your VGC league season."
    >
        <div class="flex flex-col gap-6">
            <DiscordOAuthLink label="Register with Discord" intent="register" />

            <form @submit.prevent="submit" class="flex flex-col gap-4">
                <InputError :message="pageErrors.email || form.errors.email" />

                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        placeholder="Full name"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        placeholder="Password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        placeholder="Confirm password"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="w-full" :tabindex="5" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Create account
                </Button>
            </form>

            <p class="text-center text-sm text-muted-foreground">
                Already have an account?
                <Link :href="route('login')" :class="authLinkClass" :tabindex="6">Log in</Link>
            </p>
        </div>
    </MarketingAuthShell>
</template>
