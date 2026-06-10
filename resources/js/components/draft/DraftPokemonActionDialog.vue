<script setup lang="ts">
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertTriangle, Heart, LoaderCircle } from 'lucide-vue-next';
import { watch } from 'vue';

export interface DraftActionDialogPokemon {
    id: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
    cost: number;
    banned: number | boolean;
    is_drafted: number | boolean;
    drafted_by_team_id: number | null;
    drafted_by_team_name: string | null;
}

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    selectedPokemon: DraftActionDialogPokemon | null;
    pickError: string | null;
    isBanPhase: boolean;
    isPreDraft: boolean;
    canConfirmBanOrPick: boolean;
    canToggleWishlist: boolean;
    selectedIsOnWishlist: boolean;
    isSubmitting: boolean;
    isTogglingWishlist: boolean;
}>();

const emit = defineEmits<{
    cancel: [];
    toggleWishlist: [];
    submit: [];
}>();

watch(open, (isShown, wasShown) => {
    if (wasShown && !isShown) {
        emit('cancel');
    }
});

const onCancelClick = () => {
    if (props.isSubmitting || props.isTogglingWishlist) {
        return;
    }
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{
                        props.canConfirmBanOrPick
                            ? props.isBanPhase
                                ? 'Confirm Ban'
                                : 'Confirm Pick'
                            : props.isPreDraft
                              ? 'Wishlist'
                              : 'Manage Pokémon'
                    }}
                </DialogTitle>
                <DialogDescription>
                    <template v-if="props.canConfirmBanOrPick">
                        {{
                            props.isBanPhase
                                ? 'Are you sure you want to ban this Pokémon? It will be unavailable for the rest of the draft.'
                                : 'Are you sure you want to draft this Pokémon?'
                        }}
                    </template>
                    <template v-else>
                        Add or remove this Pokémon from your wishlist. Ban and draft actions are available when it is your turn.
                    </template>
                </DialogDescription>
            </DialogHeader>
            <div v-if="props.pickError" class="flex items-start gap-2 rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive dark:bg-destructive/20">
                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                <span>{{ props.pickError }}</span>
            </div>
            <div v-if="props.selectedPokemon" class="flex flex-col items-center gap-4 py-4">
                <div class="w-48">
                    <PokemonCard :pokemon="props.selectedPokemon" />
                </div>
                <div class="text-center">
                    <p class="text-lg font-semibold capitalize">{{ props.selectedPokemon.name }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Cost: {{ props.selectedPokemon.cost }}</p>
                </div>
            </div>
            <DialogFooter class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                <Button variant="outline" :disabled="props.isSubmitting || props.isTogglingWishlist" @click="onCancelClick">Cancel</Button>
                <Button
                    v-if="props.canToggleWishlist"
                    type="button"
                    variant="secondary"
                    :disabled="props.isTogglingWishlist || props.isSubmitting"
                    @click="emit('toggleWishlist')"
                >
                    <LoaderCircle v-if="props.isTogglingWishlist" class="mr-2 size-4 animate-spin" />
                    <Heart v-else class="mr-2 size-4" :class="props.selectedIsOnWishlist ? 'fill-red-500 text-red-500' : ''" />
                    {{ props.selectedIsOnWishlist ? 'Remove from wishlist' : 'Add to wishlist' }}
                </Button>
                <Button
                    type="button"
                    :disabled="props.isSubmitting || !props.canConfirmBanOrPick"
                    :variant="props.isBanPhase ? 'destructive' : 'default'"
                    @click="emit('submit')"
                >
                    <LoaderCircle v-if="props.isSubmitting" class="mr-2 size-4 animate-spin" />
                    {{ props.isBanPhase ? 'Ban Pokémon' : 'Draft Pokémon' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
