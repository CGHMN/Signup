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
        WHERE NOT STATUS = 0", MYSQLI_USE_RESULT);
    if (!$result) {
        echo "<p>Sorry, we can't retrieve the list of requests right now. Please try again later.</p>";
        return;
    }

    # Prepare to save the list of requests to the session.
    $_SESSION["staleRequests"] = array();

    # Start the table.
    echo
    "<h3 class=\"form-header\">Past requests</h3>
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
    <th>Status</th>
    <th>Decision</th>
    </tr>";

    $requests = 0;

    # Print all the requests
    while ($row = $result->fetch_assoc()) {
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
        "<tr>",
        "<td style=\"max-width: 20vw;\">", htmlspecialchars($row["Username"]),
        "</td>",
        "<td style=\"max-width: 20vw; word-break: break-all;\">", htmlspecialchars($row["Email"]),
        "</td>",
        "<td style=\"max-width: 20vw;\">", htmlspecialchars($plan), "</td>",
        "<td>", htmlspecialchars($hosting), "</td>",
        "<td>", htmlspecialchars($expr), "</td>",
        "<td>", htmlspecialchars($row["Contact"]), "</td>",
        "<td style=\"max-width: 20vw;\">", htmlspecialchars($contactDetails), "</td>",
        "<td>", ($row["Status"] === "1") ? "Approved" : "Rejected", "</td>",
        "<td>",
        "<select id=\"decision-", $row["ID"], "\" name=\"decision-", $row["ID"], "\">",
        "<option value=\"none\">Do Nothing</option>",
        "<option value=\"delete\">Delete</option>",
        "</select>",
        "</td>";

        # Add the request to the list.
        $_SESSION["staleRequests"][$row["ID"]] = $row;
        $requests++;
    }

    # End the table.
    echo "</tbody></table>";

    # Add the submit button or the no results message
    if ($requests === 0) {
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
    $retVal = array("deleted" => 0, "errors" => array());

    foreach ($_SESSION["staleRequests"] as $id => $req) {
        $name = "decision-$id";
        if (isset($_POST[$name]) && is_string($_POST[$name])) {
            $decision = $_POST[$name];
            switch ($decision) {
                case "delete":
                    $stmt = $sqlconn->prepare("DELETE FROM Requests WHERE ID = ?");
                    $stmt->bind_param("i", $req["ID"]);
                    if ($stmt->execute()) {
                        $retVal["deleted"]++;
                    }
                    $stmt->close();
                    break;
                case "none":
                    break;
                default:
            }
        }
    }
    $sqlconn->close();
    return $retVal;
}

# If we recieved a list of decisions, process them.
if ($_POST && isset($_SESSION["staleRequests"]) && is_array($_SESSION["staleRequests"])) {
    $reqResult = process_requests();
}

# Header
print_header("Past Requests");

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
        echo "Successfully deleted ", $reqResult["deleted"], " old requests.";
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