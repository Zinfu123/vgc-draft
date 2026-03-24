<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

interface VersionGroupRef {
    id: number;
    name: string;
    slug: string;
    generation: number;
}

interface TemplateCard {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    version_group: {
        id: number;
        name: string;
        slug: string;
        generation: number;
    } | null;
}

const props = defineProps<{
    templatesByGeneration: Record<string, Record<string, TemplateCard[]>>;
    versionGroups: VersionGroupRef[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pool templates', href: '/pool-templates' },
];

const previewSlug = ref<string | null>(null);
const previewLoading = ref(false);
const previewJson = ref<string | null>(null);
const previewError = ref<string | null>(null);

function openPreview(slug: string): void {
    previewSlug.value = slug;
    previewLoading.value = true;
    previewJson.value = null;
    previewError.value = null;
    fetch(route('pool-templates.preview', { slug }))
        .then(async (r) => {
            if (!r.ok) {
                previewError.value = `Preview failed (${r.status})`;
                return;
            }
            const data = await r.json();
            previewJson.value = JSON.stringify(data, null, 2);
        })
        .catch(() => {
            previewError.value = 'Could not load preview.';
        })
        .finally(() => {
            previewLoading.value = false;
        });
}

function closePreview(): void {
    previewSlug.value = null;
    previewJson.value = null;
    previewError.value = null;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pool templates" />
        <div class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <h1 class="mb-2 text-2xl font-bold">Published pool templates</h1>
            <p class="text-muted-foreground mb-8 text-sm">
                Templates are grouped by generation and game version. Preview loads JSON (species + cost) for the selected template.
            </p>

            <div v-if="Object.keys(templatesByGeneration).length === 0" class="text-muted-foreground text-sm">No published templates yet.</div>

            <div v-else class="flex flex-col gap-10">
                <section v-for="(groups, gen) in templatesByGeneration" :key="gen">
                    <h2 class="mb-4 text-lg font-semibold">Generation {{ gen }}</h2>
                    <div class="flex flex-col gap-6">
                        <div v-for="(templates, vgName) in groups" :key="String(vgName)">
                            <h3 class="text-muted-foreground mb-2 text-sm font-medium">{{ vgName }}</h3>
                            <ul class="flex flex-col gap-3">
                                <li
                                    v-for="t in templates"
                                    :key="t.id"
                                    class="flex flex-col gap-2 rounded-lg border border-border bg-card p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p class="font-medium">{{ t.name }}</p>
                                        <p v-if="t.description" class="text-muted-foreground mt-1 text-sm">{{ t.description }}</p>
                                        <p class="text-muted-foreground mt-1 text-xs">Slug: {{ t.slug }}</p>
                                    </div>
                                    <div class="flex gap-2">
                                        <Button type="button" variant="outline" size="sm" @click="openPreview(t.slug)">Preview JSON</Button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div
            v-if="previewSlug !== null"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            role="dialog"
            aria-modal="true"
            @click.self="closePreview"
        >
            <div class="max-h-[85vh] w-full max-w-3xl overflow-hidden rounded-lg border border-border bg-background shadow-lg">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <p class="text-sm font-semibold">Preview: {{ previewSlug }}</p>
                    <Button type="button" variant="ghost" size="sm" @click="closePreview">Close</Button>
                </div>
                <div class="max-h-[calc(85vh-3rem)] overflow-auto p-4">
                    <p v-if="previewLoading" class="text-muted-foreground text-sm">Loading…</p>
                    <p v-else-if="previewError" class="text-destructive text-sm">{{ previewError }}</p>
                    <pre v-else-if="previewJson" class="text-xs whitespace-pre-wrap">{{ previewJson }}</pre>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
