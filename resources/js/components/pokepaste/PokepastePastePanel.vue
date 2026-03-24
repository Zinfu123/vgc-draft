<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { PokepasteSlot } from '@/lib/pokepaste/showdownExport';
import { computed, ref } from 'vue';

const props = defineProps<{
    pokepastePublicId: string;
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    applied: [slots: PokepasteSlot[]];
}>();

const text = computed({
    get: () => props.modelValue,
    set: (v: string) => emit('update:modelValue', v),
});

const loading = ref(false);
const errors = ref<string[]>([]);
const copied = ref(false);

function readXsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function parsePaste(): Promise<void> {
    errors.value = [];
    loading.value = true;
    try {
        const res = await fetch(route('pokepaste.parse', { pokepaste: props.pokepastePublicId }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': readXsrfToken(),
            },
            body: JSON.stringify({ paste: props.modelValue }),
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            if (Array.isArray(data.errors)) {
                errors.value = data.errors.map(String);
            } else if (data.errors && typeof data.errors === 'object') {
                errors.value = Object.entries(data.errors).flatMap(([k, v]) =>
                    Array.isArray(v) ? v.map((msg) => `${k}: ${msg}`) : [`${k}: ${v}`],
                );
            } else {
                errors.value = ['Could not parse paste.'];
            }
            return;
        }

        if (data.ok && Array.isArray(data.slots)) {
            emit('applied', data.slots as PokepasteSlot[]);
            return;
        }

        errors.value = ['Unexpected response from server.'];
    } finally {
        loading.value = false;
    }
}

async function copy(): Promise<void> {
    if (!props.modelValue.trim()) {
        return;
    }
    try {
        await navigator.clipboard.writeText(props.modelValue);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        copied.value = false;
    }
}

const textareaClass = cn(
    'border-input placeholder:text-muted-foreground text-muted-foreground min-h-[220px] w-full flex-1 resize-y rounded-md border bg-muted/30 px-3 py-2 font-mono text-xs whitespace-pre-wrap shadow-xs',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none',
    'dark:bg-muted/20',
);
</script>

<template>
    <div class="border-border flex min-h-0 flex-col space-y-3 rounded-lg border p-4">
        <div>
            <Label for="pokepaste-showdown-field">Showdown team text</Label>
            <p class="text-muted-foreground mt-1 text-xs">
                Reflects your slots below; paste a 6-Pokémon Showdown export and use <strong>Parse into slots</strong>, or copy to
                <a href="https://pokepast.es/" class="text-primary underline" target="_blank" rel="noopener noreferrer">pokepast.es</a>.
            </p>
        </div>
        <textarea
            id="pokepaste-showdown-field"
            v-model="text"
            :class="textareaClass"
            placeholder="Paste a Showdown team here or build your team in the slots below…"
            aria-label="Showdown team text"
        />
        <div v-if="errors.length" class="text-destructive space-y-1 text-sm">
            <p v-for="(e, i) in errors" :key="i">{{ e }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <Button type="button" size="sm" :disabled="loading" @click="parsePaste">
                {{ loading ? 'Parsing…' : 'Parse into slots' }}
            </Button>
            <Button type="button" size="sm" variant="secondary" :disabled="!modelValue.trim()" @click="copy">
                {{ copied ? 'Copied' : 'Copy' }}
            </Button>
        </div>
    </div>
</template>
