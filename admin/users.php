<?php
session_start();
require '../config.php';
require 'common.php';

if (!check_auth()) {
    header("Location: ./");
    die();
}

function draw_users_table() {
    global $unrestrictedPassword;
    global $dbAddr;
    global $dbName;

    $sqlconn = new mysqli($dbAddr, "adminbot", $unrestrictedPassword);
    if ($sqlconn->connect_error) {
        echo "<p>Sorry, we can't retrieve the list of users right now. Please try again later.</p>";
        return;
    }

    # Retrieve the list of users.
    $result = $sqlconn->query("SELECT ID, Username, Email, Contact, Contact_Details FROM $dbName.Users", MYSQLI_USE_RESULT);
    if (!$result) {
        echo "<p>Sorry, we can't retrieve the list of requests right now. Please try again later.</p>";
        return;
    }

    # Prepare to save the list of users to the session.
    $_SESSION["Users"] = array();

    # Start the table.
    echo
    "<h3 class=\"form-header\">Users</h3>
    <form action=\"", htmlspecialchars($_SERVER["PHP_SELF"]), "\" method=\"post\">
    <table class=\"data-table\">
    <tbody>
    <tr>
    <th>Username</th>
    <th>Email</th>
    <th>Preferred Contact Method</th>
    <th>Contact Details</th>
    <th>Action</th>
    </tr>";

    # Print all the users
    while (($row = $result->fetch_assoc()) !== null) {
        # If the contact details are ridiculously long, shorten them.
        $contactDetails = $row["Contact_Details"];

        if (strlen($contactDetails) > 1000) {
            $contactDetails = substr($contactDetails, 0, 997) . "...";
        }

        echo 
       "<tr>
        <td>", htmlspecialchars($row["Username"]), "</td>
        <td>", htmlspecialchars($row["Email"]), "</td>
        <td>", htmlspecialchars($row["Contact"]), "</td>
        <td>", htmlspecialchars($contactDetails), "</td>
        <td>
        <select id=\"decision-", $row["ID"], "\" name=\"decision-", $row["ID"], "\">
        <option value=\"none\">Do Nothing</option>
        <option value=\"ban\">Ban</option>
        </select>
        </td>";

        # Add the request to the list.
        $_SESSION["Users"][$row["ID"]] = $row;
    }

    # End the table.
    echo "</tbody></table>";

    # Add the submit button or the no results message
    if ($result->num_rows === 0) {
        echo "<p class=\"data-table-footer\">No users were found! D:</p>";
    } else {
        echo "<input class=\"data-table-footer\" type=\"submit\" value=\"Go!\">";
    }

    # Fin.
    $result->free_result();
    echo "</form>";
}

function process_actions() {
    global $unrestrictedPassword;
    global $dbAddr;
    global $dbName;
    global $router;
    global $rtrAPIKey;
    global $perm;

    # If the user doesn't have permission to manage users, return.
    if ($_SESSION["permissions"] & $perm["manage-users"]) {
        return null;
    }

    $sqlconn = new mysqli($dbAddr, "adminbot", $unrestrictedPassword);
    if ($sqlconn->connect_error) {
        return "failed to connect to the database.";
    }
    $retVal = array("banned" => 0, "errors" => array());

    # Build the cURL handle we'll use.

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-API-Key: {$rtrAPIKey}"));

    foreach ($_SESSION["Users"] as $id => $usr) {
        $name = "decision-$id";
        if (isset($_POST[$name]) && is_string($_POST[$name])) {
            $decision = $_POST[$name];
            switch ($decision) {
                case "ban":
                    # Get the user's WG peers from the databse.
                    $stmt = $sqlconn->prepare("SELECT ID FROM $dbName.WG_Peers WHERE UserID = ?");
                    $stmt->bind_params("i", $usr["ID"]);
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
                        curl_setopt($ch, CURLOPT_URL, $router . "api/v1/servers/1/peers/{$row["ID"]}");
                        $response = curl_exec($chPost);
                        if (!$response) {
                            array_push($retVal["errors"], "The router didn't respond to the API request.");
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
                    $stmt = $sqlconn->prepare("DELETE FROM $dbName.WG_Peers WHERE UserID = ?");
                    $stmt->bind_params("i", $usr["ID"]);$stmt->bind_params("i", $usr["ID"]);
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
                    $stmt = $sqlconn->prepare("DELETE FROM $dbName.Users WHERE ID = ?");
                    $stmt->bind_params("i", $usr["ID"]);$stmt->bind_params("i", $usr["ID"]);
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
                    break;
                case "none":
                    break;
                default:
                    break;
            }
        }
    }
    curl_close($ch);
    $sqlconn->close();
    return $retVal;
}

# If we recieved a list of decisions, process them.
if ($_POST && isset($_SESSION["Users"]) && is_array($_SESSION["Users"])) {
    $reqResult = process_requests();
}

# Header
print_header("CGHMN Admin Page");

# Print the list of requests
echo "<div>";
draw_requests_table();

# Print the outcome of processing the requests.
if (isset($reqResult)) {
    if ($reqResult == null) {
        echo "<p>Something went wrong handling requests.</p>";
    } else if (is_string($reqResult)) {
        echo "<p>Sorry, $reqResult Please try again later</p>";
    } else {
        echo "<p>";
        echo "Successfully banned ", $reqResult["banned"], " users.";
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