FROM scratch

LABEL maintainer frmstock

ADD alpine-minirootfs-3.17.3-x86_64.tar.gz /
RUN apk add jq && \
    apk add redis && \
    apk add mysql-client && \
    rm -rf /var/cache/apk/*

WORKDIR /
COPY --chmod=755 ./code/myusers_deamon ./code/install_plugins /
COPY ./plugins /plugins
CMD ./myusers_deamon
