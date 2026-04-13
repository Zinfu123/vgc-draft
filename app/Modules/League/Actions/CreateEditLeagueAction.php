<?php

namespace App\Modules\League\Actions;

use App\Enums\Playoffs\PlayoffFormat;
use App\Enums\Playoffs\PlayoffStatus;
use App\Enums\PokemonGame;
use App\Jobs\EnforceTradeDeadlineJob;
use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Playoffs\Models\Playoff;
use App\Modules\Playoffs\Services\PlayoffBracketService;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateEditLeagueAction
{
    private function normalizeOptionalWebhookUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public function __invoke($data)
    {
        if ($data['command'] == 'create') {
            return $this->create($data);
        } elseif ($data['command'] == 'edit') {
            return $this->edit($data);
        }
    }

    public function create(Request $request)
    {
        $request->mergeIfMissing([
            'pokemon_generation' => (int) config('pokemon.default_league_generation'),
            'pokemon_game' => (string) config('pokemon.default_league_game'),
        ]);

        $request->merge([
            'discord_webhook_url' => $this->normalizeOptionalWebhookUrl($request->input('discord_webhook_url')),
            'discord_replay_webhook_url' => $this->normalizeOptionalWebhookUrl($request->input('discord_replay_webhook_url')),
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'draft_date' => 'required|date',
            'draft_start_at' => 'nullable|date',
            'set_start_date' => 'required|date',
            'set_frequency' => 'required|integer',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'draft_points' => 'required|integer',
            'minimum_drafts' => ['required', 'integer', 'min:0'],
            'enforce_round_count' => 'required|boolean',
            'round_count' => 'required|integer',
            'ban_enabled' => 'boolean',
            'bans_per_user' => 'nullable|integer|min:1',
            'minimum_cost_to_ban' => 'nullable|integer|min:0',
            'pokemon_generation' => ['required', 'integer', 'min:1', 'max:99'],
            'pokemon_game' => ['required', Rule::enum(PokemonGame::class)],
            'discord_webhook_url' => ['nullable', 'string', 'url', 'max:500'],
            'discord_replay_webhook_url' => ['nullable', 'string', 'url', 'max:500'],
            'playoff_format' => ['required', Rule::enum(PlayoffFormat::class)],
            'playoff_bracket_size' => ['required', 'integer', Rule::in(PlayoffBracketService::allowedBracketSizes())],
            'playoffs_enabled' => 'boolean',
            'free_trade_window_hours' => ['required', 'integer', 'min:0'],
            'trade_deadline_at' => ['nullable', 'date'],
            'require_showdown_username' => 'boolean',
        ]);

        $this->assertPokemonGameMatchesGeneration($request);
        if ($request->hasFile('logo')) {
            $logo = (new LeagueLogoUploadAction)->upload($request);
        } else {
            $logo = null;
        }
        $league = League::create([
            'name' => $request->name,
            'set_start_date' => $request->set_start_date,
            'set_frequency' => $request->set_frequency,
            'logo' => $logo,
            'discord_webhook_url' => $request->input('discord_webhook_url'),
            'discord_replay_webhook_url' => $request->input('discord_replay_webhook_url'),
            'league_owner' => Auth::user()->id,
            'pokemon_generation' => $request->integer('pokemon_generation'),
            'pokemon_game' => $request->string('pokemon_game')->toString(),
            'require_showdown_username' => $request->boolean('require_showdown_username'),
            'playoffs_enabled' => $request->boolean('playoffs_enabled', true),
            'free_trade_window_hours' => $request->integer('free_trade_window_hours', 24),
            'trade_deadline_at' => $request->input('trade_deadline_at') ? Carbon::parse($request->input('trade_deadline_at')) : null,
        ]);

        $this->scheduleTradeDeadlineJob($league);

        DraftConfig::create([
            'league_id' => $league->id,
            'draft_date' => $request->draft_date,
            'draft_start_at' => $request->input('draft_start_at') ?: null,
            'draft_points' => $request->draft_points,
            'minimum_drafts' => $request->integer('minimum_drafts'),
            'ban_enabled' => $request->boolean('ban_enabled'),
            'bans_per_user' => $request->ban_enabled ? $request->bans_per_user : null,
            'minimum_cost_to_ban' => $request->ban_enabled ? $request->minimum_cost_to_ban : null,
        ]);

        $matchConfig = MatchConfig::updateOrCreate(
            ['league_id' => $league->id],
            [
                'enforce_round_count' => $request->boolean('enforce_round_count'),
                'round_count' => $request->enforce_round_count ? $request->round_count : null,
            ]
        );

        Pool::create([
            'match_config_id' => $matchConfig->id,
            'league_id' => $league->id,
        ]);

        Playoff::query()->create([
            'league_id' => $league->id,
            'format' => PlayoffFormat::from($request->string('playoff_format')->toString()),
            'bracket_size' => $request->integer('playoff_bracket_size'),
            'status' => PlayoffStatus::Draft,
            'seed_order' => null,
        ]);

        $owner = Auth::user();
        $pool = Pool::query()->where('league_id', $league->id)->orderBy('id')->first();

        Team::query()->create([
            'name' => $this->defaultLeagueOwnerTeamName($owner),
            'league_id' => $league->id,
            'user_id' => $owner->id,
            'pick_position' => 1,
            'draft_points' => $request->integer('draft_points'),
            'admin_flag' => 1,
            'pool_id' => $pool?->id,
        ]);

        return $league;
    }

    public function edit(Request $request)
    {
        $request->merge([
            'discord_webhook_url' => $this->normalizeOptionalWebhookUrl($request->input('discord_webhook_url')),
            'discord_replay_webhook_url' => $this->normalizeOptionalWebhookUrl($request->input('discord_replay_webhook_url')),
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'draft_date' => 'required|date',
            'draft_start_at' => 'nullable|date',
            'set_start_date' => 'required|date',
            'set_frequency' => 'required|integer',
            'enforce_round_count' => 'required|boolean',
            'round_count' => 'required|integer',
            'draft_points' => 'required|integer',
            'minimum_drafts' => 'required|integer',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'ban_enabled' => 'boolean',
            'bans_per_user' => 'nullable|integer|min:1',
            'minimum_cost_to_ban' => 'nullable|integer|min:0',
            'pokemon_generation' => ['required', 'integer', 'min:1', 'max:99'],
            'pokemon_game' => ['required', Rule::enum(PokemonGame::class)],
            'discord_webhook_url' => ['nullable', 'string', 'url', 'max:500'],
            'discord_replay_webhook_url' => ['nullable', 'string', 'url', 'max:500'],
            'playoff_format' => ['required', Rule::enum(PlayoffFormat::class)],
            'playoff_bracket_size' => ['required', 'integer', Rule::in(PlayoffBracketService::allowedBracketSizes())],
            'playoffs_enabled' => 'boolean',
            'free_trade_window_hours' => ['required', 'integer', 'min:0'],
            'trade_deadline_at' => ['nullable', 'date'],
            'require_showdown_username' => 'boolean',
        ]);

        $this->assertPokemonGameMatchesGeneration($request);
        $league = League::query()->where('id', $request->integer('league_id'))->firstOrFail();
        if ($request->hasFile('logo')) {
            $oldlogo = $league->logo;
            if ($oldlogo !== null) {
                Storage::disk('s3-league-logos')->delete($oldlogo);
            }
            $logo = (new LeagueLogoUploadAction)->upload($request);
        }
        $league->name = $request->name;
        $league->set_start_date = $request->set_start_date;
        $league->set_frequency = $request->set_frequency;
        $league->logo = $logo ?? $league->logo;
        $league->pokemon_generation = $request->integer('pokemon_generation');
        $league->pokemon_game = $request->string('pokemon_game')->toString();
        $league->discord_webhook_url = $request->input('discord_webhook_url');
        $league->discord_replay_webhook_url = $request->input('discord_replay_webhook_url');
        $league->require_showdown_username = $request->boolean('require_showdown_username');
        $league->playoffs_enabled = $request->boolean('playoffs_enabled', true);
        $league->free_trade_window_hours = $request->integer('free_trade_window_hours', 24);
        $league->trade_deadline_at = $request->input('trade_deadline_at') ? Carbon::parse($request->input('trade_deadline_at')) : null;
        $league->save();

        $this->scheduleTradeDeadlineJob($league);

        $league->draftConfig()->updateOrCreate(
            ['league_id' => $league->id],
            [
                'draft_date' => $request->draft_date,
                'draft_start_at' => $request->input('draft_start_at') ?: null,
                'draft_points' => $request->draft_points,
                'minimum_drafts' => $request->minimum_drafts,
                'ban_enabled' => $request->boolean('ban_enabled'),
                'bans_per_user' => $request->ban_enabled ? $request->bans_per_user : null,
                'minimum_cost_to_ban' => $request->ban_enabled ? $request->minimum_cost_to_ban : null,
            ]
        );

        $league->matchConfig()->updateOrCreate(
            ['league_id' => $league->id],
            [
                'enforce_round_count' => $request->boolean('enforce_round_count'),
                'round_count' => $request->enforce_round_count ? $request->round_count : null,
            ]
        );

        $playoff = Playoff::query()->where('league_id', $league->id)->first();
        $format = PlayoffFormat::from($request->string('playoff_format')->toString());
        $bracketSize = $request->integer('playoff_bracket_size');

        if ($playoff === null) {
            Playoff::query()->create([
                'league_id' => $league->id,
                'format' => $format,
                'bracket_size' => $bracketSize,
                'status' => PlayoffStatus::Draft,
                'seed_order' => null,
            ]);
        } elseif ($playoff->status === PlayoffStatus::Draft) {
            $playoff->format = $format;
            $playoff->bracket_size = $bracketSize;
            $playoff->save();
        }

        return $league;
    }

    /**
     * Dispatch a delayed job to cancel pending trades at the deadline. The job
     * checks that the stored deadline still matches at runtime, so if the
     * deadline is updated a new job is dispatched and the old one self-skips.
     */
    private function scheduleTradeDeadlineJob(League $league): void
    {
        if ($league->trade_deadline_at === null || $league->trade_deadline_at->isPast()) {
            return;
        }

        EnforceTradeDeadlineJob::dispatch($league->id, $league->trade_deadline_at)
            ->delay($league->trade_deadline_at);
    }

    private function defaultLeagueOwnerTeamName(User $user): string
    {
        $label = trim($user->name);
        if ($label === '') {
            $label = 'Commissioner';
        }

        $suffix = '\'s Team';
        $maxBaseLength = max(0, 255 - Str::length($suffix));
        $base = Str::limit($label, $maxBaseLength, '');

        return $base.$suffix;
    }

    private function assertPokemonGameMatchesGeneration(Request $request): void
    {
        $game = PokemonGame::tryFrom($request->string('pokemon_game')->toString());
        if ($game === null || $game->generation() !== $request->integer('pokemon_generation')) {
            throw ValidationException::withMessages([
                'pokemon_game' => ['The selected game must match the generation.'],
            ]);
        }
    }
}
