FROM ubuntu:noble

ARG BUILDER_UID=1000
ARG BUILDER_GID=1000
ARG NODE_VERSION=20
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gnupg \
        zip \
        unzip \
        git \
        nginx \
        supervisor \
        php-cli \
        php-fpm \
        php-common \
        php-mbstring \
        php-xml \
        php-pgsql \
        php-redis \
        php-intl \
        php-zip \
        php-curl \
        php-gd \
        php-bcmath \
        php-gmp \
        php-sqlite3 \
        php-opcache \
        php-xdebug \
        postgresql-client && \
    mkdir -p /etc/apt/keyrings && \
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg && \
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_VERSION}.x nodistro main" > /etc/apt/sources.list.d/nodesource.list && \
    apt-get update && \
    apt-get install -y --no-install-recommends nodejs && \
    apt-get clean && rm -rf /var/lib/apt/lists/* && \
    node -v && npm -v

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Replace the default noble appuser (uid 1000) if host UID/GID differ; otherwise reuse it.
RUN CURRENT_USER=$(getent passwd ${BUILDER_UID} | cut -d: -f1 || true) && \
    if [ -n "${CURRENT_USER}" ] && [ "${CURRENT_USER}" != "appuser" ]; then \
        userdel -r "${CURRENT_USER}" 2>/dev/null || true; \
    fi && \
    if ! getent group ${BUILDER_GID} >/dev/null 2>&1; then \
        groupadd -g ${BUILDER_GID} appuser; \
    fi && \
    if ! id appuser >/dev/null 2>&1; then \
        useradd -u ${BUILDER_UID} -g ${BUILDER_GID} -m -s /bin/bash appuser; \
    fi

# Nginx: run as appuser, single site pointing at Laravel public/.
RUN sed -ri 's!^user .*;!user appuser;!' /etc/nginx/nginx.conf && \
    rm -f /etc/nginx/sites-enabled/default
COPY --chmod=0644 docker/dev/nginx.conf /etc/nginx/sites-available/settle.conf
RUN ln -s /etc/nginx/sites-available/settle.conf /etc/nginx/sites-enabled/settle.conf && \
    mkdir -p /var/lib/nginx /var/log/nginx /run && \
    chown -R appuser:appuser /var/lib/nginx /var/log/nginx

# PHP-FPM: run pool as appuser, listen on a unix socket shared with nginx.
RUN sed -ri 's!^user = .*!user = appuser!'   /etc/php/8.3/fpm/pool.d/www.conf && \
    sed -ri 's!^group = .*!group = appuser!' /etc/php/8.3/fpm/pool.d/www.conf && \
    sed -ri 's!^listen = .*!listen = /run/php/php8.3-fpm.sock!' /etc/php/8.3/fpm/pool.d/www.conf && \
    sed -ri 's!^;?listen.owner = .*!listen.owner = appuser!'   /etc/php/8.3/fpm/pool.d/www.conf && \
    sed -ri 's!^;?listen.group = .*!listen.group = appuser!'   /etc/php/8.3/fpm/pool.d/www.conf && \
    mkdir -p /run/php && chown appuser:appuser /run/php

# Supervisor: run nginx and php-fpm side by side in foreground.
COPY --chmod=0644 docker/dev/supervisord.conf /etc/supervisor/conf.d/settle.conf

WORKDIR /var/www/html
RUN chown -R appuser:appuser /var/www/html

EXPOSE 80

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
