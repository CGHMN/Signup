# CGHMN Sign-Up Page

TODO: Add proper readme

## How to setup

A short guide on how to set up this project on a web server. This assumes you have a web server with PHP support and a MySQL/MariaDB database already running.

1. Clone this repository:
   ```
   git clone https://github.com/CGHMN/Signup /var/www/cghmn-signups
   ```
2. Run the init script:
   ```
   php /var/www/cghmn-signups/bin/init.php
   ```
3. Modify the newly created config.php file to match your database connection settings.
4. Run the init script a second time to initialize the database:
   ```
   php /var/www/cghmn-signups/bin/init.php -p 'initial-admin-password'
   ```
   Remember to change `initial-admin-password` to a strong password of your choice.
5. Configure your webserver to point its web root to the `/public` directory within this projects' root directory, e.g. `/var/www/cghmn-signups/public`.  
   An example NGINX config is included.
