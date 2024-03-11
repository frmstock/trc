FROM scratch

LABEL maintainer frmstock

ADD alpine-minirootfs-3.17.3-x86_64.tar.gz /
RUN apk add zip && \
    apk add openssl && \
    apk add uuidgen && \
    apk add mysql-client && \
    rm -rf /var/cache/apk/*

COPY ./trc /trc
RUN chmod 755 /trc/installer.sh && \
    chmod 755 /trc/bin/trcctr && \
    chmod 755 "/trc/agent/centos6 64/curl" && \
    chmod 755 "/trc/agent/centos6 64/install.sh" && \
    chmod 755 "/trc/agent/centos6 64/jq" && \
    chmod 755 "/trc/agent/centos6 64/trc" && \
    chmod 755 "/trc/agent/centos6 32/curl" && \
    chmod 755 "/trc/agent/centos6 32/install.sh" && \
    chmod 755 "/trc/agent/centos6 32/jq" && \
    chmod 755 "/trc/agent/centos6 32/trc" && \
    chmod 755 "/trc/agent/centos5 64/curl" && \
    chmod 755 "/trc/agent/centos5 64/install.sh" && \
    chmod 755 "/trc/agent/centos5 64/jq" && \
    chmod 755 "/trc/agent/centos5 64/trc" && \
    chmod 755 "/trc/agent/centos5 32/curl" && \
    chmod 755 "/trc/agent/centos5 32/install.sh" && \
    chmod 755 "/trc/agent/centos5 32/jq" && \
    chmod 755 "/trc/agent/centos5 32/trc"

WORKDIR /trc
CMD ./installer.sh
