<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/vue3';

interface League {
    id: number;
    name: string;
    league_owner: number;
}

const props = defineProps<{
    league: League;
}>();

const form = useForm({
    csv_file: null as File | null,
    league_id: props.league.id,
});

const submit = () => {
    form.post(route('leagues.pokemon.create'), {
        forceFormData: true,
    });
};
</script>

<template>
    <Dialog>
        <DialogTrigger asChild>
            <Button variant="outline"> Import Pokemon CSV </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Import Pokemon</DialogTitle>
                <DialogDescription> Upload a CSV file with Pokemon IDs and costs. Format: pokedex_id,cost (e.g., "1,500") </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <Input type="file" name="csv_file" @input="form.csv_file = ($event.target as HTMLInputElement)?.files?.[0] || null" />
                <Button @click="submit"> Upload and Import </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
