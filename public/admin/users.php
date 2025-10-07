<?php
session_start();
require __DIR__.'/../../config.php';
require 'common.php';

if (!check_auth()) {
    header("Location: ./");
    die();
}

function draw_users_table() {
    global $unrestrictedPassword;
    global $dbAddr;
    global $dbName;

    $sqlconn = new mysqli($dbAddr, "adminbot", $unrestrictedPassword, $dbName);
    if ($sqlconn->connect_error) {
        echo "<p>Sorry, we can't retrieve the list of users right now. Please try again later.</p>";
        return;
    }

    # Retrieve the list of users.
    $result = $sqlconn->query("SELECT ID, Username, Email, Contact, Contact_Details FROM Users", MYSQLI_USE_RESULT);
    if (!$result) {
        echo "<p>Sorry, we can't retrieve the list of requests right now. Please try again later.</p>";
        return;
    }

    # Prepare to save the list of users to the session.
    $_SESSION["Users"] = array();

    # Start the table.
    echo
    "<h3 class=\"form-header\">Users</h3>
    <form action=\"banuser.php\" method=\"post\">
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
    while (!is_null($row = $result->fetch_assoc())) {
        # If the contact details are ridiculously long, shorten them.
        $contactDetails = $row["Contact_Details"];

        if (strlen($contactDetails) > 1000) {
            $contactDetails = substr($contactDetails, 0, 997) . "...";
        }

        echo 
       "<tr>
        <td>", htmlspecialchars($row["Username"]), "</td>
        <td style=\"max-width: 20vw; word-break: break-all;\">", htmlspecialchars($row["Email"]), "</td>
        <td>", htmlspecialchars($row["Contact"]), "</td>
        <td style=\"max-width: 20vw;\">", htmlspecialchars($contactDetails), "</td>
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

function draw_user_viewer() {
    global $unrestrictedPassword;
    global $dbAddr;
    global $dbName;

    # The user to get info on
    $user = null;

    # Whether we were able to get info on said user.
    $found = false;
    $wgPeers = null;

    if ($_POST && isset($_POST["username"]) && is_string($_POST["username"])) {
        $user = $_POST["username"];
    }

    if ($user) {
        $sqlconn = new mysqli($dbAddr, "adminbot", $unrestrictedPassword, $dbName);
        if ($sqlconn->connect_error) {
            echo "<p>Sorry, we can't retrieve the list of users right now. Please try again later.</p>";
            return;
        }
        
        # Retrieve the user's info.
        $stmt = $sqlconn->prepare("SELECT ID, Email, Contact, Contact_Details FROM Users WHERE Username = ?");
        $stmt->bind_param("s", $user);
        if (!$stmt->execute()) {
            echo "<p>Sorry, we can't retrieve the list of requests right now. Please try again later.</p>";
            $sqlconn->close();
            return;
        }
        $stmt->bind_result($id, $email, $contact, $contactDetails);
        $status = $stmt->fetch();
        if ($status === false) {
            echo "<p>Sorry, we can't retrieve the list of requests right now. Please try again later.</p>";
            $sqlconn->close();
            return;
        }
        if ($status) {
            $found = true;
            # Get the user's WG peers.
            $stmt->close();
            $stmt = $sqlconn->prepare("SELECT TunnelIP, AllowedIPs, Pubkey, PSK FROM WG_Peers WHERE UserID = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                # If any WG peers were found, retrieve them.
                while ($row = $result->fetch_assoc()) {
                    if (is_null($wgPeers)) {
                        $wgPeers = array();
                    }
                    array_push($wgPeers, $row);
                }
            }
        }
        # Close the connection.
        $sqlconn->close();
    }

    # Start the table.
    echo
    "<h3 class=\"form-header\">User Viewer</h3>
    <form action=\"", htmlspecialchars($_SERVER["PHP_SELF"]), "\" method=\"post\">
    <table class=\"data-table\">
    <tbody>
    <tr>
    <td><label for=\"viewer-uname\">Username</label></td>
    <td><input id=\"viewer-uname\" name=\"username\" type=\"input\" value=\"", ($user) ? $user : "", "\"></td>
    </tr>";

    # Print the user's info (if we found it).
    if (!$user) {
        echo "<tr><td>Please enter a username to get started.</td><td></td><tr>";
    } else if (!$found) {
        echo "<tr><td>Couldn't find any info on that user.</td><td></td><tr>";
    } else {
        echo 
        "<tr><td>Email</td><td style=\"max-width: 20vw;\">", htmlspecialchars($email), "</td></tr>",
        "<tr><td>Contact Method</td><td>", htmlspecialchars($contact), "</td></tr>",
        "<tr><td style=\"max-width: 20vw;\">Contact Details</td><td style=\"max-width: 20vw;\">", htmlspecialchars($contactDetails), "</td></tr>";
        # If we found the user's WireGuard peers, print them.
        if (is_array($wgPeers)) {
            echo "<tr><td>Number of WireGuard Peers</td><td>" . count($wgPeers) . "</td></tr>";
            $peerNum = 0;
            # Print each peer
            foreach($wgPeers as $peer) {
                $peerNum++;
                # Decode the JSON encoded allowed IPs.
                $allowedIPs = json_decode($peer["AllowedIPs"], true);
                echo 
                "<tr><td>WireGuard Peer $peerNum</td><td></td></tr>",
                "<tr><td>Tunnel IP</td><td>", htmlspecialchars($peer["TunnelIP"]), "</td></tr>",
                "<tr><td>Allowed IPs</td><td style=\"max-width: 20vw;\">";
                # Print each of the user's routed subnets.
                $subnetNum = 0;
                foreach($allowedIPs as $subnet) {
                    if ($subnetNum !== 0) {
                        echo ", ";
                    }
                    echo htmlspecialchars($subnet["cidr"]);
                    $subnetNum++;
                }
                echo "</td></tr>",
                "<tr><td>Public Key</td><td>", htmlspecialchars($peer["Pubkey"]), "</td></tr>",
                "<tr><td>Preshared Key</td><td>", htmlspecialchars($peer["PSK"]), "</td></tr>";
            }
        }
    }
    # End the table.
    echo "<tr><td><input type=\"submit\" value=\"View User\"></td><td></td></tr></tbody></table></form>";
}

# If we recieved a list of decisions, process them.
if (isset($_SESSION["lastAction"])) {
    if ($_SESSION["lastAction"]["name"] === "ban_users") {
        $reqResult = $_SESSION["lastAction"]["ret"];
    }
    unset($_SESSION["lastAction"]);
}

# Header
print_header("User Management");

# Print the list of users
echo "<div>";
draw_users_table();

# Print the outcome of processing the actions.
if (isset($reqResult)) {
    if (is_null($reqResult)) {
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

# Print the user viewer
echo "<div>";
draw_user_viewer();
echo "</div>";

print_footer();

?>