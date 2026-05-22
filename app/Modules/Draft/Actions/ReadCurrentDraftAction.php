<?php

namespace App\Modules\Draft\Actions;

/* Define Models */
use App\Modules\Draft\Models\BanOrder;
use App\Modules\Draft\Models\Bans;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\Draft\Models\DraftPick;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Storage;

/* End Define Models */

class ReadCurrentDraftAction
{
    public function __invoke($data)
    /* Drafted Pokemon */
    {
        if ($data['command'] == 'draftedpokemon') {
            $draftedpokemon = DraftPick::where('league_id', $data['league_id'])->with('leaguePokemon.pokemon')->get();
            $draftedpokemon = $draftedpokemon->pluck('league_pokemon_id')->all();

            return $draftedpokemon;
        }

        /* Draft Order */
        elseif ($data['command'] == 'draftorder') {
            $roundnumber = Draft::where('league_id', $data['league_id'])->first();
            if ($roundnumber === null) {
                return collect([]);
            }
            $roundnumber = $roundnumber->round_number;
            $draftorder = DraftOrder::where('league_id', $data['league_id'])->with('team')
                ->where('round_number', $roundnumber)
                ->orderBy('pick_number', 'asc')
                ->get();
            $draftorder = $draftorder->map(function ($draftorder) {
                if ($draftorder->team && $draftorder->team->logo !== null) {
                    $draftorder->team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($draftorder->team->logo));
                }

                return $draftorder;
            });

            return $draftorder;
        }

        /* Current Picker */
        elseif ($data['command'] == 'currentpicker') {
            $currentpicker = DraftOrder::where('league_id', $data['league_id'])->with('team')->where('status', 1)->orderBy('pick_number', 'asc')->first();
            if ($currentpicker && $currentpicker->team !== null) {
                if ($currentpicker->team->logo !== null) {
                    $currentpicker->team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($currentpicker->team->logo));
                }
            }

            return $currentpicker;
        } elseif ($data['command'] == 'teams') {
            $teams = Team::where('league_id', $data['league_id'])->with('draftPicks.leaguePokemon.pokemon')->get();
            $teams = $teams->map(function ($team) {
                if ($team->logo !== null) {
                    $team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($team->logo));
                }

                return $team;
            });

            return $teams;
        } elseif ($data['command'] == 'currentbanner') {
            $currentBanner = BanOrder::where('league_id', $data['league_id'])
                ->with('team.user')
                ->where('status', 1)
                ->orderBy('round_number', 'asc')
                ->orderBy('ban_number', 'asc')
                ->first();

            if ($currentBanner && $currentBanner->team !== null) {
                if ($currentBanner->team->logo !== null) {
                    $currentBanner->team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($currentBanner->team->logo));
                }
                $currentBanner->team->coach = $currentBanner->team->user?->name;
            }

            return $currentBanner;
        } elseif ($data['command'] == 'banorder') {
            $currentRound = BanOrder::where('league_id', $data['league_id'])
                ->where('status', 1)
                ->orderBy('round_number', 'asc')
                ->value('round_number');

            if ($currentRound === null) {
                $currentRound = BanOrder::where('league_id', $data['league_id'])
                    ->orderBy('round_number', 'desc')
                    ->value('round_number');
            }

            if ($currentRound === null) {
                return collect([]);
            }

            $banOrders = BanOrder::where('league_id', $data['league_id'])
                ->with('team')
                ->where('round_number', $currentRound)
                ->orderBy('ban_number', 'asc')
                ->get();

            return $banOrders->map(function ($order) {
                if ($order->team && $order->team->logo !== null) {
                    $order->team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($order->team->logo));
                }

                return $order;
            });
        } elseif ($data['command'] == 'lastban') {
            $lastBan = Bans::where('league_id', $data['league_id'])
                ->whereNotNull('pokedex_id')
                ->with(['team', 'pokedex'])
                ->orderBy('updated_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($lastBan !== null && $lastBan->team !== null) {
                if ($lastBan->team->logo !== null) {
                    $lastBan->team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($lastBan->team->logo));
                }
            }

            return $lastBan;
        } elseif ($data['command'] == 'allbans') {
            $allBans = Bans::where('league_id', $data['league_id'])
                ->whereNotNull('pokedex_id')
                ->with(['team', 'pokedex'])
                ->orderBy('round_number', 'asc')
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($ban) {
                    if ($ban->team && $ban->team->logo !== null) {
                        $ban->team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($ban->team->logo));
                    }

                    return $ban;
                });

            return $allBans;
        } elseif ($data['command'] == 'lastskip') {
            $lastSkip = DraftOrder::query()
                ->where('league_id', $data['league_id'])
                ->whereNotNull('skipped_at')
                ->with('team.user')
                ->orderBy('skipped_at', 'desc')
                ->first();

            if ($lastSkip !== null && $lastSkip->team !== null) {
                if ($lastSkip->team->logo !== null) {
                    $lastSkip->team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($lastSkip->team->logo));
                }
                $lastSkip->team->coach = $lastSkip->team->user?->name;
            }

            return $lastSkip;
        } elseif ($data['command'] == 'lastbanskip') {
            $lastBanSkip = BanOrder::query()
                ->where('league_id', $data['league_id'])
                ->whereNotNull('skipped_at')
                ->with('team.user')
                ->orderBy('skipped_at', 'desc')
                ->first();

            if ($lastBanSkip !== null && $lastBanSkip->team !== null) {
                if ($lastBanSkip->team->logo !== null) {
                    $lastBanSkip->team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($lastBanSkip->team->logo));
                }
                $lastBanSkip->team->coach = $lastBanSkip->team->user?->name;
            }

            return $lastBanSkip;
        } elseif ($data['command'] == 'lastpick') {
            $lastpick = DraftPick::where('league_id', $data['league_id'])->with('leaguePokemon.pokemon')->orderBy('round_number', 'desc')->orderBy('pick_number', 'desc')->first();
            if ($lastpick !== null) {
                $lastpick->team = Team::with('user')->where('id', $lastpick->team_id)->where('league_id', $lastpick->league_id)->first();
                if ($lastpick->team !== null) {
                    if ($lastpick->team->logo !== null) {
                        $lastpick->team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($lastpick->team->logo));
                    }
                    $lastpick->team->coach = $lastpick->team->user?->name;
                }
            }

            return $lastpick;
        }
    }
}
