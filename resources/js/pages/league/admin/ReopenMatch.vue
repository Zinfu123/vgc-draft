<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/league/AdminLayout.vue';
import { type AppPageProps, type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface League {
    id: number;
    name: string;
    logo?: string;
}

const props = defineProps<{
    league: League;
}>();

const page = usePage<AppPageProps>();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leagues', href: '/leagues' },
    { title: props.league.name, href: `/leagues/${props.league.id}` },
    { title: 'Admin', href: '#' },
];

const reopenForm = useForm({
    match_reference: '',
});

const handleSubmit = () => {
    reopenForm.post(route('leagues.admin.reopen-match.store', { league: props.league.id }), {
        preserveScroll: true,
        onSuccess: () => {
            reopenForm.reset('match_reference');
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${props.league.name} — Admin`" />

        <AdminLayout :league-id="props.league.id" :league-name="props.league.name">
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Reopen a match"
                    description="Paste a full match URL or the numeric set ID to reverse the result, restore standings, and let coaches submit again. Only completed matches in this league can be reopened."
                />

                <p v-if="flashSuccess" class="text-sm font-medium text-green-700 dark:text-green-400">
                    {{ flashSuccess }}
                </p>

                <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium" for="match_reference">Match link or set ID</label>
                        <textarea
                            id="match_reference"
                            v-model="reopenForm.match_reference"
                            rows="3"
                            placeholder="e.g. https://yoursite.test/match/set/12 or 12"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none dark:bg-background"
                        />
                        <p v-if="reopenForm.errors.match_reference" class="text-sm text-destructive">
                            {{ reopenForm.errors.match_reference }}
                        </p>
                        <p v-if="reopenForm.errors.set_id" class="text-sm text-destructive">{{ reopenForm.errors.set_id }}</p>
                    </div>
                    <div class="flex justify-end pt-2">
                        <Button type="submit" variant="destructive" :disabled="reopenForm.processing || reopenForm.match_reference.trim() === ''">
                            Reopen match
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    </AppLayout>
</template>
