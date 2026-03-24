<script setup lang="ts">
import DiscordOAuthLink from '@/components/auth/DiscordOAuthLink.vue';
import MarketingAuthShell from '@/components/auth/MarketingAuthShell.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

const showPassword = ref(false);
const showLinkPassword = ref(false);

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

const authLinkClass =
    'text-sm font-medium text-[oklch(0.48_0.19_25)] underline decoration-[oklch(0.53_0.195_25/0.4)] underline-offset-4 transition-colors hover:text-[oklch(0.4_0.17_25)] dark:text-primary dark:decoration-primary/45 dark:hover:text-primary/90';
</script>

<template>
    <Head title="Log in" />

    <MarketingAuthShell
        title="Log in"
        description="Welcome back. Use Discord or your email to access your leagues and drafts."
    >
        <div v-if="status" class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            {{ status }}
        </div>

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
                    <div class="relative">
                        <Input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            class="pr-10"
                            placeholder="Password"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                            tabindex="-1"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOff v-if="showPassword" class="h-4 w-4" />
                            <Eye v-else class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
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
                        <div class="relative">
                            <Input
                                id="link_password"
                                v-model="linkForm.link_password"
                                :type="showLinkPassword ? 'text' : 'password'"
                                required
                                :tabindex="8"
                                autocomplete="current-password"
                                class="pr-10"
                                placeholder="Your password"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                                tabindex="-1"
                                @click="showLinkPassword = !showLinkPassword"
                            >
                                <EyeOff v-if="showLinkPassword" class="h-4 w-4" />
                                <Eye v-else class="h-4 w-4" />
                            </button>
                        </div>
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
