<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-vue-next';
import { useForm} from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';

const form = useForm({
    name: '',
    draft_date: '',
    set_start_date: '',
    set_frequency: '',
    logo: null as File | null,
})


const submit = () => {
    form.post(route('leagues.create'), {
        forceFormData: true,
    });
}

</script>

<template>
    <div class="flex justify-end mr-4 mt-6">
        <Dialog>
            <DialogTrigger>
                <Button variant="outline" >Create League <Plus /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create League</DialogTitle>
                </DialogHeader>
                <DialogDescription>
                    Create a new league for your friends to join.
                </DialogDescription>
                <form @submit.prevent="submit">
                    <div class="grid gap-4 w-full max-w-sm items-center justify-center">
                    <label for="name">League Name</label>
                    <Input type="text" name="name" v-model="form.name" placeholder="League Name" />
                    <div class="grid  w-full max-w-sm items-center gap-3">
                    <label for="draft_date">Draft Date</label>
                    <Input type="date" name="draft_date" v-model="form.draft_date" class="w-[150px]" />
                    <label for="set_start_date">Set Start Date</label>
                    <Input type="date" name="set_start_date" v-model="form.set_start_date" class="w-[150px]" />
                    </div>
                    <label for="set_frequency">Set Frequency</label>
                    <Input type="number" name="set_frequency" v-model="form.set_frequency"  />
                    <label for="logo">Logo</label>
                    <div class="grid w-full max-w-sm items-center gap-3">
                    <Input type="file" name="logo" @input="form.logo = ($event.target as HTMLInputElement)?.files?.[0] || null"/>
                    </div>
                    <Button type="submit" class="w-1/3" variant="outline">Create</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>