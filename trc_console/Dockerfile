FROM registry.cn-hangzhou.aliyuncs.com/frmstock/frmphp:1

MAINTAINER frmstock@163.com
LABEL maintainer frmstock@163.com

VOLUME /var/run/frmlinux

COPY ./php_uuid/uuid.ini /etc/php.d/
COPY ./php_uuid/uuid.so  /usr/lib64/php/modules/
COPY --chown=apache:apache ./web /opt/trc/tp5/

RUN ln -s /var/run/frmlinux/plugins/php /opt/trc/tp5/application/plugins/controller

