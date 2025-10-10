<?php
# Don't access common.php directly.
if ($_SERVER["SCRIPT_FILENAME"] == "common.php") {
    http_response_code(403);
    die();
}

# Permissions (INVERTED bit mask)
$perm = array(
    "manage-admins" => 1,
    "manage-users" => 2,
    "manage-requests" => 4
);

# Does what it says
function print_fatal_error($error) {
    $errorTemplate =
    "<!DOCTYPE html>
    <html>
        <head>
            <title>CGHMN Error Page</title>
        </head>
        <body>
            <p>Sorry, %s Please try again later.</p>
        </body>
    </html>";
    printf($errorTemplate, $error);
    die();
}

# Check if the current user is valid.
function check_auth() {
    global $restrictedPassword;
    global $dbAddr;
    global $dbName;

    if (!isset($_SESSION["userId"]) || !is_numeric($_SESSION["userId"]) ||
        !isset($_SESSION["userName"]) || !is_string($_SESSION["userName"]) ||
        !isset($_SESSION["permissions"]) || !is_numeric($_SESSION["permissions"]) ||
        !isset($_SESSION["expires"]) || !is_numeric($_SESSION["expires"])) {
        return false;
    }

    # We only check against the DB every 5 mins.
    if ($_SESSION["expires"] > time()) {
        return true;
    }

    $sqlconn = new mysqli($dbAddr, "submitbot", $restrictedPassword, $dbName);
    if ($sqlconn->connect_error) {
        print_fatal_error("we're experiencing a database failure.");
    }

    # Look for the user
    $stmt = $sqlconn->prepare("SELECT Username, Permissions FROM Admins WHERE ID = ?");
    $stmt->bind_param("i", $_SESSION["userId"]);
    if (!$stmt->execute()) {
        print_fatal_error("we've experienced a database failure.");
    }

    # Retrieve & update their permissions
    $stmt->bind_result($uName, $perms);
    if (!$stmt->fetch()) {
        header("Location: ./");
        die();
    }

    # Update their permissions
    $_SESSION["userName"] = $uName;
    $_SESSION["permissions"] = $perms;

    # Wait another 5 mins to check again.
    $_SESSION["expires"] = time() + 300;

    $sqlconn->close();
    return true;
}

function print_header($title) {
    $pages = array(
        "Requests" => "admin.php",
        "Past Reqs" => "past.php",
        "Users" => "users.php",
        "Admins" => "admins.php"
    );
    echo
    "<!DOCTYPE html>
    <html>
    <head>
    <title>{$title}</title>
    <link rel=\"stylesheet\" href=\"common.css\" />
    <link rel=\"stylesheet\" href=\"../theme/cghmn.css\">
    </head>
    <body>
    <div id=\"topbar\">
    <h1 id=\"titlebar\">{$title}</h1>
    <nav id=\"menubar\">
    <ol>";
    foreach ($pages as $name => $url) {
        echo "<a class=\"menu-item\" href={$url}><li>{$name}</li></a>";
    }
    echo "<a class=\"menu-item\" style=\"float: right;\" href=\"logout.php\"><li>Log Out</li></a>";
    echo "</ol></nav></div>";
}

/*
Takes the user info as an associative array
Fields: 
 * username
 * email
 * tunnelIP
 * presharedKey
 * routedSubnet
 * exampleConfig*/
function send_confirmation_email($user) {
    global $email;
    global $contactEmail;

    # Create the Email contents
    $body =
    "Dear {$user["username"]},\r\n" .
    "Welcome to CGHMN!\r\n" .
    "Your tunnel IP is {$user["tunnelIP"]},\r\n" .
    "Your WireGuard Preshared Key is {$user["presharedKey"]},\r\n" . 
    "And your routed subnet is {$user["routedSubnet"]}.\r\n" .
    "Here's an example config you can use:\r\n---\r\n" .
    $user["exampleConfig"] . "\r\n---\r\n" .
    "If you're not sure how to set up your CGHMN Router,\r\n" .
    "you can find some beginner-friendly instructions at:\r\n" .
    "https://wiki.cursedsilicon.net/wiki/Signup\r\n" .
    "If you need help with anything,\r\n" .
    "feel free to reach out at\r\n" .
    "$contactEmail";

    $headers =
    "From: $email\r\n" .
    "Reply-To: $contactEmail\r\n";

    return mail($user["email"], "Welcome to CGHMN!", $body, $headers);
}

function print_footer() {
    echo "<p>(C) 2025 CGHMN.</p></body></html>";
}
?>