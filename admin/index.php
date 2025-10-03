<?php
session_start();
require '../config.php';
# If user has already authenticated, redirect them to the admin console.
if (isset($_SESSION["userId"]) && is_numeric($_SESSION["userId"]) &&
    isset($_SESSION["userName"]) && is_string($_SESSION["userName"]) &&
    isset($_SESSION["permissions"]) && is_numeric($_SESSION["permissions"]) &&
    isset($_SESSION["expires"]) && is_numeric($_SESSION["expires"])) {
    header("Location: admin.php");
    die();
}
//session_destroy();
function verify_login() {
    if ($_POST) {
        global $restrictedPassword;
        global $dbAddr;
        global $dbName;

        if (!isset($_POST["username"]) || !is_string($_POST["username"]) || $_POST["username"] === "") {
            return "you must enter a username.";
        }

        if (!isset($_POST["password"]) || !is_string($_POST["password"]) || $_POST["password"] === "") {
            return "you must enter a password.";
        }

        $sqlconn = new mysqli($dbAddr, "submitbot", $restrictedPassword);
        if ($sqlconn->connect_error) {
            return "something went wrong.";
        }
        $stmt = $sqlconn->prepare("SELECT ID, Password, Permissions FROM $dbName.Admins WHERE username = ?");
        $stmt->bind_param("s", $_POST["username"]);
        if (!$stmt->execute()) {
            $sqlconn->close();
            return "something went wrong.";
        }
        $stmt->bind_result($id, $passHash, $permissions);
        if (!$stmt->fetch()) {
            $sqlconn->close();
            return "incorrect username or password.";
        }
        $stmt->close();
        if (password_verify($_POST["password"], $passHash)) {
            $_SESSION["userId"] = $id;
            $_SESSION["userName"] = $_POST["username"];
            $_SESSION["permissions"] = $permissions;
            # Recheck username & permissions every 5 minutes.
            $_SESSION["expires"] = time() + 300;
            $sqlconn->close();
            header("Location: admin.php");
            die();
        } else {
            $sqlconn->close();
            return "incorrect username or password.";
        }
    }
}
if ($_POST) {
    $error = verify_login();
}
?>
<!DOCTYPE html>
<html>
    <head>
    </head>
    <body>
        <h1>CGHMN Admin Login</h1>
        <p>Please enter your username and password.</p>
        <?php 
        if ($_POST && is_string($error) && $error !== "") {
            echo "<p>Sorry, " . $error . " Please try again later.</p>";
        }
        ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post">
            <label for="username">Username:</label><br>
            <input type="text" id="username" name="username"><br>
            <label for="password">Password:</label><br>
            <input type="password" id="password" name="password"><br>
            <input type="submit" value="Log In">
        </form>
    </body>
</html>