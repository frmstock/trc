FROM registry.cn-hangzhou.aliyuncs.com/frmstock/frmphp:1

LABEL maintainer frmstock

COPY ./php_uuid/uuid.ini /etc/php.d/
COPY ./php_uuid/uuid.so  /usr/lib64/php/modules/
COPY --chown=apache:apache ./web /opt/trc/tp5/
