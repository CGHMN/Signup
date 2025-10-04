<?php

return new class {
	/**
	 * Applies changes to the database through $db on upgrade
	 *
	 * @param mysqli $db MySQLi Database connection
	 */
	public function up(mysqli $db): void {
		$db->query(<<<SQL
			CREATE TABLE Admins (
				ID BIGINT NOT NULL AUTO_INCREMENT,
				Username VARCHAR (64) NOT NULL,
				Password TEXT NOT NULL,
				Permission INT NOT NULL,

				PRIMARY KEY (ID),
				UNIQUE (Username),
				INDEX idx_username (Username)
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
			DROP TABLE Admins;
		SQL);
	}
};
