<?php

namespace App\Modules\Pokepaste\Services;

class ShowdownReplayDamageParser
{
    public function __construct(
        private ShowdownPasteParser $pasteParser,
    ) {}

    /**
     * Parse total direct damage dealt by each Pokemon per side in a single game.
     *
     * Tracks HP per slot and credits damage to the active attacker whenever a
     * direct |-damage| event (no [from] tag) causes HP to drop.
     *
     * Residual damage (burn, poison, weather, recoil, etc.) via [from] tags is excluded.
     *
     * @return array{p1: array<string, int>, p2: array<string, int>}
     *                                                               Species names mapped to total damage dealt, indexed by their side.
     */
    public function parse(string $log): array
    {
        $log = str_replace(["\r\n", "\r"], "\n", $log);

        /** @var array<string, string> $slotPokemon species currently in each slot */
        $slotPokemon = [];

        /** @var array<string, float> $slotHp current HP for each slot */
        $slotHp = [];

        $activeAttackerSlot = null;

        /** @var array<string, int> $p1Damage species => total damage */
        $p1Damage = [];

        /** @var array<string, int> $p2Damage species => total damage */
        $p2Damage = [];

        foreach (explode("\n", $log) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, '|')) {
                continue;
            }

            $parts = explode('|', $line);
            $type = $parts[1] ?? '';

            // Track which species occupies each slot and initialise their HP
            if ($type === 'switch' || $type === 'drag') {
                // |switch|p1a: Nickname|Species detail|HP/MaxHP
                $slot = $this->extractSlot($parts[2] ?? '');
                $species = $this->pasteParser->speciesRawFromReplayPokeDetails($parts[3] ?? '');
                $hp = $this->parseCurrentHp($parts[4] ?? '');

                if ($slot !== null && $species !== null && $species !== '') {
                    $slotPokemon[$slot] = $species;
                    $slotHp[$slot] = $hp;
                }

                continue;
            }

            // Track the active attacker when a move is used
            if ($type === 'move') {
                // |move|p1a: Nickname|Move Name|target
                $attackerSlot = $this->extractSlot($parts[2] ?? '');
                if ($attackerSlot !== null) {
                    $activeAttackerSlot = $attackerSlot;
                }

                continue;
            }

            // Credit direct damage to the active attacker
            if ($type === '-damage') {
                // Skip residual/indirect damage
                if (str_contains($line, '[from]')) {
                    continue;
                }

                $targetSlot = $this->extractSlot($parts[2] ?? '');
                if ($targetSlot === null || $activeAttackerSlot === null) {
                    continue;
                }

                $newHp = $this->parseCurrentHp($parts[3] ?? '');
                $prevHp = $slotHp[$targetSlot] ?? null;

                if ($prevHp !== null && $newHp < $prevHp) {
                    $damage = (int) round($prevHp - $newHp);
                    if ($damage > 0) {
                        $attackerSpecies = $slotPokemon[$activeAttackerSlot] ?? null;
                        if ($attackerSpecies !== null) {
                            if (str_starts_with($activeAttackerSlot, 'p1')) {
                                $p1Damage[$attackerSpecies] = ($p1Damage[$attackerSpecies] ?? 0) + $damage;
                            } else {
                                $p2Damage[$attackerSpecies] = ($p2Damage[$attackerSpecies] ?? 0) + $damage;
                            }
                        }
                    }
                }

                $slotHp[$targetSlot] = $newHp;

                continue;
            }

            // Keep HP up to date when a Pokemon heals
            if ($type === '-heal') {
                $targetSlot = $this->extractSlot($parts[2] ?? '');
                if ($targetSlot !== null) {
                    $slotHp[$targetSlot] = $this->parseCurrentHp($parts[3] ?? '');
                }

                continue;
            }
        }

        return ['p1' => $p1Damage, 'p2' => $p2Damage];
    }

    /**
     * Extract the current HP value from strings like "220/270", "150/270 brn", "0 fnt".
     */
    private function parseCurrentHp(string $str): float
    {
        $str = trim($str);

        if (str_contains($str, '/')) {
            [$current] = explode('/', $str, 2);

            return (float) trim($current);
        }

        // "0 fnt" or bare "0"
        return (float) $str;
    }

    private function extractSlot(string $str): ?string
    {
        if (preg_match('/^(p[12][ab])/', $str, $m)) {
            return $m[1];
        }

        return null;
    }
}
