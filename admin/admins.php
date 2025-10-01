<?php
session_start();
require '../config.php';
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

    if (isset($_POST["username"]) && isset($_POST["password-1"]) && isset($_POST["password-2"])) {
        # Make sure the user input is safe.
        if (!is_string($_POST["username"]) || $_POST["username"] === "") {
            return array("type" => 0, "msg" => "you must enter a username.");
        }
        if (!preg_match("/^[a-z]\w{0, 64}$/i", $_POST["username"])) {
            return array("type" => 0, "msg" => "you entered an invalid username.");
        }
        if (!is_string($_POST["password-1"]) || !is_string($_POST["password-2"]) || $_POST["password-1"] === "" || $_POST["password-2"] === "") {
            return array("type" => 0, "msg" => "you must enter a password.");
        }
        if ($_POST["password-1"] !== $_POST["password-2"]) {
            return array("type" => 0, "msg" => "passwords do not match.");
        }
        
        # Add the new user to the DB.
        $sqlconn = new mysqli($dbAddr, "adminbot", $unrestrictedPassword);
        if ($sqlconn->connect_error) {
            return array("type" => 0, "msg" => "failed to connect to database.");
        }
        $passwordHash = password_hash($_POST["password-1"], PASSWORD_DEFAULT);
        $perms = 0;
        $misc = "{}";
        $stmt = $sqlconn->prepare("REPLACE INTO $dbName.Admins (Username, Password, Permissions, Misc) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $_POST["username"], $passwordHash, $perms, $misc);
        try {
            if ($stmt->execute()) {
                $sqlconn->close();
                return array("type" => 0, "msg" => false);
            }
        } catch(Exception $e) {
            $sqlconn->close();
            return array ("type" => 0, "msg" => "something went wrong submitting your request.");
        }

        # Clean up.
        $sqlconn->close();
    } else if (isset($_POST["cur-pass"]) || isset($_POST["new-pass-1"]) && isset($_POST["new-pass-2"])) {
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
    return false;
}

# If we got a POST, process the request.
if ($_POST) {
    $error = verify_and_add();
}

# Header
print_header("Admin Management");

# If the user just attempted to create a user, let them know if that succeeded.
if ($_POST) {
    if ($error["msg"]) {
        echo "<p>Sorry, {$error["msg"]} Please try again.</p>";
    } else if ($error["type"] === 0) {
        echo "<p>User created successfully.</p>";
    } else if ($error["type"] === 1) {
        echo "<p>Password changed successfully.</p>";
    }
}

# Create the new user form
echo sprintf(
"<h3 class=\"form-header\">Create New User</h3>
<form action=\"%s\" method=\"post\" style=\"margin-left: 10px;\">
<table>
<tbody>
<tr>
<td class=\"form-label\">
<label for=\"username\">Username: <span style=\"color: red\">*</span></label>
</td>
<td><input type=\"text\" id=\"username\" name=\"username\"></td>
</tr>
<tr>
<td class=\"form-label\">
<label for=\"password-1\">Password: <span style=\"color: red\">*</span></label>
</td>
<td><input type=\"password\" id=\"password-1\" name=\"password-1\"></td>
</tr>
<td class=\"form-label\">
<label for=\"password-2\">Confirm Password: <span style=\"color: red\">*</span></label>
</td>
<td><input type=\"password\" id=\"password-2\" name=\"password-2\"></td>
</tr>
</tbody>
</table>
<input type=\"submit\" value=\"Create User\">
</form>", htmlspecialchars($_SERVER["PHP_SELF"]));

# Create the password change form
echo sprintf(
"<h3 class=\"form-header\">Change your password</h3>
<form action=\"%s\" method=\"post\" style=\"margin-left: 10px;\">
<table>
<tbody>
<tr>
<td class=\"form-label\">
<label for=\"cur-pass\">Current Password: <span style=\"color: red\">*</span></label>
</td>
<td><input type=\"password\" id=\"cur-pass\" name=\"cur-pass\"></td>
</tr>
<tr>
<td class=\"form-label\">
<label for=\"new-pass-1\">New Password: <span style=\"color: red\">*</span></label>
</td>
<td><input type=\"password\" id=\"new-pass-1\" name=\"new-pass-1\"></td>
</tr>
<td class=\"form-label\">
<label for=\"new-pass-2\">Confirm New Password: <span style=\"color: red\">*</span></label>
</td>
<td><input type=\"password\" id=\"new-pass-2\" name=\"new-pass-2\"></td>
</tr>
</tbody>
</table>
<input type=\"submit\" value=\"Change your Password\">
</form>", htmlspecialchars($_SERVER["PHP_SELF"]));

print_footer();
?>