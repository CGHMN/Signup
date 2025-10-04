<?php
session_start();
require __DIR__.'/../../config.php';
require 'common.php';

if (!check_auth()) {
    header("Location: ./");
    die();
}
# Handle the submitted user info.
function verify_and_update() {
    global $unrestrictedPassword;
    global $dbAddr;
    global $dbName;

    if (isset($_POST["cur-pass"]) || isset($_POST["new-pass-1"]) && isset($_POST["new-pass-2"])) {
        # Make sure the user input is safe.
        if (!is_string($_POST["cur-pass"])) {
            return array("type" => 1, "msg" => "you must enter your current password.");
        }
        if (!is_string($_POST["new-pass-1"]) || $_POST["new-pass-1"] === "") {
            return array("type" => 1, "msg" => "you must enter a new password.");
        }
        if ($_POST["new-pass-1"] !== $_POST["new-pass-2"]) {
            return array("type" => 1, "msg" => "your passwords do not match.");
        }

        # Check the user's current password.
        $sqlconn = new mysqli($dbAddr, "adminbot", $unrestrictedPassword);
        if ($sqlconn->connect_error) {
            return array("type" => 1, "msg" => "failed to connect to database.");
        }

        $stmt = $sqlconn->prepare("SELECT Password FROM $dbName.Admins WHERE ID = ?");
        $stmt->bind_param("i", $_SESSION["userId"]);
        if (!$stmt->execute()) {
            $sqlconn->close();
            return array("type" => 1, "msg" => "we've experienced a database failure.");
        }
        $stmt->bind_result($pass);
        if (!$stmt->fetch()) {
            $sqlconn->close();
            return array("type" => 1, "msg" => "we were unable to verify your password.");
        }
        if (!password_verify($_POST["cur-pass"], $pass)) {
            $sqlconn->close();
            return array("type" => 1, "msg" => "incorrect password.");
        }
        $stmt->close();

        # Update the user's password.
        $passwordHash = password_hash($_POST["new-pass-1"], PASSWORD_DEFAULT);
        $perms = 0;
        $misc = "{}";
        $stmt = $sqlconn->prepare("UPDATE $dbName.Admins SET Password = ? WHERE ID = ?");
        $stmt->bind_param("si", $passwordHash, $_SESSION["userId"]);
        try {
            if ($stmt->execute()) {
                $sqlconn->close();
                return array("type" => 1, "msg" => false);
            }
        } catch(Exception $e) {
            $sqlconn->close();
            return array ("type" => 1, "msg" => "something went wrong submitting your request.");
        }

        # Clean up.
        $sqlconn->close();
    }
    # Ignore invalid POST requests.
    return null;
}
if ($_POST) {
    $_SESSION["lastAction"] = array("name" => "change_password", "ret" => verify_and_update());
}

header("Location: admins.php");
die();
?>