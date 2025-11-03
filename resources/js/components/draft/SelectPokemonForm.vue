<script setup lang="ts">
import {Button} from '@/components/ui/button';
import { ref } from 'vue';
import { ChevronsUpDownIcon } from 'lucide-vue-next';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import {router} from '@inertiajs/vue3';


interface Pokemon {
    id: number;
    name: string;
    league: Array<{
        pivot: {
            cost: number;
        };
    }>;
}

interface League {
    id: number;
    name: string;
}
const isOpen = ref(false);
const selectedPokemon = ref<Pokemon | null>(null);

const props = defineProps<{
    pokemon: Pokemon[];
    league: League;
}>();


const submit = () => {
    router.post(route('draft.pick'), {
        pokemon_id: selectedPokemon.value?.id,
        pokemon_name: selectedPokemon.value?.name,
        pokemon_cost: selectedPokemon.value?.league[0].pivot.cost,
        league_id: props.league.id,
    });
}

function onSelect(pokemon: Pokemon) {
    selectedPokemon.value = pokemon;
    isOpen.value = false;
}
</script>

<template>
<div class="flex flex-col" >
    <Popover v-model:open="isOpen">
        <PopoverTrigger>
            <Button
                variant="outline"
                role="combobox"
                class="w-[200px] justify-between capitalize"
                :class="{ 'text-muted-foreground': !selectedPokemon }"
              >
                <template v-if="selectedPokemon">
                    {{ selectedPokemon.name }} Cost:{{ selectedPokemon.league[0].pivot.cost }} <ChevronsUpDownIcon class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </template>
                <template v-else>
                    Select a Pokemon <ChevronsUpDownIcon class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </template>
            </Button>
        </PopoverTrigger>
        <PopoverContent>
            <Command>
                <CommandInput placeholder="Search Pokemon" />
                <CommandList>
                    <CommandEmpty>No Pokemon found</CommandEmpty>
                    <CommandGroup heading="Pokemon">
                        <CommandItem v-for="pokemon in pokemon" :key="pokemon.id" :value="pokemon.id" @select="onSelect(pokemon)" class="capitalize">
                            {{ pokemon.name }} Cost:{{ pokemon.league[0].pivot.cost }}
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</div>
<div class="flex justify-center mt-5">
    <Button type="submit" @click="submit" v-if="selectedPokemon">Submit</Button>
</div>
</template>