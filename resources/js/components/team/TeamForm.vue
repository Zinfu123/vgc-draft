<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogHeader, DialogScrollContent, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Loader2, Plus } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface UserTeam {
    id: number;
    name: string;
    coach: string;
    logo: string | null;
    showdown_username?: string | null;
}

interface props {
    league_id: number;
    command: string;
    user_team: UserTeam | null;
    initialOpen?: boolean;
}

const props = defineProps<props>();

const dialogOpen = ref(props.initialOpen ?? false);

const page = usePage();
const auth = page.props.auth.user as { showdown_username?: string | null };

function initialShowdownUsername(): string {
    if (props.command === 'edit' && props.user_team) {
        const teamVal = props.user_team.showdown_username;
        if (typeof teamVal === 'string' && teamVal.trim() !== '') {
            return teamVal;
        }

        return auth.showdown_username?.trim() ? auth.showdown_username : '';
    }

    return auth.showdown_username?.trim() ? auth.showdown_username : '';
}

const form = useForm({
    id: props.user_team?.id || null,
    name: props.user_team?.name || '',
    league_id: props.league_id,
    user_id: page.props.auth.user.id,
    logo: null as File | null,
    command: props.command,
    showdown_username: initialShowdownUsername(),
});

watch(
    () => props.user_team,
    (newTeam) => {
        if (newTeam) {
            form.name = newTeam.name || '';
            const teamVal = newTeam.showdown_username;
            if (typeof teamVal === 'string' && teamVal.trim() !== '') {
                form.showdown_username = teamVal;
            } else {
                form.showdown_username = auth.showdown_username?.trim() ? auth.showdown_username : '';
            }
        } else {
            form.name = '';
            form.logo = null;
            form.showdown_username = auth.showdown_username?.trim() ? auth.showdown_username : '';
        }
    },
    { immediate: true },
);

const submit = () => {
    if (props.command === 'create') {
        form.post(route('teams.create'), {
            forceFormData: true,
            onSuccess: () => {
                dialogOpen.value = false;
                router.reload();
            },
        });
    } else {
        form.post(route('teams.edit', { team_id: props.user_team?.id }), {
            forceFormData: true,
            onSuccess: () => {
                dialogOpen.value = false;
                router.reload();
            },
        });
    }
};
</script>

<template>
    <div class="flex flex-col gap-4 md:items-center md:justify-between">
        <div class="mt-4 mr-14 flex w-full flex-col items-end justify-end">
            <Dialog v-model:open="dialogOpen">
                <DialogTrigger>
                    <Button variant="outline">{{ command === 'create' ? 'Create Team' : 'Edit Team' }} <Plus /></Button>
                </DialogTrigger>
                <DialogScrollContent class="overflow-hidden">
                    <Transition
                        enter-active-class="transition-opacity duration-150"
                        enter-from-class="opacity-0"
                        leave-active-class="transition-opacity duration-150"
                        leave-to-class="opacity-0"
                    >
                        <div
                            v-if="form.processing && command === 'create'"
                            class="absolute inset-0 z-10 flex cursor-wait flex-col items-center justify-center gap-3 rounded-lg bg-background/90 backdrop-blur-sm"
                        >
                            <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                            <p class="text-sm font-medium text-foreground">Your team is being created&hellip;</p>
                        </div>
                    </Transition>
                    <DialogHeader>
                        <DialogTitle>{{ command === 'create' ? 'Create Team' : 'Edit Team' }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription>
                        {{
                            command === 'create'
                                ? 'Team name, optional logo, and a Pokémon Showdown username (yours on file, or a team-only name).'
                                : 'Update your team. You need a Showdown name on your profile and/or here for matches and replays.'
                        }}
                    </DialogDescription>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid w-full max-w-sm items-center justify-center gap-4">
                            <div class="grid gap-2">
                                <Label for="team-name-input">Team name</Label>
                                <Input id="team-name-input" v-model="form.name" type="text" name="name" placeholder="Team name" maxlength="30" />
                                <InputError :message="form.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="team-showdown-input">Pokémon Showdown username</Label>
                                <Input
                                    id="team-showdown-input"
                                    v-model="form.showdown_username"
                                    type="text"
                                    name="showdown_username"
                                    autocomplete="off"
                                    placeholder="Matches Showdown battles / replays"
                                    maxlength="18"
                                />
                                <InputError :message="form.errors.showdown_username" />
                                <p class="text-muted-foreground text-xs">
                                    Pre-filled from your
                                    <span class="text-foreground">Profile</span>
                                    when set. Use this field for a team-only name, or add it in Profile instead.
                                </p>
                            </div>
                            <Label for="team-logo-input">Logo</Label>
                            <div v-if="user_team?.logo" class="mb-2">
                                <p class="mb-2 text-sm text-muted-foreground">Current logo:</p>
                                <img :src="user_team.logo" :alt="user_team.name + ' logo'" class="h-16 w-16 object-contain" />
                            </div>
                            <Input
                                id="team-logo-input"
                                type="file"
                                name="logo"
                                @input="form.logo = ($event.target as HTMLInputElement)?.files?.[0] || null"
                                accept="image/*"
                            />
                            <InputError :message="form.errors.logo" />
                        </div>
                        <Button type="submit" class="w-1/3" variant="outline">{{ command === 'create' ? 'Create' : 'Save' }}</Button>
                    </form>
                </DialogScrollContent>
            </Dialog>
        </div>
    </div>
</template>
