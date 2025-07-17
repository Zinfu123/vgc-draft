<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm(
    {
        name: '',
        draft_date: '',
        set_date: '',
        set_frequency: '',
        logo: null as File | null,
    }
);



function submit() {
    form.post(route('leagues.create'), {
        preserveScroll: true,
    });
}

</script>

<template>
    <div>
        <form @submit.prevent="submit">
            <input type="text" name="name" v-model="form.name" />
            <input type="text" name="draft_date" v-model="form.draft_date" />
            <input type="text" name="set_date" v-model="form.set_date" />
            <input type="text" name="set_frequency" v-model="form.set_frequency" />
            <input type="file" name="logo" @input="form.logo = ($event.target as HTMLInputElement)?.files?.[0] || null" />
        </form>
        <button type="submit">Submit</button>
    </div>
</template>