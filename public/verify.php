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

    # Check if the user has already submitted a request.
    $stmt = $sqlconn->prepare("SELECT ID FROM $dbName.Requests WHERE Username = ? AND (Status = 0 OR Status = 1)");
    $stmt->bind_param("s", $_SESSION["formdata"]["username"]);
    if (!$stmt->execute()) {
        $sqlconn->close();
        return "something went wrong while connecting to the database.";
    }
    # If they have already submitted a request that is pending or has been approved,
    # prevent them from submitting another.
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        $sqlconn->close();
        return "you have already submitted a request.";
    }

    # Store request to the DB.
    $status = 0;
    $stmt = $sqlconn->prepare(
        "INSERT INTO $dbName.Requests 
        (Status, Username, Email, Pubkey, Plan, Hosting, Experience, Contact, Contact_Details) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssiiss", 
        $status, $_SESSION["formdata"]["username"], $_SESSION["formdata"]["email"], $_SESSION["formdata"]["pubkey"], 
        $_SESSION["formdata"]["plan"], $_SESSION["formdata"]["hosting"], $_SESSION["formdata"]["experience"],
        $_SESSION["formdata"]["contact"], $_SESSION["formdata"]["contact-details"]
    );
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
    }
    $sqlconn->close();
    return "something went wrong while submitting your request.";
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
            <input type="submit" value="Verify">
        </form>
    </body>
</html>