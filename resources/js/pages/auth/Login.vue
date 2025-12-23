<script setup lang="ts">
import TournamentBracketBackground from '@/components/auth/TournamentBracketBackground.vue';
import PokeBallLoginForm from '@/components/auth/PokeBallLoginForm.vue';
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
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-blue-900 to-cyan-800 relative overflow-hidden">
        <Head title="Log in" />

        <TournamentBracketBackground />

        <PokeBallLoginForm
            :status="status"
            :can-reset-password="canResetPassword"
            :form="form"
            :on-submit="submit"
        />
    </div>
</template>
