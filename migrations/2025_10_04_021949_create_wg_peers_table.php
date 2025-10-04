<?php

return new class {
	/**
	 * Applies changes to the database through $db on upgrade
	 *
	 * @param mysqli $db MySQLi Database connection
	 */
	public function up(mysqli $db): void {
		$db->query(<<<SQL
			CREATE TABLE WG_Peers (
				ID BIGINT NOT NULL AUTO_INCREMENT,
				UserID BIGINT NOT NULL,
				TunnelIP TEXT NOT NULL,
				AllowedIPs TEXT NOT NULL,
				Pubkey TEXT NOT NULL,
				PSK TEXT DEFAULT "",

				PRIMARY KEY (ID),
				FOREIGN KEY (UserID) REFERENCES Users(ID),
				INDEX idx_userid (UserID)
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
			DROP TABLE WG_Peers;
		SQL);
	}
};
