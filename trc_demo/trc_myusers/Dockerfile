FROM scratch

MAINTAINER frmstock@163.com
LABEL maintainer frmstock@163.com

ADD alpine-minirootfs-3.17.3-x86_64.tar.gz /
RUN apk add jq && \
    apk add redis && \
    apk add mysql-client && \
    rm -rf /var/cache/apk/*

VOLUME /var/run/frmlinux
WORKDIR /

ENV APP_NAME myusers

COPY lib64 /usr/lib64
COPY --chmod=755 ./code/myusers_deamon \
                 ./code/install_plugins \
				 ./code/install_plugins2 \
				 ./code/trc_myusers.sql \
				 ./code/plugins.conf \
				 ./code/plugins_register \
				 /
COPY ./plugins /plugins
COPY ./php /php
COPY ./www /www

CMD ./myusers_deamon
