<?php
session_start();
require __DIR__.'/../../config.php';
require 'common.php';

if (!check_auth()) {
    header("Location: ./");
    die();
}

# Header
print_header("Admin Management");

# If the user just attempted to create a user, let them know if that succeeded.
if (isset($_SESSION["lastAction"])) {
    if ($_SESSION["lastAction"]["name"] === "create_admin") {
        if (is_null($_SESSION["lastAction"]["ret"])) {
            echo "<p>Sorry, something went wrong. Please try again.</p>";
        } else if (is_string($_SESSION["lastAction"]["ret"])) {
            echo "<p>Sorry, {$_SESSION["lastAction"]["ret"]} Please try again.</p>";
        } else {
            echo "<p>User created successfully.</p>";
        }
    } else if ($_SESSION["lastAction"]["name"] === "change_password") {
        if (is_null($_SESSION["lastAction"]["ret"])) {
            echo "<p>Sorry, something went wrong. Please try again.</p>";
        } else if (is_string($_SESSION["lastAction"]["ret"])) {
            echo "<p>Sorry, ", $_SESSION["lastAction"]["ret"], " Please try again.</p>";
        } else {
            echo "<p>Password changed successfully.</p>";
        }
    }
    unset($_SESSION["lastAction"]);
}
?>
<!-- Create the new user form -->
<h3 class="form-header">Create New Admin</h3>
<form action="newadmin.php" method="post" style="margin-left: 10px;">
<table>
<tbody>
<tr>
<td class="form-label">
<label for="username">Username: <span style="color: red">*</span></label>
</td>
<td><input type="text" id="username" name="username"></td>
</tr>
<tr>
<td class="form-label">
<label for="password-1">Password: <span style="color: red">*</span></label>
</td>
<td><input type="password" id="password-1" name="password-1"></td>
</tr>
<td class="form-label">
<label for="password-2">Confirm Password: <span style="color: red">*</span></label>
</td>
<td><input type="password" id="password-2" name="password-2"></td>
</tr>
<tr>
<td>
<b class="form-label">Allow the new admin to:</b>
<hr style="width: 150%">
</td>
<td></td>
</tr>
<tr>
<td class="form-label">
<label for="manage-reqs">Manage User requests</label>
</td>
<td><input type="checkbox" id="manage-reqs" name="manage-reqs" value="yes"></td>
</tr>
<tr>
<td class="form-label">
<label for="manage-users">Manage users</label>
</td>
<td><input type="checkbox" id="manage-users" name="manage-users" value="yes"></td>
</tr>
<tr>
<td class="form-label">
<label for="manage-admins">Manage admins<br>(WARNING: DANGEROUS!!)</label>
</td>
<td><input type="checkbox" id="manage-admins" name="manage-admins" value="yes"></td>
</tr>
</tbody>
</table>
<input type="submit" value="Create User">
</form>
<br>

<!-- Create the password change form -->
<h3 class="form-header">Change your password</h3>
<form action="newpass.php" method="post" style="margin-left: 10px;">
<table>
<tbody>
<tr>
<td class="form-label">
<label for="cur-pass">Current Password: <span style="color: red">*</span></label>
</td>
<td><input type="password" id="cur-pass" name="cur-pass"></td>
</tr>
<tr>
<td class="form-label">
<label for="new-pass-1">New Password: <span style="color: red">*</span></label>
</td>
<td><input type="password" id="new-pass-1" name="new-pass-1"></td>
</tr>
<td class="form-label">
<label for="new-pass-2">Confirm New Password: <span style="color: red">*</span></label>
</td>
<td><input type="password" id="new-pass-2" name="new-pass-2"></td>
</tr>
</tbody>
</table>
<input type="submit" value="Change your Password">
</form>
<?php
print_footer();
?>