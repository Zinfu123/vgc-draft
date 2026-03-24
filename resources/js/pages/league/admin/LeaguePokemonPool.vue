<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/league/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

interface League {
    id: number;
    name: string;
}

interface TemplateVersionGroup {
    id: number;
    slug: string;
    name: string;
    generation: number;
}

interface TemplateSummary {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    rows_count: number;
    version_group: TemplateVersionGroup | null;
}

interface PoolRow {
    id: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
    cost: number;
    banned: boolean;
    is_drafted: number;
    drafted_by_team_name?: string;
}

interface PoolReplaceInfo {
    has_pool: boolean;
    allowed: boolean;
    blocked_reason: string | null;
}

interface PreviewRow {
    id: number;
    cost: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
    nationaldex_id: number;
}

interface PaginatedPreview {
    data: PreviewRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface PokedexRow {
    id: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
    nationaldex_id: number;
}

interface PaginatedPokedex {
    data: PokedexRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    league: League;
    templates: TemplateSummary[];
    pool: PoolRow[];
    poolReplace: PoolReplaceInfo;
    pokedexTypeOptions: string[];
    pokedexGenerationOptions: number[];
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props.flash as { success?: string } | undefined)?.success);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leagues', href: '/leagues' },
    { title: props.league.name, href: `/leagues/${props.league.id}` },
    { title: 'Admin', href: '#' },
];

function xsrfToken(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return m ? decodeURIComponent(m[1]) : '';
}

const previewOpen = ref(false);
const previewTemplate = ref<TemplateSummary | null>(null);
const previewPage = ref(1);
const previewLoading = ref(false);
const previewData = ref<PaginatedPreview | null>(null);

const applyDialogOpen = ref(false);
const templateToApply = ref<TemplateSummary | null>(null);
const applyConfirmChecked = ref(false);

const applyForm = useForm({
    confirm_replace: false,
});

const addDialogOpen = ref(false);
const addSearch = ref('');
const addType1 = ref('');
const addGeneration = ref<number | ''>('');
const addPerPage = 24;
const addPage = ref(1);
const addLoading = ref(false);
const addResults = ref<PaginatedPokedex | null>(null);
const addForm = useForm({
    pokedex_id: 0,
    cost: 10,
});

const csvForm = useForm({
    csv_file: null as File | null,
});

const costForms = ref<Record<number, { cost: number }>>({});
watch(
    () => props.pool,
    (rows) => {
        const next: Record<number, { cost: number }> = {};
        for (const r of rows) {
            next[r.id] = { cost: r.cost };
        }
        costForms.value = next;
    },
    { immediate: true },
);

async function loadPreview(pageNum: number): Promise<void> {
    if (!previewTemplate.value) {
        return;
    }
    previewLoading.value = true;
    try {
        const url =
            route('leagues.admin.pokemon-templates.preview', {
                league: props.league.id,
                template: previewTemplate.value.id,
            }) + `?page=${pageNum}&per_page=36`;
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
        });
        if (!res.ok) {
            previewData.value = null;

            return;
        }
        previewData.value = (await res.json()) as PaginatedPreview;
        previewPage.value = pageNum;
    } finally {
        previewLoading.value = false;
    }
}

function openPreview(t: TemplateSummary): void {
    previewTemplate.value = t;
    previewOpen.value = true;
    previewPage.value = 1;
    void loadPreview(1);
}

function openApplyDialog(t: TemplateSummary): void {
    templateToApply.value = t;
    applyConfirmChecked.value = false;
    applyDialogOpen.value = true;
}

function submitApply(): void {
    if (!templateToApply.value) {
        return;
    }
    const needsConfirm = props.poolReplace.has_pool && props.poolReplace.allowed;
    if (needsConfirm && !applyConfirmChecked.value) {
        return;
    }
    applyForm.confirm_replace = needsConfirm && applyConfirmChecked.value;
    applyForm.post(
        route('leagues.admin.pokemon-templates.apply', {
            league: props.league.id,
            template: templateToApply.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                applyDialogOpen.value = false;
                templateToApply.value = null;
                applyConfirmChecked.value = false;
            },
        },
    );
}

function patchCost(rowId: number): void {
    const entry = costForms.value[rowId];
    if (!entry) {
        return;
    }
    router.patch(
        route('leagues.admin.pokemon-pool.update', { league: props.league.id, leaguePokemon: rowId }),
        { cost: entry.cost },
        { preserveScroll: true },
    );
}

function removeRow(row: PoolRow): void {
    if (row.is_drafted) {
        return;
    }
    if (!confirm(`Remove ${row.name} from this league pool?`)) {
        return;
    }
    router.delete(route('leagues.admin.pokemon-pool.destroy', { league: props.league.id, leaguePokemon: row.id }), {
        preserveScroll: true,
    });
}

async function onAddFilterChange(): Promise<void> {
    await nextTick();
    await loadAddResults(1);
}

async function loadAddResults(pageNum: number): Promise<void> {
    addLoading.value = true;
    try {
        const params = new URLSearchParams({
            page: String(pageNum),
            per_page: String(addPerPage),
            exclude_in_pool: '1',
        });
        if (addSearch.value.trim()) {
            params.set('search', addSearch.value.trim());
        }
        if (addType1.value) {
            params.set('type1', addType1.value);
        }
        if (addGeneration.value !== '') {
            params.set('generation', String(addGeneration.value));
        }
        const url = route('leagues.admin.pokedex-search', { league: props.league.id }) + '?' + params.toString();
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
        });
        if (!res.ok) {
            addResults.value = null;

            return;
        }
        addResults.value = (await res.json()) as PaginatedPokedex;
        addPage.value = pageNum;
    } finally {
        addLoading.value = false;
    }
}

function openAddDialog(): void {
    addDialogOpen.value = true;
    addSearch.value = '';
    addType1.value = '';
    addGeneration.value = '';
    addPage.value = 1;
    void loadAddResults(1);
}

function submitAddPokemon(p: PokedexRow): void {
    addForm.pokedex_id = p.id;
    addForm.cost = addForm.cost > 0 ? addForm.cost : 10;
    addForm.post(route('leagues.admin.pokemon-pool.store', { league: props.league.id }), {
        preserveScroll: true,
        onSuccess: () => {
            void loadAddResults(addPage.value);
        },
    });
}

function submitCsv(): void {
    if (!csvForm.csv_file) {
        return;
    }
    csvForm.post(route('leagues.admin.pokemon-pool.import-csv', { league: props.league.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            csvForm.reset();
        },
    });
}

const templateApplyError = computed(() => {
    const errs = applyForm.errors as Record<string, string | undefined>;

    return errs.template ?? errs.confirm_replace;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${props.league.name} — Pokémon pool`" />

        <AdminLayout :league-id="props.league.id" :league-name="props.league.name">
            <div class="flex max-w-4xl flex-col space-y-8">
                <HeadingSmall
                    title="League Pokémon pool"
                    description="Apply a template, import CSV, search the Pokédex, and edit costs. Replacing the whole pool requires confirmation and is blocked once picks or trades reference pool rows."
                />

                <div
                    v-if="flashSuccess"
                    class="border-border bg-muted/40 text-foreground rounded-md border px-3 py-2 text-sm"
                    role="status"
                >
                    {{ flashSuccess }}
                </div>

                <section class="space-y-3">
                    <h2 class="text-lg font-semibold">Templates</h2>
                    <p v-if="props.templates.length === 0" class="text-muted-foreground text-sm">
                        No templates yet. Import one with
                        <code class="bg-muted rounded px-1 py-0.5 text-xs">php artisan league:pokemon-template-import</code>
                    </p>
                    <ul v-else class="space-y-2">
                        <li
                            v-for="t in props.templates"
                            :key="t.id"
                            class="border-border flex flex-col gap-2 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div class="font-medium">{{ t.name }}</div>
                                <div class="text-muted-foreground text-sm">
                                    {{ t.rows_count }} Pokémon · slug {{ t.slug }}
                                    <template v-if="t.version_group">
                                        · Gen {{ t.version_group.generation }} ({{ t.version_group.name }})
                                    </template>
                                    <template v-else> · Version group not set </template>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" variant="outline" size="sm" @click="openPreview(t)">Preview</Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    :disabled="props.poolReplace.has_pool && !props.poolReplace.allowed"
                                    @click="openApplyDialog(t)"
                                >
                                    {{ props.poolReplace.has_pool ? 'Swap template' : 'Apply template' }}
                                </Button>
                            </div>
                        </li>
                    </ul>
                    <p v-if="props.poolReplace.has_pool && !props.poolReplace.allowed" class="text-destructive text-sm">
                        {{ props.poolReplace.blocked_reason }}
                    </p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-lg font-semibold">Import CSV (nationaldex_id, cost)</h2>
                    <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="submitCsv">
                        <div class="flex flex-col gap-1">
                            <Input type="file" accept=".csv,.txt,text/csv" @input="csvForm.csv_file = ($event.target as HTMLInputElement)?.files?.[0] ?? null" />
                            <p v-if="csvForm.errors.csv_file" class="text-destructive text-sm">{{ csvForm.errors.csv_file }}</p>
                        </div>
                        <Button type="submit" :disabled="csvForm.processing || !csvForm.csv_file">Upload</Button>
                    </form>
                </section>

                <section class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold">Current pool</h2>
                        <Button type="button" variant="outline" size="sm" @click="openAddDialog">Add Pokémon</Button>
                    </div>
                    <div class="overflow-x-auto rounded-md border">
                        <table class="w-full min-w-[32rem] text-left text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="p-2">Pokémon</th>
                                    <th class="p-2">Types</th>
                                    <th class="p-2">Cost</th>
                                    <th class="p-2">Status</th>
                                    <th class="p-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in props.pool" :key="row.id" class="border-border border-t">
                                    <td class="p-2">
                                        <div class="flex items-center gap-2">
                                            <img
                                                v-if="row.sprite_url"
                                                :src="row.sprite_url"
                                                :alt="row.name"
                                                class="size-8 shrink-0"
                                            />
                                            <span>{{ row.name }}</span>
                                        </div>
                                    </td>
                                    <td class="p-2">
                                        {{ row.type1 }}<template v-if="row.type2 && row.type2 !== '-'"> / {{ row.type2 }}</template>
                                    </td>
                                    <td class="p-2">
                                        <div class="flex items-center gap-2">
                                            <Input
                                                :model-value="costForms[row.id]?.cost ?? row.cost"
                                                type="number"
                                                min="0"
                                                class="w-24"
                                                @update:model-value="
                                                    (v) => {
                                                        if (!costForms[row.id]) {
                                                            costForms[row.id] = { cost: row.cost };
                                                        }
                                                        costForms[row.id].cost = Number(v);
                                                    }
                                                "
                                            />
                                            <Button type="button" variant="outline" size="sm" @click="patchCost(row.id)">Save</Button>
                                        </div>
                                    </td>
                                    <td class="p-2">
                                        <span v-if="row.is_drafted" class="text-muted-foreground text-xs">
                                            Drafted{{ row.drafted_by_team_name ? ` (${row.drafted_by_team_name})` : '' }}
                                        </span>
                                        <span v-else-if="row.banned" class="text-muted-foreground text-xs">Banned</span>
                                        <span v-else class="text-xs text-emerald-600 dark:text-emerald-400">Available</span>
                                    </td>
                                    <td class="p-2 text-right">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive"
                                            :disabled="!!row.is_drafted"
                                            @click="removeRow(row)"
                                        >
                                            Remove
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="props.pool.length === 0" class="text-muted-foreground text-sm">No Pokémon in this pool yet.</p>
                </section>
            </div>
        </AdminLayout>

        <Dialog v-model:open="previewOpen">
            <DialogContent class="max-h-[90vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ previewTemplate?.name }} — preview</DialogTitle>
                    <DialogDescription>
                        {{ previewData?.total ?? previewTemplate?.rows_count ?? 0 }} Pokémon in this template.
                        <template v-if="previewTemplate?.version_group">
                            · Gen {{ previewTemplate.version_group.generation }} ({{ previewTemplate.version_group.name }})
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <div v-if="previewLoading" class="text-muted-foreground text-sm">Loading…</div>
                <div v-else-if="previewData" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <div
                        v-for="r in previewData.data"
                        :key="r.id"
                        class="border-border flex items-center gap-2 rounded-md border p-2 text-sm"
                    >
                        <img v-if="r.sprite_url" :src="r.sprite_url" :alt="r.name" class="size-10 shrink-0" />
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ r.name }}</div>
                            <div class="text-muted-foreground text-xs">{{ r.cost }} pts</div>
                        </div>
                    </div>
                </div>
                <DialogFooter v-if="previewData && previewData.last_page > 1" class="flex gap-2 sm:justify-between">
                    <Button type="button" variant="outline" size="sm" :disabled="previewPage <= 1" @click="loadPreview(previewPage - 1)">
                        Previous
                    </Button>
                    <span class="text-muted-foreground self-center text-sm">Page {{ previewPage }} / {{ previewData.last_page }}</span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="previewPage >= previewData.last_page"
                        @click="loadPreview(previewPage + 1)"
                    >
                        Next
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="applyDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Apply {{ templateToApply?.name }}?</DialogTitle>
                    <DialogDescription v-if="props.poolReplace.has_pool && props.poolReplace.allowed">
                        This will delete every Pokémon currently in this league pool and replace them with the template. This cannot be undone.
                    </DialogDescription>
                    <DialogDescription v-else-if="!props.poolReplace.has_pool"> This will add all template Pokémon to your empty pool. </DialogDescription>
                    <p v-if="templateToApply?.version_group" class="text-muted-foreground text-sm">
                        Template generation: Gen {{ templateToApply.version_group.generation }} ({{ templateToApply.version_group.name }})
                    </p>
                </DialogHeader>
                <div v-if="props.poolReplace.has_pool && props.poolReplace.allowed" class="flex items-start gap-2 py-2">
                    <input
                        id="confirm-replace-pool"
                        v-model="applyConfirmChecked"
                        type="checkbox"
                        class="border-input mt-1 size-4 rounded border"
                    />
                    <label for="confirm-replace-pool" class="text-sm leading-snug">
                        I understand the current league Pokémon pool will be permanently removed and replaced.
                    </label>
                </div>
                <p v-if="templateApplyError" class="text-destructive text-sm">{{ templateApplyError }}</p>
                <DialogFooter class="gap-2 sm:justify-end">
                    <Button type="button" variant="outline" @click="applyDialogOpen = false">Cancel</Button>
                    <Button
                        type="button"
                        :disabled="
                            applyForm.processing ||
                            (props.poolReplace.has_pool && props.poolReplace.allowed && !applyConfirmChecked)
                        "
                        @click="submitApply"
                    >
                        {{ props.poolReplace.has_pool ? 'Confirm swap' : 'Apply' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="addDialogOpen">
            <DialogContent class="max-h-[90vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Add Pokémon</DialogTitle>
                    <DialogDescription>Search the Pokédex. Species already in this pool are hidden.</DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <Input v-model="addSearch" type="search" placeholder="Name search" class="sm:max-w-xs" />
                    <select
                        v-model="addType1"
                        class="border-input bg-background h-9 rounded-md border px-2 text-sm"
                        @change="onAddFilterChange"
                    >
                        <option value="">Any type</option>
                        <option v-for="t in props.pokedexTypeOptions" :key="t" :value="t">{{ t }}</option>
                    </select>
                    <select
                        v-model="addGeneration"
                        class="border-input bg-background h-9 rounded-md border px-2 text-sm"
                        @change="onAddFilterChange"
                    >
                        <option value="">Any generation</option>
                        <option v-for="g in props.pokedexGenerationOptions" :key="g" :value="g">Gen {{ g }}</option>
                    </select>
                    <Button type="button" variant="secondary" size="sm" @click="loadAddResults(1)">Search</Button>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-muted-foreground text-sm">Default cost for next add:</span>
                    <Input v-model.number="addForm.cost" type="number" min="0" class="w-24" />
                </div>
                <div v-if="addLoading" class="text-muted-foreground text-sm">Loading…</div>
                <div v-else-if="addResults" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <div
                        v-for="p in addResults.data"
                        :key="p.id"
                        class="border-border flex flex-col gap-2 rounded-md border p-2 text-sm"
                    >
                        <div class="flex items-center gap-2">
                            <img v-if="p.sprite_url" :src="p.sprite_url" :alt="p.name" class="size-10 shrink-0" />
                            <span class="min-w-0 truncate font-medium">{{ p.name }}</span>
                        </div>
                        <Button type="button" size="sm" :disabled="addForm.processing" @click="submitAddPokemon(p)">Add</Button>
                    </div>
                </div>
                <DialogFooter v-if="addResults && addResults.last_page > 1" class="flex gap-2 sm:justify-between">
                    <Button type="button" variant="outline" size="sm" :disabled="addPage <= 1" @click="loadAddResults(addPage - 1)">
                        Previous
                    </Button>
                    <span class="text-muted-foreground self-center text-sm">Page {{ addPage }} / {{ addResults.last_page }}</span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="addPage >= addResults.last_page"
                        @click="loadAddResults(addPage + 1)"
                    >
                        Next
                    </Button>
                </DialogFooter>
                <p v-if="addForm.errors.pokedex_id || addForm.errors.cost" class="text-destructive text-sm">
                    {{ addForm.errors.pokedex_id || addForm.errors.cost }}
                </p>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
