#!/usr/bin/env bash
set -euo pipefail

cd -- "$(dirname "${BASH_SOURCE[0]}")" >/dev/null

usage() {
	cat <<-EOF
		$(basename "${0}") [-h] <migration-name>

		Create new database migration

		Arguments:
		    <migration-name>    Name of the migration
	EOF
}

NAME="${1:-}"
if [ -z "${NAME}" ]; then
	echo "Missing argument: <migration-name>" >&2
	usage >&2
	exit 1
fi

# lower-case name and remove spaces
NAME="${NAME,,}"
NAME="${NAME// /_}"

cat >"${PWD}/../migrations/$(date +"%Y_%m_%d_%H%M%S")_${NAME}.php" <<"EOF"
<?php

return new class {
	/**
	 * Applies changes to the database through $db on upgrade
	 *
	 * @param mysqli $db MySQLi Database connection
	 */
	public function up(mysqli $db): void {

	}

	/**
	 * Applies changes to the database through $db on downgrade
	 *
	 * @param mysqli $db MySQLi Database connection
	 */
	public function down(mysqli $db): void {
		// Apply changes to database with $db on downgrade here
	}
};
EOF