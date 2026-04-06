<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const sections = [
    { id: 'overview', label: 'Overview' },
    { id: 'getting-started', label: 'Getting Started' },
    { id: 'leagues', label: 'Leagues' },
    { id: 'draft', label: 'Draft' },
    { id: 'teams', label: 'Teams' },
    { id: 'matches', label: 'Matches' },
    { id: 'battle-simulator', label: 'Battle Simulator' },
    { id: 'pokepaste', label: 'Pokepaste & Rosters' },
    { id: 'trades', label: 'Trades' },
    { id: 'playoffs', label: 'Playoffs' },
    { id: 'match-prep', label: 'Match Prep' },
    { id: 'pokedex', label: 'Pokédex & Stats' },
    { id: 'discord', label: 'Discord Integration' },
    { id: 'admin', label: 'Admin Reference' },
] as const;
</script>

<template>
    <Head title="Documentation – VGC Draft" />

    <div class="min-h-dvh bg-background text-foreground">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b border-border bg-background/95 backdrop-blur">
            <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <Link :href="route('home')" class="text-sm font-semibold text-foreground hover:text-muted-foreground transition-colors">
                        VGC Draft
                    </Link>
                    <span class="text-muted-foreground">/</span>
                    <span class="text-sm text-muted-foreground">Documentation</span>
                </div>
                <div class="flex items-center gap-3">
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="text-sm font-medium text-foreground hover:text-muted-foreground transition-colors">
                        Dashboard →
                    </Link>
                    <template v-else>
                        <Link :href="route('login')" class="text-sm text-muted-foreground hover:text-foreground transition-colors">Log in</Link>
                        <Link :href="route('register')" class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors">Register</Link>
                    </template>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex gap-10 py-10">
                <!-- Sidebar -->
                <aside class="hidden w-52 shrink-0 lg:block">
                    <nav class="sticky top-24 space-y-1">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-muted-foreground">Contents</p>
                        <a
                            v-for="s in sections"
                            :key="s.id"
                            :href="`#${s.id}`"
                            class="block rounded px-2 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        >
                            {{ s.label }}
                        </a>
                    </nav>
                </aside>

                <!-- Main content -->
                <main class="min-w-0 flex-1 space-y-16">

                    <!-- Overview -->
                    <section id="overview">
                        <h1 class="mb-2 text-3xl font-bold tracking-tight">VGC Draft Documentation</h1>
                        <p class="mb-6 text-lg text-muted-foreground">
                            Everything you need to run a competitive Pokémon VGC draft league — from creating a pool to crowning a champion.
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-lg border border-l-4 border-l-dragontype bg-dragontype/5 p-4 dark:bg-dragontype/10">
                                <h3 class="mb-1 font-semibold">Leagues & Drafts</h3>
                                <p class="text-sm text-muted-foreground">Create a league, curate a Pokémon pool with custom costs, and run a full snake draft with optional ban phases.</p>
                            </div>
                            <div class="rounded-lg border border-l-4 border-l-watertype bg-watertype/5 p-4 dark:bg-watertype/10">
                                <h3 class="mb-1 font-semibold">Matches & Pokepaste</h3>
                                <p class="text-sm text-muted-foreground">Submit Showdown-format rosters per match, validate them against pool rules, and log results as the season plays out.</p>
                            </div>
                            <div class="rounded-lg border border-l-4 border-l-electrictype bg-electrictype/5 p-4 dark:bg-electrictype/10">
                                <h3 class="mb-1 font-semibold">Trades & Free Agency</h3>
                                <p class="text-sm text-muted-foreground">Propose trades between teams or pick up undrafted Pokémon via free agency throughout the season.</p>
                            </div>
                            <div class="rounded-lg border border-l-4 border-l-grasstype bg-grasstype/5 p-4 dark:bg-grasstype/10">
                                <h3 class="mb-1 font-semibold">Playoffs & Stats</h3>
                                <p class="text-sm text-muted-foreground">Generate brackets, record knockout results, and track Pokémon usage stats across your league's meta.</p>
                            </div>
                        </div>
                    </section>

                    <hr class="border-border" />

                    <!-- Getting Started -->
                    <section id="getting-started" class="space-y-4">
                        <h2 class="text-2xl font-bold tracking-tight">Getting Started</h2>
                        <p class="text-muted-foreground">Follow these steps to go from a fresh account to a running draft in minutes.</p>

                        <ol class="space-y-4">
                            <li class="flex gap-4">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">1</span>
                                <div>
                                    <p class="font-medium">Create an account</p>
                                    <p class="text-sm text-muted-foreground">Register with an email and password, or link your Discord account for faster login. Email verification is required before accessing the app.</p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">2</span>
                                <div>
                                    <p class="font-medium">Create a league</p>
                                    <p class="text-sm text-muted-foreground">Go to <strong>Leagues → New League</strong>. Set a name, choose whether the league is open for others to join, and configure the maximum number of teams.</p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">3</span>
                                <div>
                                    <p class="font-medium">Build your Pokémon pool</p>
                                    <p class="text-sm text-muted-foreground">In the league admin, go to <strong>Pokémon Pool</strong>. Add Pokémon from the Pokédex, assign a cost to each, and optionally apply a saved template to speed things up.</p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">4</span>
                                <div>
                                    <p class="font-medium">Configure the draft</p>
                                    <p class="text-sm text-muted-foreground">In <strong>Admin → Draft Settings</strong>, set the number of rounds, Pokémon per team, and whether a ban phase is enabled.</p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">5</span>
                                <div>
                                    <p class="font-medium">Add teams and invite players</p>
                                    <p class="text-sm text-muted-foreground">Teams can be created by the league owner or by players who join an open league. Each team is linked to one user account.</p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">6</span>
                                <div>
                                    <p class="font-medium">Run the draft</p>
                                    <p class="text-sm text-muted-foreground">Open the draft room. Picks happen in snake order. Bans (if configured) occur before picks. The draft admin can revert the last pick if needed.</p>
                                </div>
                            </li>
                        </ol>
                    </section>

                    <hr class="border-border" />

                    <!-- Leagues -->
                    <section id="leagues" class="space-y-4">
                        <h2 class="text-2xl font-bold tracking-tight">Leagues</h2>
                        <p class="text-muted-foreground">A league is the top-level container for your season. Everything — pools, teams, matches, trades, and playoffs — lives inside a league.</p>

                        <div class="space-y-3">
                            <h3 class="text-lg font-semibold">League settings</h3>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border text-left text-muted-foreground">
                                        <th class="pb-2 pr-4 font-medium">Setting</th>
                                        <th class="pb-2 font-medium">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Name</td>
                                        <td class="py-2 text-muted-foreground">Displayed name of the league.</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Open</td>
                                        <td class="py-2 text-muted-foreground">Whether non-owners can create teams and join.</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Maximum teams</td>
                                        <td class="py-2 text-muted-foreground">Cap on the number of teams. Leave blank for unlimited.</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Set frequency</td>
                                        <td class="py-2 text-muted-foreground">How often matches are scheduled (weekly, biweekly, etc.).</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Pokémon game</td>
                                        <td class="py-2 text-muted-foreground">The game version (e.g. Scarlet/Violet) used for held-item validation.</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Discord webhook</td>
                                        <td class="py-2 text-muted-foreground">Post match result announcements to a Discord channel automatically.</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Replay webhook</td>
                                        <td class="py-2 text-muted-foreground">Separate webhook for broadcasting uploaded replay links.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-2">
                            <h3 class="text-lg font-semibold">League tabs</h3>
                            <ul class="space-y-2 text-sm text-muted-foreground">
                                <li><strong class="text-foreground">Teams</strong> — All teams in the league with their records and draft rosters.</li>
                                <li><strong class="text-foreground">Matches</strong> — All scheduled and completed matches.</li>
                                <li><strong class="text-foreground">Standings</strong> — Pool standings sorted by victory points.</li>
                                <li><strong class="text-foreground">Draft</strong> — Live or historical draft board.</li>
                                <li><strong class="text-foreground">Trades</strong> — Trade proposals and history.</li>
                                <li><strong class="text-foreground">Playoffs</strong> — Bracket view once playoffs are generated.</li>
                            </ul>
                        </div>
                    </section>

                    <hr class="border-border" />

                    <!-- Draft -->
                    <section id="draft" class="space-y-4">
                        <h2 class="text-2xl font-bold tracking-tight">Draft</h2>
                        <p class="text-muted-foreground">VGC Draft uses a <strong>cost-based snake draft</strong>. Each Pokémon has an assigned cost and each team has a budget of draft points to spend.</p>

                        <div class="space-y-3">
                            <h3 class="text-lg font-semibold">Draft configuration</h3>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border text-left text-muted-foreground">
                                        <th class="pb-2 pr-4 font-medium">Option</th>
                                        <th class="pb-2 font-medium">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Rounds</td>
                                        <td class="py-2 text-muted-foreground">Number of pick rounds each team gets.</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Pokémon per team</td>
                                        <td class="py-2 text-muted-foreground">Total Pokémon each team can draft.</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Bans enabled</td>
                                        <td class="py-2 text-muted-foreground">Toggle a ban phase before picks begin.</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Ban phase format</td>
                                        <td class="py-2 text-muted-foreground">Order and quantity of bans per team during the ban phase.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-2">
                            <h3 class="text-lg font-semibold">How drafting works</h3>
                            <ol class="space-y-1 text-sm text-muted-foreground list-decimal list-inside">
                                <li>The league admin starts the draft from the admin panel.</li>
                                <li>If bans are enabled, each team bans Pokémon from the pool in the configured order before any picks are made.</li>
                                <li>Teams pick in snake order: round 1 goes 1→N, round 2 goes N→1, alternating each round.</li>
                                <li>The team whose turn it is selects any available Pokémon. The pick is deducted from their draft point budget.</li>
                                <li>The admin can <strong>revert the last pick</strong> to undo a mistake.</li>
                                <li>The draft ends when all rounds are complete or all teams exhaust their budget.</li>
                            </ol>
                        </div>

                        <div class="rounded-lg border border-border bg-muted/40 p-4 text-sm">
                            <p class="font-medium">Real-time updates</p>
                            <p class="mt-1 text-muted-foreground">When Laravel Reverb is configured, all participants see picks and bans reflected live without needing to refresh the page.</p>
                        </div>
                    </section>

                    <hr class="border-border" />

                    <!-- Teams -->
                    <section id="teams" class="space-y-4">
                        <h2 class="text-2xl font-bold tracking-tight">Teams</h2>
                        <p class="text-muted-foreground">Each team belongs to one league and is owned by one user account.</p>

                        <div class="space-y-2 text-sm text-muted-foreground">
                            <p><strong class="text-foreground">Creating a team</strong> — In an open league, any registered user can create a team. In a closed league, only the owner creates teams and assigns them to users.</p>
                            <p><strong class="text-foreground">Team logo</strong> — Upload a custom image. Logos appear on the team card throughout the app.</p>
                            <p><strong class="text-foreground">Pick position</strong> — The order in which the team picks during the draft. Set by the admin before the draft starts.</p>
                            <p><strong class="text-foreground">Draft points</strong> — The budget a team has to spend on Pokémon. Set per-team or inherited from the league default.</p>
                            <p><strong class="text-foreground">Pool assignment</strong> — Teams can be assigned to round-robin pools for scheduling purposes.</p>
                            <p><strong class="text-foreground">Records</strong> — The team page shows set wins/losses, game wins/losses, and standing within their pool.</p>
                        </div>
                    </section>

                    <hr class="border-border" />

                    <!-- Matches -->
                    <section id="matches" class="space-y-4">
                        <h2 class="text-2xl font-bold tracking-tight">Matches</h2>
                        <p class="text-muted-foreground">Matches (called <em>sets</em> in the app) are scheduled between two teams. Each set tracks scores, rosters, and up to three replay links.</p>

                        <div class="space-y-3">
                            <h3 class="text-lg font-semibold">Match config options</h3>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border text-left text-muted-foreground">
                                        <th class="pb-2 pr-4 font-medium">Option</th>
                                        <th class="pb-2 font-medium">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Format</td>
                                        <td class="py-2 text-muted-foreground">Best-of format (e.g. Bo3, Bo5).</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Frequency type</td>
                                        <td class="py-2 text-muted-foreground">How often matches are generated (weekly, manual, etc.).</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Round count</td>
                                        <td class="py-2 text-muted-foreground">Number of regular season rounds to schedule.</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Minimum drafts</td>
                                        <td class="py-2 text-muted-foreground">Minimum number of drafted Pokémon a team must have before a match roster is valid.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-2 text-sm text-muted-foreground">
                            <p><strong class="text-foreground">Recording a result</strong> — Either player can submit the score. The league admin can reopen a match to correct an error.</p>
                            <p><strong class="text-foreground">Replays</strong> — Upload up to three Pokémon Showdown replay URLs per match. When a Discord replay webhook is configured, links are posted automatically.</p>
                            <p><strong class="text-foreground">Bye rounds</strong> — If a team has no opponent in a round, a bye match is created automatically and does not require a result.</p>
                            <p><strong class="text-foreground">Pools</strong> — Teams in the same pool only play each other during the regular season. Standings are calculated per pool.</p>
                        </div>
                    </section>

                    <hr class="border-border" />

                    <!-- Battle Simulator -->
                    <section id="battle-simulator" class="space-y-4">
                        <h2 class="text-2xl font-bold tracking-tight">Battle Simulator</h2>
                        <p class="text-muted-foreground">
                            VGC Draft includes a built-in 4v4 battle simulator powered by the Pokémon Showdown engine. Both players can battle directly inside the app without leaving to an external site.
                        </p>

                        <div class="space-y-3">
                            <h3 class="text-lg font-semibold">How it works</h3>
                            <ol class="space-y-4">
                                <li class="flex gap-4">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">1</span>
                                    <div>
                                        <p class="font-medium">Navigate to the battle</p>
                                        <p class="text-sm text-muted-foreground">From any match page, click <strong>Battle</strong>. A battle room is created for that match automatically.</p>
                                    </div>
                                </li>
                                <li class="flex gap-4">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">2</span>
                                    <div>
                                        <p class="font-medium">Submit your team</p>
                                        <p class="text-sm text-muted-foreground">Each player pastes their Pokémon Showdown <strong>packed team string</strong> into the team submission panel. The battle does not start until both players have submitted. Your team must only contain Pokémon from your drafted roster.</p>
                                    </div>
                                </li>
                                <li class="flex gap-4">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">3</span>
                                    <div>
                                        <p class="font-medium">Team preview</p>
                                        <p class="text-sm text-muted-foreground">Both players see each other's six Pokémon. Select the order of the four you are bringing into the game by submitting a team order (e.g. <code class="rounded bg-muted px-1 text-xs">team 1 2 3 4</code>).</p>
                                    </div>
                                </li>
                                <li class="flex gap-4">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">4</span>
                                    <div>
                                        <p class="font-medium">Battle</p>
                                        <p class="text-sm text-muted-foreground">Each turn, choose a move or switch from the action panel. Both players' choices are submitted simultaneously. The battle log updates in real time for both players and any spectators.</p>
                                    </div>
                                </li>
                            </ol>
                        </div>

                        <div class="space-y-3">
                            <h3 class="text-lg font-semibold">Getting a packed team string</h3>
                            <p class="text-sm text-muted-foreground">The packed team format is a compact single-line string used by Pokémon Showdown internally. To get yours:</p>
                            <ol class="space-y-1 list-decimal list-inside text-sm text-muted-foreground">
                                <li>Open <strong>Pokémon Showdown → Teambuilder</strong>.</li>
                                <li>Build or import your team using your drafted Pokémon.</li>
                                <li>Click the team name to open the team menu, then choose <strong>Export</strong>.</li>
                                <li>Switch the export format to <strong>Packed</strong>.</li>
                                <li>Copy the string and paste it into the battle team submission panel.</li>
                            </ol>
                        </div>

                        <div class="space-y-3">
                            <h3 class="text-lg font-semibold">Format</h3>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border text-left text-muted-foreground">
                                        <th class="pb-2 pr-4 font-medium">Setting</th>
                                        <th class="pb-2 font-medium">Value</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Format</td>
                                        <td class="py-2 text-muted-foreground">Gen 9 VGC 2024 Regulation G (doubles)</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Team size</td>
                                        <td class="py-2 text-muted-foreground">Bring 6, pick 4 during team preview</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Battle style</td>
                                        <td class="py-2 text-muted-foreground">Simultaneous — both players choose each turn at the same time</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-xs">Real-time updates</td>
                                        <td class="py-2 text-muted-foreground">Battle log streams live to both players via WebSockets (Reverb)</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="rounded-lg border border-border bg-muted/40 p-4 text-sm">
                            <p class="font-medium">Spectating</p>
                            <p class="mt-1 text-muted-foreground">Any league member can open the battle page for a match and watch the battle log update in real time. Spectators cannot submit actions.</p>
                        </div>

                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900 dark:bg-amber-950/30">
                            <p class="font-medium text-amber-900 dark:text-amber-200">Self-hosted requirement</p>
                            <p class="mt-1 text-amber-800 dark:text-amber-300">The battle simulator requires a Node.js battle service running alongside the app. If battles are unavailable, contact your league admin to confirm the service is running.</p>
                        </div>
                    </section>

                    <hr class="border-border" />

                    <!-- Pokepaste -->
                    <section id="pokepaste" class="space-y-4">
                        <h2 class="text-2xl font-bold tracking-tight">Pokepaste & Rosters</h2>
                        <p class="text-muted-foreground">Before or after a match, each team can submit their six-Pokémon roster in Pokémon Showdown paste format. The app validates the paste against the league's draft rules.</p>

                        <div class="space-y-2 text-sm text-muted-foreground">
                            <p><strong class="text-foreground">Submitting a roster</strong> — Paste a valid Showdown-format team string into the pokepaste field on the match page. The app parses the paste and populates the six roster slots.</p>
                            <p><strong class="text-foreground">Validation rules</strong> — Each Pokémon in the paste must exist in the team's drafted pool. Held items are checked against the configured game version. Invalid slots are highlighted with an error.</p>
                            <p><strong class="text-foreground">Slot state</strong> — Each slot is tracked individually. A slot can be empty, valid, or invalid. The team cannot finalize a roster with invalid slots.</p>
                            <p><strong class="text-foreground">Playoff rosters</strong> — The same pokepaste system is used for playoff matches. Rosters are stored separately per match.</p>
                        </div>

                        <div class="rounded-lg border border-border bg-muted/40 p-4 text-sm">
                            <p class="font-medium">Showdown paste format</p>
                            <p class="mt-1 text-muted-foreground">The app accepts the standard Pokémon Showdown export format. You can generate a paste by exporting your team from Showdown's teambuilder or any compatible tool.</p>
                        </div>
                    </section>

                    <hr class="border-border" />

                    <!-- Trades -->
                    <section id="trades" class="space-y-4">
                        <h2 class="text-2xl font-bold tracking-tight">Trades</h2>
                        <p class="text-muted-foreground">Teams can trade drafted Pokémon with each other or pick up undrafted Pokémon through free agency.</p>

                        <div class="space-y-2 text-sm text-muted-foreground">
                            <p><strong class="text-foreground">Proposing a trade</strong> — Navigate to <strong>League → Trades → New Trade</strong>. Select the Pokémon you are offering and the Pokémon you want in return from another team.</p>
                            <p><strong class="text-foreground">Counterparty</strong> — A trade can be directed at a specific team (team-to-team) or submitted as an open offer.</p>
                            <p><strong class="text-foreground">Trade status</strong> — Trades move through <em>pending → accepted / rejected</em>. A league admin can also override a trade's status.</p>
                            <p><strong class="text-foreground">Free agency</strong> — Picking up an undrafted Pokémon from the pool. The target team is the league itself rather than another team. The cost is deducted from the team's remaining draft points.</p>
                            <p><strong class="text-foreground">Admin oversight</strong> — League admins can view all trades and approve or reject any pending trade from the admin trades panel.</p>
                        </div>
                    </section>

                    <hr class="border-border" />

                    <!-- Playoffs -->
                    <section id="playoffs" class="space-y-4">
                        <h2 class="text-2xl font-bold tracking-tight">Playoffs</h2>
                        <p class="text-muted-foreground">Once the regular season ends, the league admin generates a playoff bracket to determine the champion.</p>

                        <div class="space-y-2 text-sm text-muted-foreground">
                            <p><strong class="text-foreground">Generating a bracket</strong> — In <strong>Admin → Playoffs</strong>, choose a bracket size (4, 8, 16, etc.) and seed order. The app creates the bracket structure automatically.</p>
                            <p><strong class="text-foreground">Seeding</strong> — Teams are seeded based on their regular season standing. The admin can customize the seed order before generating.</p>
                            <p><strong class="text-foreground">Recording results</strong> — For each playoff match, submit the score. The winner advances to the next round slot automatically.</p>
                            <p><strong class="text-foreground">Bronze match</strong> — Optionally include a third-place match between the two semifinal losers.</p>
                            <p><strong class="text-foreground">Pokepaste</strong> — Playoff matches support the same roster submission and validation workflow as regular season matches.</p>
                            <p><strong class="text-foreground">League winner</strong> — Once the final is recorded, the admin can mark the league winner from the admin panel.</p>
                        </div>
                    </section>

                    <hr class="border-border" />

                    <!-- Match Prep -->
                    <section id="match-prep" class="space-y-4">
                        <h2 class="text-2xl font-bold tracking-tight">Match Prep</h2>
                        <p class="text-muted-foreground">Match Prep is a private strategy tool. For each scheduled match you can build out team plans, slot selections, damage calculations, and notes — then optionally share them.</p>

                        <div class="space-y-2 text-sm text-muted-foreground">
                            <p><strong class="text-foreground">Bring six</strong> — Select which six Pokémon from your roster you plan to bring to the match.</p>
                            <p><strong class="text-foreground">Team plans</strong> — Build up to three alternate lead/back plans (e.g. Plan A, Plan B, Plan C), each with four-slot selections from your bring-six.</p>
                            <p><strong class="text-foreground">Notes</strong> — Add text notes per plan for reads, win conditions, or opponent scouting.</p>
                            <p><strong class="text-foreground">Calcs</strong> — Store damage calculations inline with your prep notes.</p>
                            <p><strong class="text-foreground">Sharing</strong> — Enable public sharing to generate a unique URL. Anyone with the link can view your prep without logging in. Sharing can be revoked at any time.</p>
                        </div>
                    </section>

                    <hr class="border-border" />

                    <!-- Pokédex & Stats -->
                    <section id="pokedex" class="space-y-4">
                        <h2 class="text-2xl font-bold tracking-tight">Pokédex & Usage Stats</h2>

                        <div class="space-y-2 text-sm text-muted-foreground">
                            <p><strong class="text-foreground">Pokédex</strong> — Browse all Pokémon available in the configured game version. Each entry shows types, sprite, and held items valid for that version. Use this when deciding costs for your league pool.</p>
                            <p><strong class="text-foreground">Pool templates</strong> — Pre-built Pokémon pools with assigned costs. Browse available templates, preview the full list before applying, and import a template directly into your league pool to skip manual entry.</p>
                            <p><strong class="text-foreground">Usage stats</strong> — Aggregated statistics showing which Pokémon are most frequently drafted and used across all leagues. Useful for gauging the meta when setting costs or deciding bans.</p>
                            <p><strong class="text-foreground">Rebuilding stats</strong> — Admins can trigger a stats rebuild via the <code class="rounded bg-muted px-1 text-xs">stats:rebuild</code> Artisan command if data becomes stale.</p>
                        </div>
                    </section>

                    <hr class="border-border" />

                    <!-- Discord -->
                    <section id="discord" class="space-y-4">
                        <h2 class="text-2xl font-bold tracking-tight">Discord Integration</h2>

                        <div class="space-y-2 text-sm text-muted-foreground">
                            <p><strong class="text-foreground">Discord login</strong> — Users can register or log in with their Discord account. On the login page, choose <em>Continue with Discord</em>. If you already have an account, you can link Discord from <strong>Settings → Profile</strong>.</p>
                            <p><strong class="text-foreground">Match result webhook</strong> — In <strong>Admin → Discord</strong>, paste a Discord channel webhook URL. When a match result is submitted, a formatted message is posted to that channel automatically.</p>
                            <p><strong class="text-foreground">Replay webhook</strong> — A separate webhook URL for replay link announcements. Add this if you want replays posted to a different channel from results.</p>
                            <p><strong class="text-foreground">Setting up a webhook</strong> — In Discord, go to your channel settings → Integrations → Webhooks → New Webhook. Copy the URL and paste it into the league admin.</p>
                        </div>
                    </section>

                    <hr class="border-border" />

                    <!-- Admin Reference -->
                    <section id="admin" class="space-y-4">
                        <h2 class="text-2xl font-bold tracking-tight">Admin Reference</h2>
                        <p class="text-muted-foreground">The league admin panel is accessible to the league owner from the league page. Below is a summary of every admin section.</p>

                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left text-muted-foreground">
                                    <th class="pb-2 pr-4 font-medium">Section</th>
                                    <th class="pb-2 font-medium">What you can do</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr>
                                    <td class="py-2.5 pr-4 font-medium">Pokémon Pool</td>
                                    <td class="py-2.5 text-muted-foreground">Add or remove Pokémon from the draft pool, set costs, mark Pokémon as banned.</td>
                                </tr>
                                <tr>
                                    <td class="py-2.5 pr-4 font-medium">Pool Templates</td>
                                    <td class="py-2.5 text-muted-foreground">Apply a saved template to populate the pool in bulk, or export the current pool as a template for reuse.</td>
                                </tr>
                                <tr>
                                    <td class="py-2.5 pr-4 font-medium">Draft Settings</td>
                                    <td class="py-2.5 text-muted-foreground">Configure rounds, Pokémon per team, ban phase rules, and draft point budgets.</td>
                                </tr>
                                <tr>
                                    <td class="py-2.5 pr-4 font-medium">Match Config</td>
                                    <td class="py-2.5 text-muted-foreground">Set the match format, frequency type, round count, and minimum draft requirements.</td>
                                </tr>
                                <tr>
                                    <td class="py-2.5 pr-4 font-medium">Trades</td>
                                    <td class="py-2.5 text-muted-foreground">View all pending trades, approve or reject any trade, and review trade history.</td>
                                </tr>
                                <tr>
                                    <td class="py-2.5 pr-4 font-medium">Playoffs</td>
                                    <td class="py-2.5 text-muted-foreground">Generate bracket, set seed order, record match results, and optionally add a bronze match.</td>
                                </tr>
                                <tr>
                                    <td class="py-2.5 pr-4 font-medium">Discord</td>
                                    <td class="py-2.5 text-muted-foreground">Configure match result and replay webhooks.</td>
                                </tr>
                                <tr>
                                    <td class="py-2.5 pr-4 font-medium">Winner</td>
                                    <td class="py-2.5 text-muted-foreground">Mark the league champion once the final playoff match is complete.</td>
                                </tr>
                                <tr>
                                    <td class="py-2.5 pr-4 font-medium">Reopen Match</td>
                                    <td class="py-2.5 text-muted-foreground">Reopen a closed match to correct a score or roster submission.</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <div class="pt-8 border-t border-border text-sm text-muted-foreground">
                        <p>VGC Draft — built for competitive Pokémon doubles leagues.</p>
                    </div>

                </main>
            </div>
        </div>
    </div>
</template>
