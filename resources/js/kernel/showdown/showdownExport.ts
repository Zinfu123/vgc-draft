export interface PokepasteSlot {
    league_pokemon_id: number | null;
    ability: string;
    moves: string[];
    version_group_held_item_id: number | null;
    nature: number | null;
    tera_type: string | null;
    evs: Record<string, number> | null;
}

export interface RosterMapEntry {
    pokedex_name: string;
}

const evOrder: Record<string, string> = {
    hp: 'HP',
    atk: 'Atk',
    def: 'Def',
    spa: 'SpA',
    spd: 'SpD',
    spe: 'Spe',
};

export function moveSlugToDisplay(slug: string): string {
    return slug
        .trim()
        .toLowerCase()
        .split('-')
        .filter(Boolean)
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

function formatEvsLine(evs: Record<string, number> | null | undefined): string | null {
    if (!evs || typeof evs !== 'object') {
        return null;
    }
    const parts: string[] = [];
    for (const [key, label] of Object.entries(evOrder)) {
        const v = evs[key];
        if (typeof v === 'number' && v > 0) {
            parts.push(`${v} ${label}`);
        }
    }
    if (parts.length === 0) {
        return null;
    }
    return `EVs: ${parts.join(' / ')}`;
}

function slotIsComplete(slot: PokepasteSlot): boolean {
    if (!slot.league_pokemon_id || !slot.ability?.trim()) {
        return false;
    }
    const m = slot.moves ?? [];
    return m.length === 4 && m.every((x) => String(x).trim() !== '');
}

export function buildShowdownExport(
    slots: PokepasteSlot[],
    rosterById: Record<number, RosterMapEntry>,
    heldItemLabelById: Record<number, string>,
    natureLabelByValue: Record<number, string>,
): string {
    const blocks: string[] = [];

    for (const slot of slots) {
        if (!slotIsComplete(slot) || !slot.league_pokemon_id) {
            continue;
        }
        const entry = rosterById[slot.league_pokemon_id];
        if (!entry) {
            continue;
        }

        const species = entry.pokedex_name;
        const heldId = slot.version_group_held_item_id;
        const item =
            heldId != null && heldId > 0 && heldItemLabelById[heldId] ? heldItemLabelById[heldId].trim() : null;

        const lines: string[] = [];
        lines.push(item ? `${species} @ ${item}` : species);
        lines.push(`Ability: ${slot.ability.trim()}`);
        lines.push('Level: 50');

        const evLine = formatEvsLine(slot.evs ?? null);
        if (evLine) {
            lines.push(evLine);
        }

        if (slot.nature != null && natureLabelByValue[slot.nature]) {
            lines.push(`${natureLabelByValue[slot.nature]} Nature`);
        }

        if (slot.tera_type?.trim()) {
            lines.push(`Tera Type: ${slot.tera_type.trim()}`);
        }

        const moves = (slot.moves ?? []).slice(0, 4);
        for (const move of moves) {
            lines.push(`- ${moveSlugToDisplay(String(move))}`);
        }

        blocks.push(lines.join('\n'));
    }

    return blocks.join('\n\n');
}
