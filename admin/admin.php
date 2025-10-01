<?php
session_start();
require '../config.php';
require 'common.php';

if (!check_auth()) {
    header("Location: ./");
    die();
}

function draw_requests_table() {
    global $unrestrictedPassword;
    global $dbAddr;
    global $dbName;

    $sqlconn = new mysqli($dbAddr, "adminbot", $unrestrictedPassword);
    if ($sqlconn->connect_error) {
        echo "<p>Sorry, we can't retrieve the list of requests right now. Please try again later.</p>";
        return;
    }

    # Retrieve the list of requests.
    $result = $sqlconn->query("SELECT * FROM $dbName.Requests", MYSQLI_USE_RESULT);
    if (!$result) {
        echo "<p>Sorry, we can't retrieve the list of requests right now. Please try again later.</p>";
        return;
    }

    # Prepare to save the list of requests to the session.
    $_SESSION["Requests"] = array();

    # Start the table.
    echo sprintf(
    "<h3 class=\"form-header\">Pending requests</h3>
    <form action=\"%s\" method=\"post\">
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
    </tr>", htmlspecialchars($_SERVER["PHP_SELF"]));

    # Print all the requests
    while (($row = $result->fetch_assoc()) !== null) {
        $misc = json_decode($row["Misc"], true);
        $plan = "N/A";
        $hosting = "N/A";
        $expr = "N/A";
        $contact = "N/A";
        $contactDetails = "N/A";
        if ($misc) {
            if (isset($misc["plan"]) && is_string($misc["plan"])) {
                $plan = $misc["plan"];
            }
            if (isset($misc["hosting"]) && is_string($misc["hosting"])) {
                $hosting = $misc["hosting"];
            }
            if (isset($misc["experience"]) && is_string($misc["experience"])) {
                $expr = $misc["experience"];
            }
            if (isset($misc["contact"]) && is_string($misc["contact"])) {
                $contact = $misc["contact"];
            }
            if (isset($misc["contact-details"]) && is_string($misc["contact-details"])) {
                $contactDetails = $misc["contact-details"];
            }
        }
        echo sprintf(
            "<tr>
            <td>%s</td>
            <td>%s</td>
            <td>%s</td>
            <td>%s</td>
            <td>%s</td>
            <td>%s</td>
            <td>%s</td>
            <td>
            <select id=\"decision-%u\" name=\"decision-%u\">
            <option value=\"none\">Do Nothing</option>
            <option value=\"approve\">Approve</option>
            <option value=\"reject\">Reject</option>
            </select>
            </td>",
            htmlspecialchars($row["Username"]),
            htmlspecialchars($row["Email"]),
            htmlspecialchars($plan),
            htmlspecialchars($hosting),
            htmlspecialchars($expr),
            htmlspecialchars($contact),
            htmlspecialchars($contactDetails),
            $row["ID"], $row["ID"]
        );

        # Add the request to the list.
        $_SESSION["Requests"][$row["ID"]] = $row;
    }

    # End the table.
    echo "</tbody></table>";

    # Add the submit button or the no results message
    if ($result->num_rows === 0) {
        echo "<p class=\"data-table-footer\">No requests were found! :D</p>";
    } else {
        echo "<input class=\"data-table-footer\" type=\"submit\" value=\"Submit\">";
    }

    # Fin.
    $result->free_result();
    echo "</form>";
}

function process_requests() {
    global $restrictedPassword;
    global $dbAddr;
    global $dbName;
    global $router;
    global $rtrAPIKey;

    $sqlconn = new mysqli($dbAddr, "submitbot", $restrictedPassword);
    if ($sqlconn->connect_error) {
        return null;
    }
    $retVal = array("approved" => 0, "rejected" => 0);
    foreach ($_SESSION["Requests"] as $id => $req) {
        $name = sprintf("decision-%u", $id);
        if (isset($_POST[$name]) && is_string($_POST[$name])) {
            $decision = $_POST[$name];
            switch ($decision) {
                case "approve":
                    # API code goes here.
                    $apiReq = array(
                        "name" => "Tunnel for Member " . $req["Username"],
                        "public_key" => $req["Pubkey"]
                    );

                    # Build cURL request
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $router . "api/v1/servers/1/gen_new_peer");
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiReq));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-API-Key: {$rtrAPIKey}"));

                    # Execute the request.
                    $response = curl_exec($ch);
                    if ($response) {
                        # Decode the JSON response and send a confirmation email to the user.
                        $decodedRes = json_decode($response, true);
                        if (mail($req["Email"], "Welcome to CGHMN!", 
                            "Dear {$req["Username"]},\r\n" .
                            "Welcome to CGHMN!\r\n" .
                            "Your tunnel IP is {$decodedRes["tunnel_ip"]}\r\n" .
                            "And your routed subnet is {$decodedRes["allowed_ips"][0]}.")) {
                            $stmt = $sqlconn->prepare("DELETE FROM $dbName.Requests WHERE ID = ?");
                            $stmt->bind_param("i", $req["ID"]);
                            if ($stmt->execute()) {
                                $retVal["approved"]++;
                            }
                            $stmt->close();
                        }
                    }
                    curl_close($ch);
                    break;
                case "reject":
                    $stmt = $sqlconn->prepare("DELETE FROM $dbName.Requests WHERE ID = ?");
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
    $sqlconn->close();
    return $retVal;
}

$reqResult = false;
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
if ($reqResult !== false) {
    if ($reqResult == null) {
        echo "<p>Something went wrong handling requests.</p>";
    } else {
        echo sprintf("<p>Successfully approved %u requests and rejected %u requests.</p>", $reqResult["approved"], $reqResult["rejected"]);
    }
}
echo "</div>";

print_footer();

?>