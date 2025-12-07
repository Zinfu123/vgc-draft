<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useForm, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { watch } from 'vue';

interface UserTeam {
    id: number;
    name: string;
    coach: string;
    logo: string;
}

interface props {
    league_id: number;
    user_id: number;
    command: string;
    user_team: UserTeam | null;
}

const props = defineProps<props>();

const form = useForm({
    name: props.user_team?.name || '',
    league_id: props.league_id,
    user_id: usePage().props.auth.user.id,
    logo: null as File | null,
    command: props.command,
});

// Watch for changes in user_team prop and update form
watch(
    () => props.user_team,
    (newTeam) => {
        if (newTeam) {
            form.name = newTeam.name || '';
            // Note: logo is a URL string, not a File, so we don't set it here
            // The file input will handle new file uploads
        } else {
            form.name = '';
            form.logo = null;
        }
    },
    { immediate: true },
);

const submit = () => {
    if (props.command === 'create') {
        form.post(route('teams.create'), {
            forceFormData: true,
        });
    } else {
        form.post(route('teams.edit'), {
            forceFormData: true,
        });
    }
};
</script>

<template>
    <div class="mt-6 mr-4 flex justify-end">
        <Dialog>
            <DialogTrigger>
                <Button variant="outline">{{ command === 'create' ? 'Create Team' : 'Edit Team' }} <Plus /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ command === 'create' ? 'Create Team' : 'Edit Team' }}</DialogTitle>
                </DialogHeader>
                <DialogDescription>{{ command === 'create' ? 'Create a new team' : 'Edit your team.' }}</DialogDescription>
                <form @submit.prevent="submit">
                    <div class="grid w-full max-w-sm items-center justify-center gap-4">
                        <label for="name">Team Name</label>
                        <Input type="text" name="name" v-model="form.name" placeholder="Team Name" />
                        <label for="logo">Logo</label>
                        <div v-if="user_team?.logo" class="mb-2">
                            <p class="mb-2 text-sm text-muted-foreground">Current logo:</p>
                            <img :src="user_team.logo" :alt="user_team.name + ' logo'" class="h-16 w-16 object-contain" />
                        </div>
                        <Input
                            type="file"
                            name="logo"
                            @input="form.logo = ($event.target as HTMLInputElement)?.files?.[0] || null"
                            accept="image/*"
                        />
                    </div>
                    <Button type="submit" class="w-1/3" variant="outline">{{ command === 'create' ? 'Create' : 'Edit' }}</Button>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
