#!/usr/bin/env bash
set -e

# run database migrations
echo "Running database migrations ..."
APP_ENV=dev php bin/console doctrine:migrations:migrate

# run tasks only relevant for production environment
: "${APP_ENV:=unset}" # fall back to 'unset' if no environment was set
if [ "${APP_ENV}" = "prod" ]; then
	echo "Running app in production environment (${APP_ENV})"

	# ensure default symfony storage directory structure exists
	echo "Creating Symfony directory structure ..."
	for i in \
		var/cache/{dev,local} \
		var/log \
		var/share
	do
		if [ ! -d "/var/www/html/${i}" ]; then
			mkdir -vp "/var/www/html/${i}"
		fi
	done

	# ensure the app storage directory belongs to the www user
	echo "Adjusting file ownership of storage directory ..."
	chown -R www-data:www-data /var/www/html/var
fi

# run process manager
echo "Starting process supervisor ..."
exec supervisord -c /etc/supervisor.conf