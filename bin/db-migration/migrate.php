<?php
// this script migrates the existing sign-up v1 database to the new v2 format
// copy the `.env-migration.example` file to `.env-migration` in the same directory as this script,
// adjust the required password and API key fields, then ensure php8.4-cli or higher with the
// PDO, MariaDB and cURL extensions are installed and finally call the script with `php migrate.php`

// --- function definitions

/**
 * Loads environment variables from .env file
 * 
 * @param string $path Specify the file path for the .env file
 * @param bool $override Overrides existing environment variables if true
 */
function load_env(string $path, bool $override = false): void {
	if (is_file($path)) {
		preg_match_all('/^(?!\s*#)([\w]+)\s*=\s*"?(.*?)"?$/m', file_get_contents($path), $matches);

		for ($i = 0; $i < count($matches[1]); $i++) {
			if ($override || getenv($matches[1][$i]) === false) {
				putenv("{$matches[1][$i]}={$matches[2][$i]}");
			}
		}
	} else {
		throw new \Exception("Environment file {$path} does not exist");
	}
}

/**
 * Generate a random string, using a cryptographically secure 
 * pseudorandom number generator (random_int)
 * 
 * For PHP 7, random_int is a PHP core function
 * For PHP 5.x, depends on https://github.com/paragonie/random_compat
 * 
 * @param int $length      How many characters do we want?
 * @param string $keyspace A string of all possible characters
 *                         to select from
 * @return string
 * @see https://stackoverflow.com/a/31284266
 */
function random_str(
	$length,
	$keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
) {
	$str = '';
	$max = mb_strlen($keyspace, '8bit') - 1;
	if ($max < 1) {
		throw new Exception('$keyspace must be at least two characters long');
	}
	for ($i = 0; $i < $length; ++$i) {
		$str .= $keyspace[random_int(0, $max)];
	}
	return $str;
}

// --- main script entrypoint

// -- load .env from new signup page
load_env(__DIR__ . '/../../.env');

// -- load .env for this migration script
load_env(__DIR__ . '/.env-migration');

// -- connect to old database
echo "Connecting to old database... \n";
$old_db_pdo = new PDO(
	'mysql:host=' . getenv('OLD_DB_HOST') . ';dbname=' . getenv("OLD_DB_NAME"),
	getenv('OLD_DB_USERNAME'),
	getenv('OLD_DB_PASSWORD')
);
echo "Connected. \n";

// -- connect to new database
echo "Connecting to new database... \n";
$new_db_pdo = new PDO(
	"mysql:host=" . getenv('DATABASE_HOST') . ":" . getenv('DATABASE_PORT') . ";dbname=" . getenv('DATABASE_NAME'),
	getenv('DATABASE_USER'),
	getenv('DATABASE_PASSWORD')
);
echo "Connected. \n";

// -- migrating users

// fetch existing users and their associated requests from the old database
$previous_users = $old_db_pdo->query(<<<SQL
SELECT
	u.ID AS ID,
	u.Username AS Username,
	u.Email AS Email,
	u.Contact AS Contact,
	u.Contact_Details AS Contact_Details,
	r.Pubkey AS Pubkey,
	r.Plan AS Plan,
	r.Hosting AS Hosting,
	r.Experience AS Experience
FROM Users AS u

INNER JOIN Requests AS r ON r.Email = u.Email;
SQL);
if (!$previous_users) {
	throw new \Exception('Unable to fetch previous users from old database: ' . $new_db_pdo->errorInfo());
}

// prepare user insert statement
$user_insert_statement = $new_db_pdo->prepare(<<<SQL
INSERT INTO user
	(username, roles, password, email, pub_key, plan, needs_hosting, has_experience, contact_method, contact_details)
VALUES
	(:username, :roles, :password, :email, :pub_key, :plan, :needs_hosting, :has_experience, :contact_method, :contact_details);
SQL);

// fetch existing users to prevent duplicate errors
$existing_users_result = $new_db_pdo->query('SELECT id, username, pub_key FROM user;');
if (!$existing_users_result) {
	throw new \Exception('Unable to fetch existing users from new database: ' . $new_db_pdo->errorInfo());
} else {
	$existing_users = [];
	foreach ($existing_users_result as $user) {
		$existing_users[$user['pub_key']] = [
			'id' => $user['id'],
			'username' => $user['username']
		];
	}
}

// iterate over pervious user list and create new users as required
foreach($previous_users as $user) {
	if (array_find($existing_users, fn($v) => $v['username'] == $user['Username'])) {
		echo "User {$user['Username']} already exists in new database, skipping\n";
		continue;
	}

	$new_user_data = [
		'username' => $user['Username'],
		'roles' => '["ROLE_USER_APPROVED"]',
		'password' => password_hash(random_str(32), PASSWORD_BCRYPT),
		'email' => $user['Email'],
		'pub_key' => $user['Pubkey'],
		'plan' => $user['Plan'],
		'needs_hosting' => $user['Hosting'],
		'has_experience' => $user['Experience'],
		'contact_method' => $user['Contact'],
		'contact_details' => $user['Contact_Details']
	];

	echo "Creating new entry for {$new_user_data['username']}\n";
	$user_insert_statement->execute($new_user_data);
	
	$existing_users[$user['Pubkey']] = [
		'id' => intval($new_db_pdo->lastInsertId()),
		'username' => $user['Username']
	];
}

// -- migrate wireguard peers
// for this, we pull the live peer data from the wireguard api server instead of the old database for the most up-to-date data

$ch = curl_init('http://' . getenv('WG_SERVER_API_HOST') . '/api/v1/servers/' . getenv('WG_SERVER_API_SERVER_ID') . '/peers');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
	'X-API-Key: ' . getenv('WG_SERVER_API_KEY')
]);
if (!($curl_result = curl_exec($ch))) {
	throw new \Exception("Unable to fetch Wireguard peer data from API: " . curl_error($ch));
} else {
	unset($ch);
	$previous_peers = json_decode($curl_result);
}

// prepare peer insert statement
$peer_insert_statement = $new_db_pdo->prepare(<<<SQL
INSERT INTO wireguard_peer
	(tunnel_ip, allowed_ips, pub_key, preshared_key, router_id, user_id)
VALUES
	(:tunnel_ip, :allowed_ips, :pub_key, :preshared_key, :router_id, :user_id);
SQL);

// fetch existing peers to prevent duplicate errors
$existing_peers_result = $new_db_pdo->query('SELECT pub_key FROM wireguard_peer;');
if (!$existing_peers_result) {
	throw new \Exception('Unable to fetch existing peers from new database: ' . $new_db_pdo->errorInfo());
} else {
	$existing_peers = [];
	foreach ($existing_peers_result as $peer) {
		$existing_peers[] = $peer['pub_key'];
	}
}

// iterate over pervious peer list and create new peers as required
foreach ($previous_peers as $peer) {
	if (in_array($peer->public_key, $existing_peers)) {
		echo "Peer {$peer->name} ({$peer->public_key}, {$peer->tunnel_ip}) already exists in new database, skipping\n";
		continue;
	}

	$mapped_user_id = (array_key_exists($peer->public_key, $existing_users)? $existing_users[$peer->public_key] : false);
	if (!$mapped_user_id) {
		error_log("Warning: Found existing Wireguard peer {$peer->name} ({$peer->public_key}, {$peer->tunnel_ip}) which does not map to any existing user public keys!");
		continue;
	}

	$new_peer_data = [
		'tunnel_ip' => $peer->tunnel_ip,
		'allowed_ips' => json_encode($peer->allowed_ips),
		'pub_key' => $peer->public_key,
		'preshared_key' => $peer->preshared_key,
		'router_id' => 0,
		'user_id' => $mapped_user_id['id']
	];

	echo "Creating new peer for {$mapped_user_id['username']} ({$peer->public_key}, {$peer->tunnel_ip})\n";
	$peer_insert_statement->execute($new_peer_data);
}