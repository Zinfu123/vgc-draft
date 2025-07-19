<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-vue-next';
import { useForm} from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { usePage } from '@inertiajs/vue3';


interface props {
    league_id: number;
}

const props = defineProps<props>();

const form = useForm({
    name: '',
    league_id: props.league_id,
    user_id: usePage().props.auth.user.id,
    logo: null as File | null,
})


const submit = () => {
    form.post(route('teams.create'), {
        forceFormData: true,
    });
}

</script>

<template>
    <div class="flex justify-end mr-4 mt-6">
        <Dialog>
            <DialogTrigger>
                <Button variant="outline" >Create Team <Plus /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create Team</DialogTitle>
                </DialogHeader>
                <DialogDescription>
                    Create a new team for your friends to join.
                </DialogDescription>
                <form @submit.prevent="submit">
                    <div class="grid gap-4 w-full max-w-sm items-center justify-center">
                    <label for="name">Team Name</label>
                    <Input type="text" name="name" v-model="form.name" placeholder="Team Name" />
                    <label for="logo">Logo</label>
                    <Input type="file" name="logo" @input="form.logo = ($event.target as HTMLInputElement)?.files?.[0] || null"/>
                    </div>
                    <Button type="submit" class="w-1/3" variant="outline">Create</Button>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>