<?php

return new class
{
	/**
	 * Adds initial data to the database
	 *
	 * @param mysqli $db MySQLi Database connection
	 */
	public function seed(mysqli $db): void
	{
		global $initial_admin_password;
		if (is_null($initial_admin_password)) return;

		$statement = $db->prepare(<<<SQL
			INSERT INTO Admins (
				Username,
				Password,
				Permission
			) VALUES (
				"admin",
				?,
				0
			);
		SQL);

		$password_hash = password_hash($initial_admin_password, PASSWORD_DEFAULT);
		
		$statement->bind_param('s', $password_hash);
		$statement->execute();
	}
};
