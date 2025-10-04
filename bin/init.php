<?php
// Initialization script for the Sign-up page

// Constants and global variable definition
define("MIGRATION_NAME_PATTERN", '/(\d{4})_(\d{2})_(\d{2})_(\d{2})(\d{2})(\d{2})_(.+)/');
$root_dir = realpath(__DIR__ . "/..");


/**
 * Show the usage information for this script
 */
function usage(): void {
	echo <<<USAGE
	{$_SERVER['SCRIPT_FILENAME']} [-h|--help]

	Initializes the Sign-Up project for the CGHMN.
	Can be run multiple times to upgrade the database on any changes.

	Arguments:
	    -h|--help    Show this help
	
	USAGE;
}

/**
 * Prints a log or error message to the console
 * 
 * @param string $message Message to print out
 * @param bool $is_error If true, writes to the standard error stream
 * @param bool $new_line If true, appends a new line character at the end of the message
 */
function out(string $message, bool $is_error = false, bool $newline = true): void {
	if ($is_error) {
		error_log("<E> {$message}");
		return;
	}

	if ($newline) {
		$message .= PHP_EOL;
	}

	echo "<I> {$message}";
}

/**
 * Wrapper for log to write error messages to console
 * 
 * @param string $message Message to print out
 */
function error_out(string $message) {
	out($message, true);
}


// Parse script command line arguments
$script_options = getopt('h', [ 'help' ]);
foreach($script_options as $option => $value) {
	switch($option) {
		case "h":
		case "help":
			usage();
			exit(0);
		default:
			error_out("Invalid argument: {$option}");
			usage();
			exit(1);
	} 
}



// Check existance of config.php file
if (! is_file("{$root_dir}/config.php")) {
	out("Creating config.php from template ...");
	copy("{$root_dir}/config.example.php", "{$root_dir}/config.php");

	out("Adjust the configuration to match your database configuration and re-run this script to continue.");
	exit(0);
}

// Load configuration
require_once("{$root_dir}/config.php");

// Create database connection
$db = null;
try {
	$db = new mysqli($dbAddr, "adminbot", $restrictedPassword, $dbName);
} catch (Exception $ex) {
	error_out("Unable to open database connection: {$ex->getMessage()}");
}

// Run database migrations
if (is_dir("{$root_dir}/migrations")) {
	out("Running database migrations ...");
	
	// Load timestamp of last run migration script
	$last_run_migration = 0;
	if (is_file("{$root_dir}/.db_last_migration")) {
		$last_run_migration = intval(
			trim(
				file_get_contents("{$root_dir}/.db_last_migration")
			)
		);
	}

	// Load all migration scripts, sort them and call up() on each
	$php_scripts = glob("{$root_dir}/migrations/*.php");
	natsort($php_scripts);

	foreach ($php_scripts as $file) {
		$migration_full_name = basename($file, '.php');

		// Ensure migration matches name pattern
		if (!preg_match(MIGRATION_NAME_PATTERN, $migration_full_name, $name_parts)) {
			error_out("    {$migration_full_name} ... Skipped, invalid name.");
			continue;
		}

		$migration_timestamp = mktime(
			intval($name_parts[4]),
			intval($name_parts[5]),
			intval($name_parts[6]),
			intval($name_parts[2]),
			intval($name_parts[3]),
			intval($name_parts[1]),
		);

		out("    {$migration_full_name} ... ", newline: false);

		if ($migration_timestamp <= $last_run_migration) {
			echo "Skipped." . PHP_EOL;
			continue;
		}

		$migration = require_once($file);
		
		if (!is_object($migration) || !method_exists($migration, 'up')) {
			echo "Skipped, no up method.".PHP_EOL;
			continue;
		}

		$migration->up($db);
		file_put_contents("{$root_dir}/.db_last_migration", $migration_timestamp);

		echo "OK." . PHP_EOL;
	}
}

out("Done!");