<?php
session_start();
require __DIR__.'/../../config.php';
require 'common.php';

if (!check_auth()) {
    header("Location: ./");
    die();
}

# Handle the submitted actions.
function process_actions() {
    global $unrestrictedPassword;
    global $dbAddr;
    global $dbName;
    global $router;
    global $rtrAPIKey;
    global $perm;

    # Track whether we've modified the WG peers (so we know if we need to reload the WG interface).
    $modified = false;

    # If the user doesn't have permission to manage users, return.
    if ($_SESSION["permissions"] & $perm["manage-users"]) {
        return null;
    }

    $sqlconn = new mysqli($dbAddr, "adminbot", $unrestrictedPassword, $dbName);
    if ($sqlconn->connect_error) {
        return "failed to connect to the database.";
    }
    $retVal = array("banned" => 0, "resent" => 0, "errors" => array());

    # Build the cURL handles we'll use.

    $chDel = curl_init();
    curl_setopt($chDel, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($chDel, CURLOPT_HTTPHEADER, array("X-API-Key: {$rtrAPIKey}"));
    curl_setopt($chDel, CURLOPT_RETURNTRANSFER, true);

    $chGet = curl_init();
    curl_setopt($chGet, CURLOPT_POST, 0);
    curl_setopt($chGet, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chGet, CURLOPT_HTTPHEADER, array("X-API-Key: {$rtrAPIKey}"));

    foreach ($_SESSION["Users"] as $id => $usr) {
        $name = "decision-$id";
        if (isset($_POST[$name]) && is_string($_POST[$name])) {
            $decision = $_POST[$name];
            switch ($decision) {
                case "ban":
                    # Get the user's WG peers from the databse.
                    $stmt = $sqlconn->prepare("SELECT ID FROM WG_Peers WHERE UserID = ?");
                    $stmt->bind_param("i", $usr["ID"]);
                    try {
                        if (!$stmt->execute()) {
                            array_push($retVal["errors"], "Failed to retrieve Wireguard peers from database.");
                            break;
                        }
                    } catch (Exception $e) {
                        array_push($retVal["errors"], "Something went wrong retrieving Wireguard peers from database.");
                        break;
                    }
                    # Iterate through the WG peers
                    $result = $stmt->get_result();
                    # Avoid orphaned WG peers
                    $good = true;
                    while ($row = $result->fetch_assoc()) {
                        # Delete the WG peers
                        curl_setopt($chDel, CURLOPT_URL, $router . "servers/1/peers/{$row["ID"]}");
                        $response = curl_exec($chDel);
                        if (!$response) {
                            array_push($retVal["errors"], "The router didn't respond to the API request.");
                            $good = false;
                            break;
                        }
                        if ($response != 1) {
                            array_push($retVal["errors"], "Something went wrong while deleting the WG peer.");
                            $good = false;
                            break;
                        }
                    }
                    $stmt->close();
                    # If deleting the WG peers failed, abort.
                    if (!$good) {
                        break;
                    }
                    # Delete the WG peers from the DB
                    $stmt = $sqlconn->prepare("DELETE FROM WG_Peers WHERE UserID = ?");
                    $stmt->bind_param("i", $usr["ID"]);$stmt->bind_param("i", $usr["ID"]);
                    try {
                        if (!$stmt->execute()) {
                            array_push($retVal["errors"], "Failed to delete Wireguard peers from database.");
                            break;
                        }
                    } catch (Exception $e) {
                        array_push($retVal["errors"], "Something went wrong while deleting Wireguard peers from database.");
                        break;
                    }
                    $stmt->close();
                    # Delete them from the list of users
                    $stmt = $sqlconn->prepare("DELETE FROM Users WHERE ID = ?");
                    $stmt->bind_param("i", $usr["ID"]);$stmt->bind_param("i", $usr["ID"]);
                    try {
                        if (!$stmt->execute()) {
                            array_push($retVal["errors"], "Failed to delete user from database.");
                            break;
                        }
                    } catch (Exception $e) {
                        array_push($retVal["errors"], "Something went wrong while the user from database.");
                        break;
                    }
                    # We have successfully banned a user.
                    $retVal["banned"]++;
                    $modified = true;
                    break;
                case "resend":
                    # Get the user's WG peers from the databse.
                    $stmt = $sqlconn->prepare("SELECT ID, TunnelIP, AllowedIPs, PSK FROM WG_Peers WHERE UserID = ?");
                    $stmt->bind_param("i", $usr["ID"]);
                    try {
                        if (!$stmt->execute()) {
                            array_push($retVal["errors"], "Failed to retrieve Wireguard peers from database.");
                            break;
                        }
                    } catch (Exception $e) {
                        array_push($retVal["errors"], "Something went wrong retrieving Wireguard peers from database.");
                        break;
                    }
                    $stmt->bind_result($peerID, $tunnelIP, $allowedIPs, $PSK);
                    if (!$stmt->fetch()) {
                        array_push($retVal["errors"], "Something went wrong retrieving Wireguard peer info from database.");
                        $stmt->close();
                        break;
                    }
                    $stmt->close();

                    # Decode the allowed IPs
                    $decodedIPs = json_decode($allowedIPs, true);

                    # Get the example config from the Wireguard API
                    curl_setopt($chGet, CURLOPT_URL, $router . "servers/1/peers/$peerID/config");
                    $response = curl_exec($chGet);
                    if (!$response || !is_string($response)) {
                        array_push($retVal["errors"], "The router didn't respond to the API request.");
                        break;
                    }

                    # Prepare the email contents.
                    $email = array(
                        "username" => $usr["Username"],
                        "email" => $usr["Email"],
                        "tunnelIP" => $tunnelIP,
                        "presharedKey" => $PSK,
                        "routedSubnet" => $decodedIPs[0]["cidr"],
                        "exampleConfig" => $response
                    );

                    if (send_confirmation_email($email)) {
                        $retVal["resent"]++;
                    } else {
                        array_push($retVal["errors"], "Failed to resend the confirmation email.");
                    }
                    break;
                case "none":
                    break;
                default:
                    break;
            }
        }
    }
    curl_close($chDel);
    curl_close($chGet);
    $sqlconn->close();

    # Reload the WG peers if we need to
    if ($modified) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $router . "servers/1/reload");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-API-Key: {$rtrAPIKey}"));
        curl_exec($ch);
        curl_close($ch);
    }
    return $retVal;
}

if ($_POST && isset($_SESSION["Users"]) && is_array($_SESSION["Users"])) {
    $_SESSION["lastAction"] = array("name" => "modify_users", "ret" => process_actions());
}
header("Location: users.php");
die();
?>