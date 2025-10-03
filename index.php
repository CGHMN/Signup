<?php 
session_start();
require 'config.php';
function validate() {
    # Validate the form data.
    if (!isset($_POST["username"]) || !is_string($_POST["username"]) ||  $_POST["username"] === "") {
        return "you must enter a username.";
    }
    if (!preg_match("/^[a-z]\w{0, 64}$/i", $_POST["username"])) {
        return "you must enter a valid username (no spaces or special characters).";
    }
    if (!isset($_POST["email"]) || !is_string($_POST["username"]) ||
        $_POST["email"] === "" || strlen($_POST["email"]) > 65535 ||
        !preg_match("/^([a-z0-9!-'*+\-\/=?\^_`{-}][a-z0-9!-'*+\-\/=?\^_`{-}\.]*)?[a-z0-9!-'*+\-\/=?\^_`{-}]@([a-z][a-z0-9\-]*)?[a-z0-9](\.([a-z][a-z0-9\-]*)?[a-z0-9])+$/i", $_POST["email"])) {
        return "you must enter a valid email address.";
    }
    if (!isset($_POST["pubkey"]) || !is_string($_POST["pubkey"]) ||
        $_POST["pubkey"] === "" ||
        !preg_match("/^[a-z0-9\+\/]{43}=$/i", $_POST["pubkey"])) {
        return "you must enter a valid WireGuard public key.";
    }
    return false;
}
if ($_POST) {
    $error = validate();
    if (!$error && $_POST) {
        # Store the form data in the session for the verify page.
        $_SESSION["formdata"] = array(
            "username" => $_POST["username"],
            "email" => $_POST["email"],
            "pubkey" => $_POST["pubkey"],
            "plan" => (isset($_POST["plan"]) && is_string($_POST["plan"])) ? $_POST["plan"] : "",
            "hosting" => (isset($_POST["hosting"]) && is_string($_POST["hosting"])) ? $_POST["hosting"] : "",
            "experience" => (isset($_POST["experience"]) && is_string($_POST["experience"])) ? $_POST["experience"] : "",
            "contact" => (isset($_POST["contact"]) && is_string($_POST["contact"])) ? $_POST["contact"] : "",
            "contact-details" => (isset($_POST["contact-details"]) && is_string($_POST["contact-details"])) ? $_POST["contact-details"] : ""
        );

        # Store the email verification code and when it expires in the session.
        $_SESSION["verifycode"] = bin2hex(random_bytes(5));
        $_SESSION["expires"] = time() + 1200;

        # Send the verification email.
        if (mail($_POST["email"], "CGHMN Email Verification", 
            "Dear {$_POST["username"]},\r\n" .
            "Your verification code is:\r\n" . $_SESSION["verifycode"] . "\r\n" .
            "This code expires in 20 minutes.\r\n" .
            "If you do not recognize this email, please contact loganisamazing@outlook.com.", 
            "From: noreply@cghmn.org")) {
            
            # Redirect to the verification page.
            header("Location: verify.php");
            die();
        }

        $error = "failed to send verification email.";
    }
}
?>
<!-- What I want is to keep the user on the form page if something went wrong,
 and otherwise redirect them to an email verification page. -->
<!DOCTYPE html>
<html>
    <head>
    </head>
    <body>
        <h1>CGHMN Sign-Up Page</h1>
        <?php
            # Display any errors.
            if ($_POST && $error) {
                echo "<p>Sorry, " . htmlspecialchars($error) . " Please try again.</p>";
            }
        ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post">
            <label for="username">What's your username?<span style="color:red"> *</span></label><br>
            <input type="text" name="username" id="username" placeholder="johndoe1"><br>
            <label for="email">What's your email address?<span style="color:red"> *</span></label><br>
            <input type="email" name="email" id="email" placeholder="johndoe@gmail.com"><br>
            <label for="pubkey">What's your WireGuard public key?<span style="color:red"> *</span></label><br>
            <a href="placeholder.html">I don't have one</a><br>
            <input type="text" name="pubkey" id="pubkey" placeholder="Public Key"><br>
            <label for="plan">What do you plan to do on CGHMN?</label><br>
            <textarea name="plan" id="plan" rows="6" cols="40"></textarea><br>
            <p>Will you need hosting from CGHMN?</p>
            <input type="radio" id="yes-hosting" name="hosting" value="Yes">
            <label for="yes-hosting">Yes</label><br>
            <input type="radio" id="no-hosting" name="hosting" value="No">
            <label for="no-hosting">No</label>
            <p>Do you have sys admin/networking experience?</p>
            <input type="radio" id="yes-experience" name="experience" value="Yes">
            <label for="yes-experience">Yes</label><br>
            <input type="radio" id="no-experience" name="experience" value="No">
            <label for="no-experience">No</label>
            <p>How do you prefer to be contacted?</p>
            <input type="radio" name="contact" id="email-preferred" value="Email">
            <label for="email-preferred">Email</label><br>
            <input type="radio" name="contact" id="discord-preferred" value="Discord">
            <label for="discord-preferred">Discord</label><br>
            <input type="radio" name="contact" id="irc-preferred" value="IRC">
            <label for="irc-preferred">IRC</label><br>
            <input type="radio" name="contact" id="other-preferred" value="Other">
            <label for="other-preferred">Other</label>
            <p>If you put something other than email, please give details on how we can contact you using your preferred method.</p>
            <textarea name="contact-details" id="contact-details" rows="6" cols="40"></textarea><br>
            <input type="submit">
        </form>
    </body>
</html>