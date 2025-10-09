<?php
session_start();
require __DIR__.'/../../config.php';
require 'common.php';

if (!check_auth()) {
    header("Location: ./");
    die();
}

function draw_requests_table() {
    global $unrestrictedPassword;
    global $dbAddr;
    global $dbName;

    $sqlconn = new mysqli($dbAddr, "adminbot", $unrestrictedPassword, $dbName);
    if ($sqlconn->connect_error) {
        echo "<p>Sorry, we can't retrieve the list of requests right now. Please try again later.</p>";
        return;
    }

    # Retrieve the list of pending requests.
    $result = $sqlconn->query(
        "SELECT ID, Username, Email, Pubkey, Plan, Hosting, Experience, Contact, Contact_Details, Status 
        FROM Requests
        ORDER BY STATUS DESC", MYSQLI_USE_RESULT);
    if (!$result) {
        echo "<p>Sorry, we can't retrieve the list of requests right now. Please try again later.</p>";
        return;
    }

    # Prepare to save the list of requests to the session.
    $_SESSION["Requests"] = array();

    # Start the table.
    echo
    "<h3 class=\"form-header\">Pending requests</h3>
    <form action=\"", htmlspecialchars($_SERVER["PHP_SELF"]), "\" method=\"post\">
    <table class=\"data-table\">
    <tbody>
    <tr>
    <th>Username</th>
    <th>Email</th>
    <th>Plan</th>
    <th>Needs Hosting?</th>
    <th>Has Experience?</th>
    <th>Preferred Contact Method</th>
    <th>Contact Details</th>
    <th>Decision</th>
    </tr>";

    # Keep track of how many times we've seen each username and email.
    $usernamesSeen = array();
    $emailsSeen = array();
    $requestsPending = 0;

    # Print all the requests
    while ($row = $result->fetch_assoc()) {
        # Figure out if we've seen this user or their email before.
        if (!isset($usernamesSeen[$row["Username"]])) {
            $usernamesSeen[$row["Username"]] = 0;
        }
        if (!isset($emailsSeen[$row["Email"]])) {
            $emailsSeen[$row["Email"]] = 0;
        }
        $usernamesSeen[$row["Username"]]++;
        $emailsSeen[$row["Email"]]++;

        # If this request is pending, print it.
        # why the hell does MySQL return integers as strings???
        if ($row["Status"] === "0") {
            $unameSeen = $usernamesSeen[$row["Username"]];
            $emailSeen = $emailsSeen[$row["Email"]];

            # Convert the boolean hosting & experience fields to strings.
            $hosting = "N/A";
            $expr = "N/A";
            if (!is_null($row["Hosting"])) {
                $hosting = ($row["Hosting"]) ? "Yes" : "No";
            }
            if (!is_null($row["Experience"])) {
                $expr = ($row["Experience"]) ? "Yes" : "No";
            }

            # If the plan or contact details are ridiculously long, shorten them.
            $plan = $row["Plan"];
            $contactDetails = $row["Contact_Details"];

            if (strlen($plan) > 1000) {
                $plan = substr($plan, 0, 997) . "...";
            }
            if (strlen($contactDetails) > 1000) {
                $contactDetails = substr($contactDetails, 0, 997) . "...";
            }

            echo 
            "<tr>
            <td style=\"max-width: 20vw;\">", htmlspecialchars($row["Username"]);
            # If someone with the same username has submitted a request before, print a warning.
            if ($unameSeen > 1) {
                echo "<br><span style=\"color: red\">*</span>Warning! There have been $unameSeen requests with this username!";
            }
            echo
            "</td>
            <td style=\"max-width: 20vw; word-break: break-all;\">", htmlspecialchars($row["Email"]);
            # If someone with the same email has submitted a request before, print a warning.
            if ($emailSeen > 1) {
                echo "<br><span style=\"color: red\">*</span>Warning! There have been $emailSeen requests with this email address!";
            }
            echo "</td>
            <td style=\"max-width: 20vw;\">", htmlspecialchars($plan), "</td>
            <td>", htmlspecialchars($hosting), "</td>
            <td>", htmlspecialchars($expr), "</td>
            <td>", htmlspecialchars($row["Contact"]), "</td>
            <td style=\"max-width: 20vw;\">", htmlspecialchars($contactDetails), "</td>
            <td>
            <select id=\"decision-", $row["ID"], "\" name=\"decision-", $row["ID"], "\">
            <option value=\"none\">Do Nothing</option>
            <option value=\"approve\">Approve</option>
            <option value=\"reject\">Reject</option>
            </select>
            </td>";

            # Add the request to the list.
            $_SESSION["Requests"][$row["ID"]] = $row;
            $requestsPending++;
        }
    }

    # End the table.
    echo "</tbody></table>";

    # Add the submit button or the no results message
    if ($requestsPending === 0) {
        echo "<p class=\"data-table-footer\">No requests were found! :D</p>";
    } else {
        echo "<input class=\"data-table-footer\" type=\"submit\" value=\"Go!\">";
    }

    # Fin.
    $result->free_result();
    $sqlconn->close();
    echo "</form>";
}

function process_requests() {
    global $unrestrictedPassword;
    global $dbAddr;
    global $dbName;
    global $router;
    global $rtrAPIKey;
    global $perm;

    # If the user doesn't have permission to manage requests, return.
    if ($_SESSION["permissions"] & $perm["manage-requests"]) {
        return null;
    }

    $sqlconn = new mysqli($dbAddr, "adminbot", $unrestrictedPassword, $dbName);
    if ($sqlconn->connect_error) {
        return "failed to connect to the database.";
    }
    $retVal = array("approved" => 0, "rejected" => 0, "errors" => array());

    # Build the two cURL handles we'll use. Hopefully this is correct.
    $chPost = curl_init();
    curl_setopt($chPost, CURLOPT_URL, $router . "servers/1/gen_new_peer");
    curl_setopt($chPost, CURLOPT_POST, 1);
    curl_setopt($chPost, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chPost, CURLOPT_HTTPHEADER, array("X-API-Key: {$rtrAPIKey}"));

    $chGet = curl_init();
    curl_setopt($chGet, CURLOPT_POST, 0);
    curl_setopt($chGet, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chGet, CURLOPT_HTTPHEADER, array("X-API-Key: {$rtrAPIKey}"));

    foreach ($_SESSION["Requests"] as $id => $req) {
        $name = "decision-$id";
        if (isset($_POST[$name]) && is_string($_POST[$name])) {
            $decision = $_POST[$name];
            switch ($decision) {
                case "approve":
                    # Build cURL request
                    $apiReq = array(
                        "name" => "Tunnel for Member " . $req["Username"],
                        "public_key" => $req["Pubkey"]
                    );
                    curl_setopt($chPost, CURLOPT_POSTFIELDS, http_build_query($apiReq));

                    # Create the WG peer for the new user.
                    $response = curl_exec($chPost);
                    if (!$response || !is_string($response)) {
                        array_push($retVal["errors"], "The router didn't respond to the API request.");
                        break;
                    }
                    # Decode the JSON response and check for errors.
                    $decodedRes = json_decode($response, true);
                    if (isset($decodedRes["message"]) && is_string($decodedRes["message"])) {
                        array_push($retVal["errors"], "The router encountered an error: \"{$decodedRes["message"]}\".");
                        break;
                    }
                    if (!isset($decodedRes["id"]) || !isset($decodedRes["tunnel_ip"]) || !isset($decodedRes["allowed_ips"]) ||
                        !isset($decodedRes["preshared_key"])) {
                        array_push($retVal["errors"], "The router encountered an unknown error.");
                        break;
                    }

                    # Add the user to the DB.
                    $stmt = $sqlconn->prepare("INSERT INTO Users (Username, Email, Contact, Contact_Details) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $req["Username"], $req["Email"], $req["Contact"], $req["Contact_Details"]);
                    try {
                        if (!$stmt->execute()) {
                            array_push($retVal["errors"], "Failed to add user to the database.");
                            break;
                        }
                    } catch (Exception $e) {
                        $errno = $sqlconn->errno;
                        if ($errno === 1169 || $errno === 1062) {
                            array_push($retVal["errors"], "User already in database.");
                            break;
                        }
                        array_push($retVal["errors"], "Something went wrong adding user to the database: \"{$sqlconn->error}\".");
                        break;
                    }
                    $userID = $stmt->insert_id;
                    $stmt->close();

                    # Add the user's WG peer to the DB.
                    $allowedIPs = json_encode($decodedRes["allowed_ips"]);
                    $stmt = $sqlconn->prepare("INSERT INTO WG_Peers (ID, UserID, TunnelIP, AllowedIPs, Pubkey, PSK) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssss", $decodedRes["id"], $userID, $decodedRes["tunnel_ip"], $allowedIPs, $decodedRes["public_key"], $decodedRes["preshared_key"]);
                    try {
                        if (!$stmt->execute()) {
                            array_push($retVal["errors"], "Failed to add user's WG peer to the database.");
                            break;
                        }
                    } catch (Exception $e) {
                        array_push($retVal["errors"], "Something went wrong adding user's WG peer to the database: \"{$sqlconn->error}\".");
                        break;
                    }
                    $stmt->close();

                    # Get the example config
                    curl_setopt($chGet, CURLOPT_URL, $router . "servers/1/peers/{$decodedRes["id"]}/config");
                    $response = curl_exec($chGet);
                    if (!$response || !is_string($response)) {
                        array_push($retVal["errors"], "The router didn't respond to the API request.");
                        break;
                    }

                    # Prepare the email contents.
                    $email = array(
                        "username" => $req["Username"],
                        "email" => $req["Email"],
                        "tunnelIP" => $decodedRes["tunnel_ip"],
                        "presharedKey" => $decodedRes["preshared_key"],
                        "routedSubnet" => $decodedRes["allowed_ips"][0]["cidr"],
                        "exampleConfig" => $response
                    );

                    if (send_confirmation_email($email)) {
                        $stmt = $sqlconn->prepare("UPDATE $dbName.Requests SET Status = 1 WHERE ID = ?");
                        $stmt->bind_param("i", $req["ID"]);
                        if ($stmt->execute()) {
                            $retVal["approved"]++;
                        }
                        $stmt->close();
                    }
                    break;
                case "reject":
                    $stmt = $sqlconn->prepare("UPDATE $dbName.Requests SET Status = 2 WHERE ID = ?");
                    $stmt->bind_param("i", $req["ID"]);
                    if ($stmt->execute()) {
                        $retVal["rejected"]++;
                    }
                    $stmt->close();
                    break;
                case "none":
                    break;
                default:
            }
        }
    }
    curl_close($chGet);
    curl_close($chPost);
    $sqlconn->close();
    return $retVal;
}

# If we recieved a list of decisions, process them.
if ($_POST && isset($_SESSION["Requests"]) && is_array($_SESSION["Requests"])) {
    $reqResult = process_requests();
}

# Header
print_header("CGHMN Admin Page");

# Print the list of requests
echo "<div>";
draw_requests_table();

# Print the outcome of processing the requests.
if (isset($reqResult)) {
    if (is_null($reqResult)) {
        echo "<p>Something went wrong handling requests.</p>";
    } else if (is_string($reqResult)) {
        echo "<p>Sorry, $reqResult Please try again later</p>";
    } else {
        echo "<p>";
        echo "Successfully approved ", $reqResult["approved"], " requests and rejected ", $reqResult["rejected"], " requests.";
        if (count($reqResult["errors"]) !== 0) {
            echo "<br>However, the following errors were encountered while processing requests:<br>";
            foreach ($reqResult["errors"] as $error) {
                echo htmlspecialchars($error) . "<br>";
            }
        }
        echo "</p>";
    }
}
echo "</div>";

print_footer();

?>