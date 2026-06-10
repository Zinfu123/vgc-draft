<?php

namespace App\Modules\Pokepaste\Services;

class ShowdownReplayKoParser
{
    public function __construct(
        private ShowdownPasteParser $pasteParser,
    ) {}

    /**
     * Parse which pokemon caused knockouts and which pokemon fainted in a single game.
     *
     * Tracks the active attacker per slot and credits a direct KO when:
     * - A |faint| event occurs, AND
     * - The last |-damage| to that slot had no [from] tag (i.e. was direct move damage)
     *
     * Residual damage (burn, poison, weather, recoil, etc.) is detected via
     * the [from] tag in |-damage| lines and does NOT credit a KO.
     *
     * Deaths (p1Deaths/p2Deaths) track ALL fainted pokemon regardless of damage source.
     *
     * @return array{p1: list<string>, p2: list<string>, p1Deaths: list<string>, p2Deaths: list<string>}
     *                                                                                                   Species names indexed by their side.
     */
    public function parse(string $log): array
    {
        $log = str_replace(["\r\n", "\r"], "\n", $log);

        // Which species currently occupies each slot (e.g. 'p1a' => 'Chien-Pao')
        $slotPokemon = [];

        // The pokemon that most recently used a move (the active attacker)
        $activeAttackerSlot = null;

        // Per target slot: whether the most recent damage event was indirect
        $lastDamageIndirect = [];

        // Per target slot: which pokemon slot dealt the last direct damage
        $lastDirectAttackerSlot = [];

        $p1Knockouts = [];
        $p2Knockouts = [];
        $p1Deaths = [];
        $p2Deaths = [];

        foreach (explode("\n", $log) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, '|')) {
                continue;
            }

            $parts = explode('|', $line);
            $type = $parts[1] ?? '';

            // Track which species is in each slot
            if ($type === 'switch' || $type === 'drag') {
                // |switch|p1a: Nickname|Species detail|hp
                $slot = $this->extractSlot($parts[2] ?? '');
                $species = $this->pasteParser->speciesRawFromReplayPokeDetails($parts[3] ?? '');
                if ($slot !== null && $species !== null && $species !== '') {
                    $slotPokemon[$slot] = $species;
                }

                continue;
            }

            // Track the active attacker when a move is used
            if ($type === 'move') {
                // |move|p1a: Nickname|Move Name|p2a: Nickname
                $attackerSlot = $this->extractSlot($parts[2] ?? '');
                if ($attackerSlot !== null) {
                    $activeAttackerSlot = $attackerSlot;
                }

                continue;
            }

            // Track whether the last damage to each slot was direct or indirect
            if ($type === '-damage') {
                // |-damage|p2a: Nickname|hp|[from] brn  (indirect)
                // |-damage|p2a: Nickname|0 fnt           (direct)
                $targetSlot = $this->extractSlot($parts[2] ?? '');
                if ($targetSlot === null) {
                    continue;
                }

                $isIndirect = str_contains($line, '[from]');
                $lastDamageIndirect[$targetSlot] = $isIndirect;

                if (! $isIndirect && $activeAttackerSlot !== null) {
                    $lastDirectAttackerSlot[$targetSlot] = $activeAttackerSlot;
                }

                continue;
            }

            // Credit KO on faint
            if ($type === 'faint') {
                // |faint|p2a: Nickname
                $faintedSlot = $this->extractSlot($parts[2] ?? '');
                if ($faintedSlot === null) {
                    continue;
                }

                // Track the death regardless of damage source
                $faintedSpecies = $slotPokemon[$faintedSlot] ?? null;
                if ($faintedSpecies !== null) {
                    if (str_starts_with($faintedSlot, 'p1')) {
                        $p1Deaths[] = $faintedSpecies;
                    } else {
                        $p2Deaths[] = $faintedSpecies;
                    }
                }

                // Skip crediting a kill if last damage was residual/indirect
                if ($lastDamageIndirect[$faintedSlot] ?? false) {
                    continue;
                }

                $killerSlot = $lastDirectAttackerSlot[$faintedSlot] ?? null;
                if ($killerSlot === null) {
                    continue;
                }

                $killerSpecies = $slotPokemon[$killerSlot] ?? null;
                if ($killerSpecies === null) {
                    continue;
                }

                if (str_starts_with($killerSlot, 'p1')) {
                    $p1Knockouts[] = $killerSpecies;
                } else {
                    $p2Knockouts[] = $killerSpecies;
                }
            }
        }

        return ['p1' => $p1Knockouts, 'p2' => $p2Knockouts, 'p1Deaths' => $p1Deaths, 'p2Deaths' => $p2Deaths];
    }

    private function extractSlot(string $str): ?string
    {
        // Matches 'p1a', 'p1b', 'p2a', 'p2b' at the start of slot strings like 'p1a: Nickname'
        if (preg_match('/^(p[12][ab])/', $str, $m)) {
            return $m[1];
        }

        return null;
    }
}
