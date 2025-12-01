CREATE TABLE IF NOT EXISTS "migrations"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "migration" VARCHAR NOT NULL,
  "batch" INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "name" VARCHAR NOT NULL,
  "email" VARCHAR NOT NULL,
  "password" VARCHAR NOT NULL,
  "remember_token" VARCHAR,
  "created_at" TIMESTAMP,
  "updated_at" TIMESTAMP
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" VARCHAR NOT NULL,
  "token" VARCHAR NOT NULL,
  "created_at" TIMESTAMP,
  PRIMARY KEY("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" VARCHAR NOT NULL,
  "user_id" INTEGER,
  "ip_address" VARCHAR,
  "user_agent" TEXT,
  "payload" TEXT NOT NULL,
  "last_activity" INTEGER NOT NULL,
  PRIMARY KEY("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" VARCHAR NOT NULL,
  "value" TEXT NOT NULL,
  "expiration" INTEGER NOT NULL,
  PRIMARY KEY("key")
);
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" VARCHAR NOT NULL,
  "owner" VARCHAR NOT NULL,
  "expiration" INTEGER NOT NULL,
  PRIMARY KEY("key")
);
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "queue" VARCHAR NOT NULL,
  "payload" TEXT NOT NULL,
  "attempts" INTEGER NOT NULL,
  "reserved_at" INTEGER,
  "available_at" INTEGER NOT NULL,
  "created_at" INTEGER NOT NULL
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" VARCHAR NOT NULL,
  "name" VARCHAR NOT NULL,
  "total_jobs" INTEGER NOT NULL,
  "pending_jobs" INTEGER NOT NULL,
  "failed_jobs" INTEGER NOT NULL,
  "failed_job_ids" TEXT NOT NULL,
  "options" TEXT,
  "cancelled_at" INTEGER,
  "created_at" INTEGER NOT NULL,
  "finished_at" INTEGER,
  PRIMARY KEY("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "uuid" VARCHAR NOT NULL,
  "connection" TEXT NOT NULL,
  "queue" TEXT NOT NULL,
  "payload" TEXT NOT NULL,
  "exception" TEXT NOT NULL,
  "failed_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "pokedex"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "nationaldex_id" REAL NOT NULL,
  "name" VARCHAR NOT NULL,
  "type1" VARCHAR NOT NULL,
  "type2" VARCHAR,
  "sprite_url" VARCHAR,
  "created_at" TIMESTAMP,
  "updated_at" TIMESTAMP
);
CREATE TABLE IF NOT EXISTS "leagues"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "winner" INTEGER,
  "set_frequency" INTEGER NOT NULL DEFAULT 1,
  "name" VARCHAR NOT NULL,
  "logo" VARCHAR,
  "draft_date" DATE,
  "set_start_date" DATE,
  "status" INTEGER NOT NULL DEFAULT 1,
  "draft_points" INTEGER NOT NULL DEFAULT 80,
  "league_owner" INTEGER NOT NULL,
  "created_at" TIMESTAMP,
  "updated_at" TIMESTAMP,
  "format" INTEGER,
  "round_number" INTEGER NOT NULL DEFAULT 0,
  FOREIGN KEY("winner") REFERENCES "users"("id") ON DELETE CASCADE,
  FOREIGN KEY("league_owner") REFERENCES "users"("id") ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS "telescope_entries"(
  "sequence" SERIAL PRIMARY KEY NOT NULL,
  "uuid" VARCHAR NOT NULL,
  "batch_id" VARCHAR NOT NULL,
  "family_hash" VARCHAR,
  "should_display_on_index" BOOLEAN NOT NULL DEFAULT TRUE,
  "type" VARCHAR NOT NULL,
  "content" TEXT NOT NULL,
  "created_at" TIMESTAMP
);
CREATE UNIQUE INDEX "telescope_entries_uuid_unique" on "telescope_entries"(
  "uuid"
);
CREATE INDEX "telescope_entries_batch_id_index" on "telescope_entries"(
  "batch_id"
);
CREATE INDEX "telescope_entries_family_hash_index" on "telescope_entries"(
  "family_hash"
);
CREATE INDEX "telescope_entries_created_at_index" on "telescope_entries"(
  "created_at"
);
CREATE INDEX "telescope_entries_type_should_display_on_index_index" on "telescope_entries"(
  "type",
  "should_display_on_index"
);
CREATE TABLE IF NOT EXISTS "telescope_entries_tags"(
  "entry_uuid" VARCHAR NOT NULL,
  "tag" VARCHAR NOT NULL,
  FOREIGN KEY("entry_uuid") REFERENCES "telescope_entries"("uuid") ON DELETE CASCADE,
  PRIMARY KEY("entry_uuid", "tag")
);
CREATE INDEX "telescope_entries_tags_tag_index" on "telescope_entries_tags"(
  "tag"
);
CREATE TABLE IF NOT EXISTS "telescope_monitoring"(
  "tag" VARCHAR NOT NULL,
  PRIMARY KEY("tag")
);
CREATE TABLE IF NOT EXISTS "league_pokemon"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "league_id" INTEGER NOT NULL,
  "pokedex_id" INTEGER NOT NULL,
  "name" VARCHAR NOT NULL,
  "cost" INTEGER NOT NULL,
  "is_drafted" BOOLEAN NOT NULL DEFAULT FALSE,
  "created_at" TIMESTAMP,
  "updated_at" TIMESTAMP,
  "drafted_by" INTEGER,
  "kos" INTEGER NOT NULL DEFAULT 0,
  FOREIGN KEY("league_id") REFERENCES "leagues"("id") ON DELETE CASCADE,
  FOREIGN KEY("pokedex_id") REFERENCES "pokedex"("id") ON DELETE CASCADE,
  FOREIGN KEY("drafted_by") REFERENCES "teams"("id")
);
CREATE TABLE IF NOT EXISTS "draft_picks"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "draft_id" INTEGER NOT NULL,
  "team_id" INTEGER NOT NULL,
  "league_pokemon_id" INTEGER NOT NULL,
  "round_number" INTEGER NOT NULL,
  "pick_number" INTEGER NOT NULL,
  "created_at" TIMESTAMP,
  "updated_at" TIMESTAMP,
  "league_id" INTEGER,
  FOREIGN KEY("league_pokemon_id") REFERENCES "league_pokemon"("id") ON DELETE CASCADE,
  FOREIGN KEY("team_id") REFERENCES "teams"("id") ON DELETE CASCADE,
  FOREIGN KEY("draft_id") REFERENCES "drafts"("id") ON DELETE CASCADE,
  FOREIGN KEY("league_id") REFERENCES "leagues"("id") ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS "drafts"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "league_id" INTEGER NOT NULL,
  "round_number" INTEGER NOT NULL,
  "status" INTEGER NOT NULL DEFAULT 1,
  "created_at" TIMESTAMP,
  "updated_at" TIMESTAMP,
  "pick_number" INTEGER DEFAULT 0,
  FOREIGN KEY("league_id") REFERENCES "leagues"("id") ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS "draft_order"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "league_id" INTEGER NOT NULL,
  "user_id" INTEGER NOT NULL,
  "pick_number" INTEGER NOT NULL DEFAULT 1,
  "status" INTEGER NOT NULL DEFAULT 1,
  "is_last_pick" INTEGER NOT NULL DEFAULT 0,
  "created_at" TIMESTAMP,
  "updated_at" TIMESTAMP,
  "team_name" VARCHAR NOT NULL,
  "team_id" INTEGER,
  "round_number" INTEGER NOT NULL,
  FOREIGN KEY("user_id") REFERENCES "users"("id") ON DELETE NO ACTION,
  FOREIGN KEY("league_id") REFERENCES "leagues"("id") ON DELETE NO ACTION,
  FOREIGN KEY("team_id") REFERENCES "teams"("id") ON DELETE NO ACTION
);
CREATE TABLE IF NOT EXISTS "pools"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "match_config_id" INTEGER NOT NULL,
  "league_id" INTEGER NOT NULL,
  "status" INTEGER NOT NULL DEFAULT 1,
  "created_at" TIMESTAMP NOT NULL,
  "updated_at" TIMESTAMP NOT NULL,
  FOREIGN KEY("match_config_id") REFERENCES "match_configs"("id") ON DELETE CASCADE,
  FOREIGN KEY("league_id") REFERENCES "leagues"("id") ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS "teams"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "league_id" INTEGER NOT NULL,
  "user_id" INTEGER NOT NULL,
  "name" VARCHAR NOT NULL,
  "pick_position" INTEGER NOT NULL,
  "trades" INTEGER NOT NULL DEFAULT 4,
  "draft_points" INTEGER NOT NULL DEFAULT 0,
  "victory_points" INTEGER NOT NULL DEFAULT 0,
  "set_wins" INTEGER NOT NULL DEFAULT 0,
  "set_losses" INTEGER NOT NULL DEFAULT 0,
  "game_wins" INTEGER NOT NULL DEFAULT 0,
  "game_losses" INTEGER NOT NULL DEFAULT 0,
  "logo" VARCHAR,
  "created_at" TIMESTAMP,
  "updated_at" TIMESTAMP,
  "admin_flag" INTEGER NOT NULL DEFAULT 0,
  "pool_id" INTEGER,
  "seed" INTEGER NOT NULL DEFAULT 1,
  FOREIGN KEY("league_id") REFERENCES "leagues"("id") ON DELETE CASCADE,
  FOREIGN KEY("user_id") REFERENCES "users"("id") ON DELETE CASCADE,
  FOREIGN KEY("pool_id") REFERENCES "pools"("id")
);
CREATE TABLE IF NOT EXISTS "match_configs"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "league_id" INTEGER NOT NULL,
  "number_of_pools" INTEGER NOT NULL DEFAULT 1,
  "frequency_type" INTEGER NOT NULL DEFAULT 1,
  "frequency_value" INTEGER DEFAULT 0,
  "status" INTEGER NOT NULL DEFAULT 1,
  "created_at" TIMESTAMP,
  "updated_at" TIMESTAMP,
  FOREIGN KEY("league_id") REFERENCES "leagues"("id") ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS "sets"(
  "id" SERIAL PRIMARY KEY NOT NULL,
  "league_id" INTEGER NOT NULL,
  "pool_id" INTEGER NOT NULL,
  "round" INTEGER NOT NULL DEFAULT 1,
  "team1_id" INTEGER NOT NULL,
  "team2_id" INTEGER NOT NULL,
  "team1_score" INTEGER,
  "team2_score" INTEGER,
  "team1_pokepaste" VARCHAR,
  "team2_pokepaste" VARCHAR,
  "winner_id" INTEGER,
  "status" INTEGER NOT NULL DEFAULT 1,
  "created_at" TIMESTAMP,
  "updated_at" TIMESTAMP,
  FOREIGN KEY("league_id") REFERENCES "leagues"("id") ON DELETE CASCADE,
  FOREIGN KEY("pool_id") REFERENCES "pools"("id") ON DELETE CASCADE,
  FOREIGN KEY("team1_id") REFERENCES "teams"("id") ON DELETE CASCADE,
  FOREIGN KEY("team2_id") REFERENCES "teams"("id") ON DELETE CASCADE
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2025_04_29_182610_create_pokedex_table',1);
INSERT INTO migrations VALUES(5,'2025_04_29_183714_create_leagues_table',1);
INSERT INTO migrations VALUES(6,'2025_04_29_185355_create_drafts_table',1);
INSERT INTO migrations VALUES(7,'2025_04_29_185462_create_league_pokemon_table',1);
INSERT INTO migrations VALUES(8,'2025_04_29_185803_create_draftpicks_table',1);
INSERT INTO migrations VALUES(9,'2025_07_16_215313_teams',1);
INSERT INTO migrations VALUES(10,'2025_07_19_011156_create_draftorders_table',1);
INSERT INTO migrations VALUES(11,'2025_07_23_113510_create_telescope_entries_table',2);
INSERT INTO migrations VALUES(12,'2025_10_18_192708_drop_draft_orders',3);
INSERT INTO migrations VALUES(13,'2025_10_18_192911_create_draft_orders',4);
INSERT INTO migrations VALUES(14,'2025_10_18_193345_create_draft_order',5);
INSERT INTO migrations VALUES(15,'2025_10_18_193442_create_draft_order',6);
INSERT INTO migrations VALUES(16,'2025_10_18_193550_create_draft_order',7);
INSERT INTO migrations VALUES(17,'2025_10_18_193621_create_draft_order',8);
INSERT INTO migrations VALUES(18,'2025_10_18_193902_create_draft_order',9);
INSERT INTO migrations VALUES(19,'2025_11_03_024226_modify_draft_order',10);
INSERT INTO migrations VALUES(20,'2025_11_03_032624_modify_league_pokemon',11);
INSERT INTO migrations VALUES(21,'2025_11_04_210612_modify_drafts',12);
INSERT INTO migrations VALUES(22,'2025_11_04_211255_modify_draft_order2',13);
INSERT INTO migrations VALUES(23,'2025_11_04_225028_modify_draft_picks',14);
INSERT INTO migrations VALUES(24,'2025_11_05_220703_modify_teams',15);
INSERT INTO migrations VALUES(29,'2025_11_05_221351_modify_teams_again',16);
INSERT INTO migrations VALUES(30,'2025_11_05_232058_modify_draft_picks',17);
INSERT INTO migrations VALUES(31,'2025_11_06_183547_modify_draft_order2',18);
INSERT INTO migrations VALUES(32,'2025_11_06_183711_modify_draft_order3',19);
INSERT INTO migrations VALUES(39,'2025_11_12_191209_modify_leagues',20);
INSERT INTO migrations VALUES(40,'2025_11_12_191555_match_config',20);
INSERT INTO migrations VALUES(41,'2025_11_12_212031_pools',20);
INSERT INTO migrations VALUES(42,'2025_11_12_212159_teams_pools',20);
INSERT INTO migrations VALUES(46,'2025_11_14_013845_match_config_delete',21);
INSERT INTO migrations VALUES(47,'2025_11_14_151210_create_match_configs_table',22);
INSERT INTO migrations VALUES(48,'2025_11_14_152238_league_delete_column',22);
INSERT INTO migrations VALUES(49,'2025_11_20_182106_match_config_delete_column',23);
INSERT INTO migrations VALUES(50,'2025_11_24_152547_match_config_delete_wins_required',24);
INSERT INTO migrations VALUES(51,'2025_11_24_153123_match_config_delete_duration',25);
INSERT INTO migrations VALUES(54,'2025_11_24_180653_sets',26);
INSERT INTO migrations VALUES(55,'2025_11_24_214305_leagues_rounds',27);
INSERT INTO migrations VALUES(56,'2025_12_01_225622_league_pokemon__k_os',28);
