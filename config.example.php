<?php
// Example project configuration file

# Password for the restricted SQL user, submitbot (used for account requests)
$restrictedPassword = "changeme";

# Password for the unrestricted SQL user, adminbot (used for admin tasks)
$unrestrictedPassword = "changeme";

# Hostname/IP Address of the MySQL DB to connect to.
$dbAddr = "127.0.0.1";

# Name of the database to use.
$dbName = "cghmn_signups";

# URL of the router API
$router = "<router>/api/v1/";

# Router API key
$rtrAPIKey = "<router API key>";

# Email address to use for emailing
$email = "noreply@your-domain.com";

# Email address to direct users to contact
$contactEmail = "contact@your-domain.com";