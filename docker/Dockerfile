FROM phpswoole/swoole

# @see https://my.vertica.com/download/vertica/client-drivers/
RUN \
    apt-get update              && \
    apt-get install -y             \
        unixodbc-dev               \
        --no-install-recommends && \
    docker-php-source extract                                                  && \
    cd /usr/src/php/ext/odbc                                                   && \
    phpize                                                                     && \
    sed -ri 's@^ *test +"\$PHP_.*" *= *"no" *&& *PHP_.*=yes *$@#&@g' configure && \
    ./configure --with-unixODBC=shared,/usr                                    && \
    docker-php-ext-install odbc                                                && \
    docker-php-source delete                                                   && \
    cd /                                                                                                               && \
    curl -sfkL --remote-name https://www.vertica.com/client_drivers/9.2.x/9.2.1-0/vertica-client-9.2.1-0.x86_64.tar.gz && \
    tar -zxvf vertica-client-9.2.1-0.x86_64.tar.gz                                                                     && \
    rm  -f  vertica-client-9.2.1-0.x86_64.tar.gz                                                                       && \
    rm  -rf /var/lib/apt/lists/*

COPY ./rootfilesystem /
