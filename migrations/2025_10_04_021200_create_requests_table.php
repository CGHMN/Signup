<?php

return new class {
	/**
	 * Applies changes to the database through $db on upgrade
	 *
	 * @param mysqli $db MySQLi Database connection
	 */
	public function up(mysqli $db): void {
		$db->query(<<<SQL
			CREATE TABLE Requests (
				ID BIGINT NOT NULL AUTO_INCREMENT,
				Status INT NOT NULL,
				Username VARCHAR (64) NOT NULL,
				Email VARCHAR (254) NOT NULL,
				Pubkey TEXT NOT NULL,
				Plan TEXT NOT NULL,
				Hosting BOOLEAN DEFAULT 0,
				Experience BOOLEAN DEFAULT NULL,
				Contact TEXT NOT NULL,
				Contact_Details TEXT NOT NULL,

				PRIMARY KEY (ID),
				UNIQUE (Username),
				UNIQUE (Email),
				INDEX idx_username (Username),
				INDEX idx_status (Status)
			);
		SQL);
	}

	/**
	 * Applies changes to the database through $db on downgrade
	 *
	 * @param mysqli $db MySQLi Database connection
	 */
	public function down(mysqli $db): void {
		$db->query(<<<SQL
			DROP TABLE Requests;
		SQL);
	}
};
