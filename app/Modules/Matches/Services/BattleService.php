<?php

namespace App\Modules\Matches\Services;

use App\Modules\Matches\Models\Battle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BattleService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('battle.service_url');
    }

    /**
     * Initialise the battle in the Node service once both teams have submitted.
     *
     * @return array<string, mixed>
     */
    public function startBattle(Battle $battle): array
    {
        $response = Http::post("{$this->baseUrl}/battle", [
            'battleId' => (string) $battle->id,
            'format' => $battle->format,
            'p1Name' => $battle->p1Team->name,
            'p1Team' => $battle->p1_packed_team,
            'p2Name' => $battle->p2Team->name,
            'p2Team' => $battle->p2_packed_team,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Battle service error: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Submit a move or switch for a player.
     *
     * @return array<string, mixed>
     */
    public function submitAction(Battle $battle, string $player, string $action): array
    {
        $response = Http::post("{$this->baseUrl}/battle/{$battle->id}/action", [
            'player' => $player,
            'action' => $action,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Battle service error: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Fetch the current battle log from the Node service.
     *
     * @return array<string, mixed>
     */
    public function fetchLog(Battle $battle): array
    {
        $response = Http::get("{$this->baseUrl}/battle/{$battle->id}");

        if ($response->failed()) {
            throw new RuntimeException('Battle service error: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Clean up a finished battle from the Node service memory.
     */
    public function destroyBattle(Battle $battle): void
    {
        try {
            Http::delete("{$this->baseUrl}/battle/{$battle->id}");
        } catch (ConnectionException) {
            // Non-fatal — service may have restarted
        }
    }

    /**
     * Check whether the battle service is reachable.
     */
    public function isHealthy(): bool
    {
        try {
            return Http::get("{$this->baseUrl}/health")->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * Extract the winner from a set of output lines.
     * Returns 'p1', 'p2', or null if not yet finished.
     */
    public function extractWinner(array $outputLines): ?string
    {
        foreach ($outputLines as $line) {
            if (str_starts_with($line, '|win|')) {
                $winnerName = trim(substr($line, 5));

                return $winnerName;
            }
        }

        return null;
    }
}
