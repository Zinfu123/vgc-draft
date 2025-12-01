<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { router, useForm } from '@inertiajs/vue3';

interface MatchConfig {
    id: number;
    league_id: number;
    number_of_pools: number;
    frequency_type: number;
    frequency_value: number;
    status: number;
}
interface League {
    id: number;
    name: string;
}

const props = defineProps<{
    league: League;
    matchConfig: MatchConfig;
}>();

const buttonText = props.matchConfig.id > 0 ? 'Edit Match Config' : 'Create Match Config';

const createEdit = () => {
    router.get(route('leagues.match-config.show', { league: props.league.id }), {
        league_id: props.league.id,
        command: 'show',
    });
};

const command = props.matchConfig.id > 0 ? 'update' : 'create';

const form = useForm('CreateEditMatchConfigForm', {
    league_id: props.league.id,
    number_of_pools: props.matchConfig.number_of_pools,
    frequency_type: props.matchConfig.frequency_type,
    frequency_value: props.matchConfig.frequency_value,
    command: command,
});

const handleSubmit = () => {
    form.post(route('leagues.match-config.create-edit-show', { league: props.league.id, forceFormData: true }));
};
</script>

<template>
    <Dialog>
        <DialogTrigger asChild>
            <Button variant="outline" @click="createEdit">
                {{ buttonText }}
            </Button>
        </DialogTrigger>
        <DialogContent>
            <form @submit.prevent="handleSubmit">
                <div class="grid w-full max-w-sm items-center justify-center gap-4">
                    <label for="number_of_pools">Number of Pools</label>
                    <Input type="number" name="number_of_pools" input="form.number_of_pools" v-model="form.number_of_pools" />
                    <label for="frequency_type">Frequency Type</label>
                    <select name="frequency_type" input="form.frequency_type" v-model="form.frequency_type" class="bg-background">
                        <option value="1">Daily</option>
                        <option value="2">Weekly</option>
                        <option value="3">Single Day</option>
                        <option value="4">Custom</option>
                    </select>
                    <label v-if="form.frequency_type === 4" for="frequency_value">Frequency Value</label>
                    <Input
                        v-if="form.frequency_type === 4"
                        type="number"
                        name="frequency_value"
                        input="form.frequency_value"
                        v-model="form.frequency_value"
                    />
                    <DialogFooter>
                        <DialogClose as-child>
                            <Button type="submit" class="w-1/3" variant="outline">{{ command === 'create' ? 'Create' : 'Update' }}</Button>
                        </DialogClose>
                    </DialogFooter>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
