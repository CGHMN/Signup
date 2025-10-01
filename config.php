<?php
# Don't access config.php directly.
if ($_SERVER["SCRIPT_FILENAME"] == "config.php") {
    http_response_code(403);
    die();
}

# Password for the restricted SQL user, submitbot (used for account requests)
$restrictedPassword = "<password>";

# Password for the unrestricted SQL user, adminbot (used for admin tasks)
$unrestrictedPassword = "<password>"";

# Hostname/IP Address of the MySQL DB to connect to.
$dbAddr = "<database address>";

# Name of the database to use.
$dbName = "<database name>";

# URL of the router API
$router = "<router>";

# Router API key
$rtrAPIKey = "<router API key>";
?>
