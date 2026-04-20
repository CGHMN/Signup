# Package version parameters
ARG PHP_VERSION="8.4"

# --------
# -- Shared base for production and development containers
FROM debian:trixie AS base
ARG PHP_VERSION

# Install required system packages
ENV DEBIAN_FRONTEND="noninteractive"
SHELL [ "/bin/bash", "-c" ]
RUN apt-get update -qq && \
	apt-get install -qq -y --no-install-recommends \
	ca-certificates supervisor wget zip unzip curl git \
	nginx \
	php${PHP_VERSION}-{bcmath,bz2,cli,curl,fpm,gd,intl,mbstring,mysql,opcache,redis,soap,xml,zip} && \
	rm -rf /var/cache/apt/archives /var/lib/apt/lists/*

# Install composer binary
COPY --from=composer /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Install Symfony CLI
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash && \
	apt-get update -qq && \
	apt install -y symfony-cli && \
	rm -rf /var/cache/apt/archives /var/lib/apt/lists/*

# --------
# -- Development image
FROM base AS development

# Install support files
COPY ./docker/supervisor-development.conf /etc/supervisor.conf
COPY ./docker/entrypoint.sh /entrypoint.sh

# Add 'user' for supervisord to start services with user permissions
RUN groupadd -g 1000 user && \
	useradd -m -k /dev/null -u 1000 -g 1000 -s /bin/bash user

EXPOSE 8000

WORKDIR /var/www/html
ENTRYPOINT [ "/entrypoint.sh" ]

# --------
# -- Cache layer for PHP vendor packages
FROM base AS backend_pkg_cache
WORKDIR /var/www/html
COPY composer.json composer.lock ./
COPY ./lib/composer.json ./lib/composer.json
RUN composer install --no-autoloader

# --------
# -- Cache for cleaning up the project root
FROM backend_pkg_cache AS backend_files
COPY --chown=www-data:www-data . .
RUN rm -rf .git

# --------
# -- Production image
FROM backend_pkg_cache AS production
ARG PHP_VERSION

# Copy cleaned-up project root
COPY --from=backend_files /var/www/html .

# Create composer autoloader files
RUN composer dump-autoload -o --strict-psr --no-cache

# Install support files
COPY ./docker/entrypoint.sh /entrypoint.sh
COPY ./docker/supervisor-production.conf /etc/supervisor.conf

# Configure PHP-FPM with sane defaults
ENV PHP_INI_DIR="/etc/php/${PHP_VERSION}/fpm"
RUN sed -ri -e 's|^;?memory_limit.*|memory_limit = 512M|g' "${PHP_INI_DIR}/php.ini" && \
	sed -ri -e 's|^;?post_max_size.*|post_max_size = 32M|g' "${PHP_INI_DIR}/php.ini" && \
	sed -ri -e 's|^;?upload_max_filesize.*|upload_max_filesize = 32M|g' "${PHP_INI_DIR}/php.ini" && \
	sed -ri -e 's|^;?log_errors.*|log_errors = On|g' "${PHP_INI_DIR}/php.ini" && \
	sed -ri -e 's|^;?error_log.*|error_log = /proc/self/fd/2|g' "${PHP_INI_DIR}/php.ini" "${PHP_INI_DIR}/php-fpm.conf" && \
	sed -ri -e 's|^;?log_buffering.*|log_buffering = no|g' "${PHP_INI_DIR}/php-fpm.conf" && \
	sed -ri -e 's|^;?catch_workers_output.*|catch_workers_output = yes|g' "${PHP_INI_DIR}/pool.d/www.conf"

# Configure NGINX
COPY <<EOF /etc/nginx/sites-available/default
set_real_ip_from	172.16.0.0/12;
real_ip_header		X-Forwarded-For;
real_ip_recursive	on;

# ignore 2xx, 3xx and 404 in access log
map \$status \$loggable {
    ~^[23]  0;
	404     0;
    default 1;
}

# pseudo-anonymize client ip in log
map \$remote_addr \$remote_addr_anon {
    ~(?P<ip>\d+\.\d+\.\d+)\.    \$ip.x;
    ~(?P<ip>[^:]+:[^:]+:[^:]+): \$ip::x;
    127.0.0.1                   \$remote_addr;
    ::1                         \$remote_addr;
    default                     0.0.0.0;
}

log_format main '\$remote_addr_anon - \$remote_user [\$time_local] "\$request" '
    '\$status \$body_bytes_sent "\$http_referer" '
    '"\$http_user_agent" "\$http_x_forwarded_for"';

server {
    listen 80 default_server;
    server_name _;
    root /var/www/html/public;

    index index.php;

    charset utf-8;

    # change 'combined' to main in the access_log to use the semi-anonymized ip addresses from above
    error_log stderr warn;
    access_log /proc/self/fd/1 combined if=\$loggable;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php$ {
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param PHP_VALUE "error_log=/proc/self/fd/2";
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        include snippets/fastcgi-php.conf;

        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }
}
EOF

EXPOSE 80

ENTRYPOINT [ "/entrypoint.sh" ]