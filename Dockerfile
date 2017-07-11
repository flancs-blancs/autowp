FROM ubuntu

LABEL maintainer "dmitry@pereslegin.ru"

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER 1

RUN apt-get update -qq && \
    apt-get dist-upgrade -qq --no-install-recommends --no-install-suggests -y && \
    apt-get install -qq --no-install-recommends --no-install-suggests -y \
        anacron \
        apt-utils \
        bash \
        build-essential \
        ca-certificates \
        cron \
        curl \
        dh-autoreconf \
        git \
        imagemagick \
        nginx \
        php7.0-cli \
        php7.0-curl \
        php7.0-fpm \
        php7.0-intl \
        php7.0-json \
        php7.0-gd \
        php7.0-mbstring \
        php7.0-mysql \
        php7.0-xml \
        php7.0-zip \
        php-imagick \
        php-memcache \
        php-memcached \
        php-xdebug \
        supervisor \
        tzdata && \
    \
    curl -sL https://deb.nodesource.com/setup_6.x | bash - && \
    apt-get install -qq --no-install-recommends --no-install-suggests -y nodejs && \
    \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --quiet && \
    rm composer-setup.php && \
    \
    mkdir -p /var/log/supervisor && \
    rm /etc/nginx/sites-enabled/default && \
    mkdir -p /var/run/php/ && \
    rm /etc/php/7.0/fpm/pool.d/www.conf && \
    rm /etc/php/7.0/cli/conf.d/20-xdebug.ini && \
    rm /etc/php/7.0/fpm/conf.d/20-xdebug.ini

COPY ./etc/ /etc/

ADD composer.json /app/composer.json
RUN php ./composer.phar install --no-progress --no-interaction --no-suggest --optimize-autoloader

ADD package.json /app/package.json
RUN npm install -y --silent --production

ADD . /app

RUN chmod +x zf && \
    chmod +x start.sh && \
    chmod +x wait-for-it.sh && \
    mkdir logs && chmod 0777 logs && \
    mkdir public_html/img && \
    crontab ./crontab

RUN ./node_modules/.bin/webpack -p

EXPOSE 80

CMD ["./start.sh"]
