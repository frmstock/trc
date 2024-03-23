FROM nginx:1.20

MAINTAINER frmstock@163.com
LABEL maintainer frmstock@163.com

VOLUME /var/run/frmlinux

COPY ./etc /etc/
COPY ./www /opt/trc/www/

RUN ln -s /var/run/frmlinux/plugins/www /opt/trc/www/plugins
