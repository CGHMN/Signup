<?php
session_start();
require __DIR__.'/../../config.php';
require 'common.php';

if (!check_auth()) {
    header("Location: ./");
    die();
}

# Handle the submitted user info.
function verify_and_add() {
    global $unrestrictedPassword;
    global $dbAddr;
    global $dbName;
    global $perm;

    if (isset($_POST["username"]) && isset($_POST["password-1"]) && isset($_POST["password-2"])) {
        # Make sure the user input is safe.
        if (!is_string($_POST["username"]) || $_POST["username"] === "") {
            return "you must enter a username.";
        }
        if (!preg_match("/^[a-z]\w{0, 64}$/i", $_POST["username"])) {
            return "you entered an invalid username.";
        }
        if (!is_string($_POST["password-1"]) || !is_string($_POST["password-2"]) || $_POST["password-1"] === "" || $_POST["password-2"] === "") {
            return "you must enter a password.";
        }
        if ($_POST["password-1"] !== $_POST["password-2"]) {
            return "passwords do not match.";
        }
        # Figure out the permissions for this new user.
        $perms = 0;
        if (!isset($_POST["manage-reqs"]) || !is_string($_POST["manage-reqs"]) || $_POST["manage-reqs"] !== "yes") {
            $perms |= $perm["manage-requests"];
        }
        if (!isset($_POST["manage-users"]) || !is_string($_POST["manage-users"]) || $_POST["manage-users"] !== "yes") {
            $perms |= $perm["manage-users"];
        }
        if (!isset($_POST["manage-admins"]) || !is_string($_POST["manage-admins"]) || $_POST["manage-admins"] !== "yes") {
            $perms |= $perm["manage-admins"];
        }
        
        # Add the new user to the DB.
        $sqlconn = new mysqli($dbAddr, "adminbot", $unrestrictedPassword);
        if ($sqlconn->connect_error) {
            return "failed to connect to database.";
        }
        $passwordHash = password_hash($_POST["password-1"], PASSWORD_DEFAULT);
        $misc = "{}";
        $stmt = $sqlconn->prepare("REPLACE INTO $dbName.Admins (Username, Password, Permissions, Misc) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $_POST["username"], $passwordHash, $perms, $misc);
        try {
            if ($stmt->execute()) {
                $sqlconn->close();
                return false;
            }
        } catch(Exception $e) {
            $sqlconn->close();
            return "something went wrong submitting your request.";
        }

        # Clean up.
        $sqlconn->close();
    }
    # Ignore invalid POST requests.
    return null;
}

if ($_POST) {
    # Make sure that the user has permission to manage admins.
    if ($_SESSION["permissions"] & $perms["manage-admins"]) {
        $_SESSION["lastAction"] = array("name" => "create_admin", "ret" => "you are not allowed to do that.");
    } else {
        $_SESSION["lastAction"] = array("name" => "create_admin", "ret" => verify_and_add());
    }
}

header("Location: admins.php");
die();
?>