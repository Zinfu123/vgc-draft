<script setup lang="ts">
import AuthAlert from '@/components/auth/AuthAlert.vue';
import MarketingAuthShell from '@/components/auth/MarketingAuthShell.vue';
import PasswordInput from '@/components/auth/PasswordInput.vue';
import InputError from '@/components/InputError.vue';
import { authLinkClass } from '@/lib/authLink';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    status?: string;
}>();

const page = usePage();
const pageErrors = computed(() => page.props.errors as Record<string, string | undefined>);

const form = useForm({
    link_email: '',
    link_password: '',
});

const submit = () => {
    form.post(route('discord.prepare-link'), {
        onFinish: () => form.reset('link_password'),
    });
};
</script>

<template>
    <Head title="Link existing account to Discord" />

    <MarketingAuthShell
        title="Link existing account"
        description="Enter your account credentials to verify ownership, then authorize Discord — we'll connect them and sign you in."
    >
        <AuthAlert v-if="status" :message="status" class="mb-4" />

        <div class="flex flex-col gap-6">
            <form @submit.prevent="submit" class="flex flex-col gap-4">
                <InputError :message="pageErrors.link_email || form.errors.link_email" />
                <InputError :message="pageErrors.link_password || form.errors.link_password" />

                <div class="grid gap-2">
                    <Label for="link_email">Account email</Label>
                    <Input
                        id="link_email"
                        v-model="form.link_email"
                        type="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="you@example.com"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="link_password">Password</Label>
                    <PasswordInput
                        id="link_password"
                        v-model="form.link_password"
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Your password"
                        required
                    />
                </div>

                <Button type="submit" class="w-full" :tabindex="3" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Continue to Discord
                </Button>
            </form>

            <p class="text-center text-sm text-muted-foreground">
                <Link :href="route('login')" :class="authLinkClass" :tabindex="4">Back to login</Link>
            </p>
        </div>
    </MarketingAuthShell>
</template>
