<script setup lang="ts">
import PokeBallLoginForm from '@/components/auth/PokeBallLoginForm.vue';
import TournamentBracketBackground from '@/components/auth/TournamentBracketBackground.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-blue-900 to-cyan-800">
        <Head title="Log in" />

        <TournamentBracketBackground />

        <PokeBallLoginForm :status="status" :can-reset-password="canResetPassword" :form="form" :on-submit="submit" />
    </div>
</template>
