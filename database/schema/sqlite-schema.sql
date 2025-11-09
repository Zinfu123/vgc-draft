CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "pokedex"(
  "id" integer primary key autoincrement not null,
  "nationaldex_id" float not null,
  "name" varchar not null,
  "type1" varchar not null,
  "type2" varchar,
  "sprite_url" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "leagues"(
  "id" integer primary key autoincrement not null,
  "winner" integer,
  "set_frequency" integer not null default '1',
  "name" varchar not null,
  "logo" varchar,
  "draft_date" date,
  "set_start_date" date,
  "status" integer not null default '1',
  "draft_points" integer not null default '80',
  "league_owner" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("winner") references "users"("id") on delete cascade,
  foreign key("league_owner") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "telescope_entries"(
  "sequence" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "batch_id" varchar not null,
  "family_hash" varchar,
  "should_display_on_index" tinyint(1) not null default '1',
  "type" varchar not null,
  "content" text not null,
  "created_at" datetime
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
  "entry_uuid" varchar not null,
  "tag" varchar not null,
  foreign key("entry_uuid") references "telescope_entries"("uuid") on delete cascade,
  primary key("entry_uuid", "tag")
);
CREATE INDEX "telescope_entries_tags_tag_index" on "telescope_entries_tags"(
  "tag"
);
CREATE TABLE IF NOT EXISTS "telescope_monitoring"(
  "tag" varchar not null,
  primary key("tag")
);
CREATE TABLE IF NOT EXISTS "league_pokemon"(
  "id" integer primary key autoincrement not null,
  "league_id" integer not null,
  "pokedex_id" integer not null,
  "name" varchar not null,
  "cost" integer not null,
  "is_drafted" tinyint(1) not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "drafted_by" integer,
  foreign key("league_id") references leagues("id") on delete cascade on update no action,
  foreign key("pokedex_id") references pokedex("id") on delete cascade on update no action,
  foreign key("drafted_by") references "teams"("id")
);
CREATE TABLE IF NOT EXISTS "draft_picks"(
  "id" integer primary key autoincrement not null,
  "draft_id" integer not null,
  "team_id" integer not null,
  "league_pokemon_id" integer not null,
  "round_number" integer not null,
  "pick_number" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  "league_id" integer,
  foreign key("league_pokemon_id") references league_pokemon("id") on delete cascade on update no action,
  foreign key("team_id") references teams("id") on delete cascade on update no action,
  foreign key("draft_id") references drafts("id") on delete cascade on update no action,
  foreign key("league_id") references "leagues"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "teams"(
  "id" integer primary key autoincrement not null,
  "league_id" integer not null,
  "user_id" integer not null,
  "name" varchar not null,
  "pick_position" integer not null,
  "trades" integer not null default('4'),
  "draft_points" integer not null default('0'),
  "victory_points" integer not null default('0'),
  "set_wins" integer not null default('0'),
  "set_losses" integer not null default('0'),
  "game_wins" integer not null default('0'),
  "game_losses" integer not null default('0'),
  "logo" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "admin_flag" integer not null default '0',
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("league_id") references leagues("id") on delete cascade on update no action
);
CREATE TABLE IF NOT EXISTS "drafts"(
  "id" integer primary key autoincrement not null,
  "league_id" integer not null,
  "round_number" integer not null,
  "status" integer not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "pick_number" integer default('0'),
  foreign key("league_id") references leagues("id") on delete cascade on update no action
);
CREATE TABLE IF NOT EXISTS "draft_order"(
  "id" integer primary key autoincrement not null,
  "league_id" integer not null,
  "user_id" integer not null,
  "pick_number" integer not null default('1'),
  "status" integer not null default('1'),
  "is_last_pick" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "team_name" varchar not null,
  "team_id" integer,
  "round_number" integer not null,
  foreign key("user_id") references users("id") on delete no action on update no action,
  foreign key("league_id") references leagues("id") on delete no action on update no action,
  foreign key("team_id") references teams("id") on delete no action on update no action
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
