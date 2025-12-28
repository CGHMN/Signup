For storing data, we rely on an external MySQL (or compatible) database.
There are 4 tables: Admins, Requests, Users, and WG_Peers.
Because none of these are created automatically at the moment,
it's important that you create these manually.
To help with that, we'll document each table, it's purpose, and it's columns here.

# Users
This table stores all users who have requested to join CGHMN,
have been approved and are part of the network,
or have been banned.
 * Their user IDs
 * Their usernames
 * Their passwords (as hashes)
 * Their roles (pending/admin/approved/banned/etc)
 * Their email addresses
 * Their WireGuard public keys
 * What they plan on doing on CGHMN
 * If they will need hosting from CGHMN
 * Whether they have sysadmin experience
 * Their preferred contact method
 * Their contact details

# WG_Peers
This table stores the Wireguard info for all users
 * Which user the WG peer belongs to
 * Their tunnel IP
 * Their routed subnets
 * Their pubkey
 * Their preshared key
 * The peer ID
Here are the columns:
 * ID:bigint (Primary Key)
 * UserID:bigint (Foreign Key on Users[ID])
 * TunnelIP:text
 * AllowedIPs:text
 * Pubkey:text
 * PSK:text