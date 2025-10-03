<?php
session_start();
require __DIR__.'/../config.php';
function verify_and_submit() {
    # Check that the server state & email verification code are valid.
    global $restrictedPassword;
    global $dbAddr;
    global $dbName;

    if (!isset($_SESSION["formdata"]) || !isset($_SESSION["verifycode"]) || !isset($_SESSION["expires"]) ||
        !isset($_POST["code"]) || !is_string($_POST["code"]) || time() >= $_SESSION["expires"] || $_SESSION["verifycode"] !== $_POST["code"]) {
        return "you have entered an incorrect or expired email verification code.";
    }

    # Prepare to send the request to the database.
    $sqlconn = new mysqli($dbAddr, "submitbot", $restrictedPassword);
    if ($sqlconn->connect_error) {
        return "something went wrong.";
    }

    # JSON code any other data we want to save for storage in the DB.
    $misc = json_encode(array(
        "plan" => $_SESSION["formdata"]["plan"],
        "hosting" => $_SESSION["formdata"]["hosting"],
        "experience" => $_SESSION["formdata"]["experience"],
        "contact" => $_SESSION["formdata"]["contact"],
        "contact-details" => $_SESSION["formdata"]["contact-details"]
    ));

    # Store request to the DB.
    $stmt = $sqlconn->prepare("INSERT IGNORE INTO $dbName.Requests (Username, Email, Pubkey, Misc) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $_SESSION["formdata"]["username"], $_SESSION["formdata"]["email"], $_SESSION["formdata"]["pubkey"], $misc);
    try {
        if ($stmt->execute()) {
            echo 
            "<!DOCTYPE html>
            <html>
            <head></head>
            <body>
                <p>
                You have successfully signed up to CGHMN.<br>
                Your request will be reviewed by an admin shortly.
                </p>
            </body>
            </html>";
            session_destroy();
            die();
        }
    } catch(Exception $e) {
        $sqlconn->close();
        return "something went wrong while submitting your request.";
    }
}
if ($_POST) {
    $error = verify_and_submit();
}
?>
<!DOCTYPE html>
<html>
    <head>
    </head>
    <body>
        <h1>CGHMN Email Verification</h1>
        <?php 
            if ($_POST && $error) {
                echo "<p>Sorry, " . $error . " Please try again.";
            }
        ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post">
            <label for="code">Please enter your verification code.<br>If you don't see an email from us, check your spam folder.</label><br>
            <input type="text" id="code" name="code">
            <input type="submit">
        </form>
    </body>
</html>