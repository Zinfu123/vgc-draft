<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { router } from '@inertiajs/vue3';
import { ChevronsUpDownIcon, LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

interface Pokemon {
    id: number;
    name: string;
    cost: number;
}

interface League {
    id: number;
    name: string;
}
const isOpen = ref(false);
const selectedPokemon = ref<Pokemon | null>(null);
const isSubmitting = ref(false);

const props = defineProps<{
    pokemon: Pokemon[];
    league: League;
}>();

const submit = () => {
    if (isSubmitting.value || !selectedPokemon.value) return;

    isSubmitting.value = true;
    router.post(
        route('draft.pick'),
        {
            pokemon_id: selectedPokemon.value?.id,
            pokemon_name: selectedPokemon.value?.name,
            pokemon_cost: selectedPokemon.value?.cost,
            league_id: props.league.id,
        },
        {
            onSuccess: () => {
                selectedPokemon.value = null;
                isSubmitting.value = false;
                router.reload();
            },
            onError: () => {
                isSubmitting.value = false;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

function onSelect(pokemon: Pokemon) {
    selectedPokemon.value = pokemon;
    isOpen.value = false;
}
</script>

<template>
    <div class="flex flex-col">
        <Popover v-model:open="isOpen">
            <PopoverTrigger>
                <Button
                    variant="outline"
                    role="combobox"
                    class="w-[200px] justify-between capitalize"
                    :class="{ 'text-muted-foreground': !selectedPokemon }"
                >
                    <template v-if="selectedPokemon">
                        {{ selectedPokemon.name }} Cost:{{ selectedPokemon.cost }} <ChevronsUpDownIcon class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </template>
                    <template v-else> Select a Pokemon <ChevronsUpDownIcon class="ml-2 h-4 w-4 shrink-0 opacity-50" /> </template>
                </Button>
            </PopoverTrigger>
            <PopoverContent>
                <Command>
                    <CommandInput placeholder="Search Pokemon" />
                    <CommandList>
                        <CommandEmpty>No Pokemon found</CommandEmpty>
                        <CommandGroup heading="Pokemon">
                            <CommandItem
                                v-for="pokemon in pokemon"
                                :key="pokemon.id"
                                :value="pokemon.id"
                                @select="onSelect(pokemon)"
                                class="capitalize"
                            >
                                {{ pokemon.name }} Cost:{{ pokemon.cost }}
                            </CommandItem>
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    </div>
    <div class="mt-5 flex justify-center">
        <Button type="submit" @click="submit" v-if="selectedPokemon" :disabled="isSubmitting">
            <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
            Submit
        </Button>
    </div>
</template>
