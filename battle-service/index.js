const express = require('express');
const { BattleStream } = require('@pkmn/sim');

const app = express();
app.use(express.json());

const PORT = process.env.BATTLE_SERVICE_PORT || 3001;

/**
 * In-memory battle store.
 *
 * Each entry: { stream, log: string[], waitQueue: WaitEntry[] }
 * WaitEntry: { chunks: string[], resolve: fn, isDone: fn }
 *
 * In production, replace with Redis-backed serialisation.
 */
const battles = new Map();

/**
 * Start an async read loop for a battle session.
 * Resolves waiting HTTP requests when a terminal chunk arrives.
 */
function startReadLoop(session) {
    (async () => {
        for await (const chunk of session.stream) {
            session.log.push(chunk);

            if (session.waitQueue.length === 0) {
                continue;
            }

            const waiter = session.waitQueue[0];
            waiter.chunks.push(chunk);

            if (waiter.isDone(chunk)) {
                session.waitQueue.shift();
                waiter.resolve({ output: waiter.chunks, log: session.log });
            }
        }
    })();
}

/**
 * Write a command to a battle and wait for a terminal chunk.
 * isDone(chunk) => bool — called on each output chunk to decide when to resolve.
 */
function writeAndWait(session, command, isDone) {
    return new Promise((resolve) => {
        session.waitQueue.push({ chunks: [], resolve, isDone });
        session.stream.write(command);
    });
}

/**
 * Terminal conditions — resolves the waiter when any of these appear.
 */
function isTerminal(chunk) {
    return (
        chunk.includes('|turn|') ||
        chunk.includes('|win|') ||
        chunk.includes('|tie|') ||
        chunk.includes('|error|') ||
        chunk.includes('|teampreview')
    );
}

function isActionTerminal(chunk) {
    return (
        chunk.includes('|turn|') ||
        chunk.includes('|win|') ||
        chunk.includes('|tie|') ||
        chunk.includes('|error|')
    );
}

// ─── Routes ─────────────────────────────────────────────────────────────────

/**
 * POST /battle
 * Body: { battleId, format, p1Name, p1Team, p2Name, p2Team }
 * p1Team / p2Team: PS packed team string
 */
app.post('/battle', async (req, res) => {
    const { battleId, format, p1Name, p1Team, p2Name, p2Team } = req.body;

    if (!battleId || !p1Team || !p2Team) {
        return res.status(422).json({ error: 'battleId, p1Team, and p2Team are required' });
    }

    if (battles.has(String(battleId))) {
        return res.status(409).json({ error: 'Battle already exists' });
    }

    const stream = new BattleStream();
    const session = { stream, log: [], waitQueue: [] };
    battles.set(String(battleId), session);

    startReadLoop(session);

    try {
        // Initialise the battle; team preview is the first terminal chunk
        const startResult = await writeAndWait(
            session,
            `>start {"formatid":"${format ?? 'gen9vgc2024regg'}","p1":{"name":"${p1Name}"},"p2":{"name":"${p2Name}"}}`,
            isTerminal,
        );

        // Register both players
        session.stream.write(`>player p1 {"name":"${p1Name}","team":"${p1Team}"}`);
        session.stream.write(`>player p2 {"name":"${p2Name}","team":"${p2Team}"}`);

        // Wait for team preview or turn 1
        const playerResult = await new Promise((resolve) => {
            session.waitQueue.push({ chunks: [], resolve, isDone: isTerminal });
        });

        return res.json({ battleId, output: [...startResult.output, ...playerResult.output], log: session.log });
    } catch (err) {
        battles.delete(String(battleId));
        return res.status(500).json({ error: err.message });
    }
});

/**
 * POST /battle/:battleId/action
 * Body: { player: 'p1'|'p2', action: string }
 * action examples: "move 1", "move 2 mega", "switch 3", "team 1 2 3 4"
 */
app.post('/battle/:battleId/action', async (req, res) => {
    const { player, action } = req.body;
    const session = battles.get(req.params.battleId);

    if (!session) {
        return res.status(404).json({ error: 'Battle not found' });
    }

    if (!player || !action) {
        return res.status(422).json({ error: 'player and action are required' });
    }

    try {
        const result = await writeAndWait(session, `>${player} ${action}`, isActionTerminal);
        return res.json(result);
    } catch (err) {
        return res.status(500).json({ error: err.message });
    }
});

/**
 * GET /battle/:battleId
 * Returns the full battle log.
 */
app.get('/battle/:battleId', (req, res) => {
    const session = battles.get(req.params.battleId);

    if (!session) {
        return res.status(404).json({ error: 'Battle not found' });
    }

    return res.json({ battleId: req.params.battleId, log: session.log });
});

/**
 * DELETE /battle/:battleId
 * Cleans up a finished battle from memory.
 */
app.delete('/battle/:battleId', (req, res) => {
    const existed = battles.delete(req.params.battleId);
    return res.json({ deleted: existed });
});

// ─── Health ──────────────────────────────────────────────────────────────────

app.get('/health', (_, res) => res.json({ status: 'ok', battles: battles.size }));

app.listen(PORT, () => {
    console.log(`Battle service running on port ${PORT}`);
});
