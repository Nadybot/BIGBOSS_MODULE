CREATE TABLE IF NOT EXISTS bigboss_timers (
	`mob_name` VARCHAR(50) NOT NULL PRIMARY KEY,
	`timer` INTEGER NOT NULL,
	`spawn` INTEGER NOT NULL,
	`killable` INTEGER NOT NULL,
	`time_submitted` INTEGER NOT NULL,
	`submitter_name` VARCHAR(25) NOT NULL
);
