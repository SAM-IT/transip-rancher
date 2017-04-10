FROM alpine

RUN apk add --no-cache --update \
    php7 \
    php7-json \
    php7-phar \
    php7-mbstring \
    php7-openssl \
    php7-zlib \
    php7-soap \
    curl
RUN ln -s /usr/bin/php7 /usr/bin/php

# Install composer
RUN curl -sS https://getcomposer.org/installer | php7 -- --install-dir=/bin --filename=composer && \
    composer global config bin-dir /bin && \
    composer global config vendor-dir /vendor
RUN mkdir /project
COPY . /project
ENV test true
CMD /usr/bin/php /project/update-haip.php