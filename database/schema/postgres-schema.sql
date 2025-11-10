CREATE TABLE IF NOT EXISTS "migrations" (
  "id" serial primary key,
  "migration" varchar not null,
  "batch" integer not null
);

CREATE TABLE IF NOT EXISTS "users" (
  "id" serial primary key,
  "name" varchar not null,
  "email" varchar not null,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" timestamp,
  "updated_at" timestamp
);

CREATE UNIQUE INDEX "users_email_unique" ON "users" ("email");

CREATE TABLE IF NOT EXISTS "password_reset_tokens" (
  "email" varchar not null,
  "token" varchar not null,
  "created_at" timestamp,
  primary key ("email")
);

CREATE TABLE IF NOT EXISTS "sessions" (
  "id" varchar primary key,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  foreign key ("user_id") references "users" ("id") on delete cascade
);

CREATE INDEX "sessions_user_id_index" ON "sessions" ("user_id");
CREATE INDEX "sessions_last_activity_index" ON "sessions" ("last_activity");

CREATE TABLE IF NOT EXISTS "cache" (
  "key" varchar primary key,
  "value" text not null,
  "expiration" integer not null
);

CREATE TABLE IF NOT EXISTS "cache_locks" (
  "key" varchar primary key,
  "owner" varchar not null,
  "expiration" integer not null
);

CREATE TABLE IF NOT EXISTS "jobs" (
  "id" serial primary key,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);

CREATE INDEX "jobs_queue_index" ON "jobs" ("queue");

CREATE TABLE IF NOT EXISTS "job_batches" (
  "id" varchar primary key,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer
);

CREATE TABLE IF NOT EXISTS "failed_jobs" (
  "id" serial primary key,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" timestamp not null default CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX "failed_jobs_uuid_unique" ON "failed_jobs" ("uuid");

CREATE TABLE IF NOT EXISTS "pokedex" (
  "id" serial primary key,
  "nationaldex_id" real not null,
  "name" varchar not null,
  "type1" varchar not null,
  "type2" varchar,
  "sprite_url" varchar,
  "created_at" timestamp,
  "updated_at" timestamp
);

CREATE TABLE IF NOT EXISTS "leagues" (
  "id" serial primary key,
  "winner" integer,
  "set_frequency" integer not null default 1,
  "name" varchar not null,
  "logo" varchar,
  "draft_date" date,
  "set_start_date" date,
  "status" integer not null default 1,
  "draft_points" integer not null default 80,
  "league_owner" integer not null,
  "created_at" timestamp,
  "updated_at" timestamp,
  foreign key ("winner") references "users" ("id") on delete cascade,
  foreign key ("league_owner") references "users" ("id") on delete cascade
);


CREATE TABLE IF NOT EXISTS "teams" (
  "id" serial primary key,
  "league_id" integer not null,
  "user_id" integer not null,
  "name" varchar not null,
  "pick_position" integer not null,
  "trades" integer not null default 4,
  "draft_points" integer not null default 0,
  "victory_points" integer not null default 0,
  "set_wins" integer not null default 0,
  "set_losses" integer not null default 0,
  "game_wins" integer not null default 0,
  "game_losses" integer not null default 0,
  "logo" varchar,
  "created_at" timestamp,
  "updated_at" timestamp,
  "admin_flag" boolean not null default false,
  foreign key ("user_id") references "users" ("id") on delete cascade,
  foreign key ("league_id") references "leagues" ("id") on delete cascade
);

CREATE TABLE IF NOT EXISTS "league_pokemon" (
  "id" serial primary key,
  "league_id" integer not null,
  "pokedex_id" integer not null,
  "name" varchar not null,
  "cost" integer not null,
  "is_drafted" boolean not null default false,
  "created_at" timestamp,
  "updated_at" timestamp,
  "drafted_by" integer,
  foreign key ("league_id") references "leagues" ("id") on delete cascade,
  foreign key ("pokedex_id") references "pokedex" ("id") on delete cascade,
  foreign key ("drafted_by") references "teams" ("id")
);


CREATE TABLE IF NOT EXISTS "drafts" (
  "id" serial primary key,
  "league_id" integer not null,
  "round_number" integer not null,
  "status" integer not null default 1,
  "created_at" timestamp,
  "updated_at" timestamp,
  "pick_number" integer default 0,
  foreign key ("league_id") references "leagues" ("id") on delete cascade
);

CREATE TABLE IF NOT EXISTS "draft_picks" (
  "id" serial primary key,
  "draft_id" integer not null,
  "team_id" integer not null,
  "league_pokemon_id" integer not null,
  "round_number" integer not null,
  "pick_number" integer not null,
  "created_at" timestamp,
  "updated_at" timestamp,
  "league_id" integer,
  foreign key ("league_pokemon_id") references "league_pokemon" ("id") on delete cascade,
  foreign key ("team_id") references "teams" ("id") on delete cascade,
  foreign key ("draft_id") references "drafts" ("id") on delete cascade,
  foreign key ("league_id") references "leagues" ("id") on delete cascade
);

CREATE TABLE IF NOT EXISTS "draft_order" (
  "id" serial primary key,
  "league_id" integer not null,
  "user_id" integer not null,
  "pick_number" integer not null default 1,
  "status" integer not null default 1,
  "is_last_pick" boolean not null default false,
  "created_at" timestamp,
  "updated_at" timestamp,
  "team_name" varchar not null,
  "team_id" integer,
  "round_number" integer not null,
  foreign key ("user_id") references "users" ("id") on delete no action,
  foreign key ("league_id") references "leagues" ("id") on delete no action,
  foreign key ("team_id") references "teams" ("id") on delete no action
);

INSERT INTO "migrations" VALUES
  (1, '0001_01_01_000000_create_users_table', 1),
  (2, '0001_01_01_000001_create_cache_table', 1),
  (3, '0001_01_01_000002_create_jobs_table', 1),
  (4, '2025_04_29_182610_create_pokedex_table', 1),
  (5, '2025_04_29_183714_create_leagues_table', 1),
  (6, '2025_04_29_185355_create_drafts_table', 1),
  (7, '2025_04_29_185462_create_league_pokemon_table', 1),
  (8, '2025_04_29_185803_create_draftpicks_table', 1),
  (9, '2025_07_16_215313_teams', 1),
  (10, '2025_07_19_011156_create_draftorders_table', 1),
  (11, '2025_07_23_113510_create_telescope_entries_table', 2),
  (12, '2025_10_18_192708_drop_draft_orders', 3),
  (13, '2025_10_18_192911_create_draft_orders', 4),
  (14, '2025_10_18_193345_create_draft_order', 5),
  (15, '2025_10_18_193442_create_draft_order', 6),
  (16, '2025_10_18_193550_create_draft_order', 7),
  (17, '2025_10_18_193621_create_draft_order', 8),
  (18, '2025_10_18_193902_create_draft_order', 9),
  (19, '2025_11_03_024226_modify_draft_order', 10),
  (20, '2025_11_03_032624_modify_league_pokemon', 11),
  (21, '2025_11_04_210612_modify_drafts', 12),
  (22, '2025_11_04_211255_modify_draft_order2', 13),
  (23, '2025_11_04_225028_modify_draft_picks', 14),
  (24, '2025_11_05_220703_modify_teams', 15),
  (29, '2025_11_05_221351_modify_teams_again', 16),
  (30, '2025_11_05_232058_modify_draft_picks', 17),
  (31, '2025_11_06_183547_modify_draft_order2', 18),
  (32, '2025_11_06_183711_modify_draft_order3', 19);

