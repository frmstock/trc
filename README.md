插件开发约束


以trc_plg_demo为例
目录结构如下，目录结构不能改变
trc_demo
  ├── alpine-minirootfs-3.17.3-x86_64.tar.gz	# 基础镜像，不需要改动
  ├── code										# 后台程序目录
  │   ├── install_plugins						# 插件安装程序，不需要改动
  │   ├── install_plugins2						# 插件安装程序，不需要改动
  │   ├── myusers_deamon						# 入库程序，需要根据插件功能进行修改
  │   ├── plugins.conf							# 插件信息配置文件，需要修改
  │   ├── plugins_register						# 插件注册程序，不需要改动
  │   └── trc_myusers.sql						# 插件使用的表结构，根据插件功能进行修改
  ├── Dockerfile								# 说明见下文
  ├── lib64										# 不需要改动
  │   └── libfrm.so
  ├── php										# php代码，说明见下文
  │   ├── MyusersMgr.php
  │   └── Myusers.php
  ├── plugins									# 插件程序目录，说明见下文
  │   ├── cron
  │   │   └── myusers.conf						# 插件执行定时配置文件
  │   ├── plug-ins
  │   │   └── myusers							# 插件程序
  │   └── plug-ins.conf							# 插件信息配置文件
  └── www										# 静态页面，说明见下文
      ├── detail.html
      └── index.html							# 插件的首页，是必须的，其它页面由插件功能决定
  

说明：插件有三个信息，有多处用到
1、APP_NAME	   ：插件英文名称，要唯一
2、PLUGINS_NAME：插件中文名称
3、PLUGINS_DESC：插件描述

一、code/plugins.conf
	文件编码格式为utf-8
	PLUGINS_NAME=demo
	PLUGINS_DESC=插件示例
	说明：
	1、PLUGINS_NAME：插件中文名称，在插件页面显示
	2、PLUGINS_DESC：插件描述，在插件页面显示
	3、等号前后不要有空格
二、code/trc_myusers.sql
	如果插件数据要存储到数据库中，平台使用的是mariadb，那么表结构存放在此文件中
	CREATE TABLE IF NOT EXISTS `plugins_myusers`  (
	注：
	1、不要有“DROP TABLE”语句，使用“CREATE TABLE IF NOT EXISTS”
	2、表名前缀为“plugins_”，后跟插件的英文名称
三、code/myusers_deamon
	数据入库处理程序，示例为简单处理，使用的是shell，开发语言根据自己的需要自选
	#!/bin/sh
	redis_server=127.0.0.1
	redis_port=6379
	mysql_server=127.0.0.1
	mysql_port=3306
	mysql_user=root
	mysql_passwd=mysql
	
	/install_plugins2
	/install_plugins
	/plugins_register &
	mysql -h "$mysql_server" -P "$mysql_port" -u"$mysql_user" -p"$mysql_passwd" trc < /trc_myusers.sql
	......
	注：
	1、处理数据入库前，创建表结构，如果有。
	2、处理数据入库前，调用三个程序install_plugins2、install_plugins、plugins_register，
	　其中plugins_register在放入后台运行
四、Dockerfile
FROM scratch

MAINTAINER frmstock@163.com
LABEL maintainer frmstock@163.com

ADD alpine-minirootfs-3.17.3-x86_64.tar.gz /
# 根据数据入库程序的需要，添加软件的安装，这里的三个不要删除
RUN apk add jq && \
    apk add redis && \
    apk add mysql-client && \
    rm -rf /var/cache/apk/*

VOLUME /var/run/frmlinux
WORKDIR /

# 插件的英文名称，根据实际修改
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
五、plugins/cron/myusers.conf
	win下
	1440
	linux下
	plugins_interval=1440
	注：
	1、文件名称为插件的英文名称
	2、1440是一天的分钟数，即24*60，是插件执行的时间间隔，最小为5，最好是2的倍数，如6，即每6分钟执行一次
	3、等号前后没有空格
	4、采集utf-8编码，在win下编辑时，不要换行，因win下的换行符和linux下的不同
六、plugins/conf/myusers.conf
	插件的配置文件，由插件定义，此示例没有用到
	文件名称为插件的英文名称
七、plugins/plug-ins/myusers
	插件程序，为了简单处理，示例使用的是shell
	文件名称是插件的英文名称，linux下没有后缀，win要带上后缀“.exe”
八、php/Myusers.php
	<?php
	namespace app\plugins\controller\myusers;
	
	use think\Model;
	
	class Myusers extends Model
	......
	注：
	namespace规则：前面的“app\plugins\controller\”固定，后“myusers”为插件的英文名称
九、php/MyusersMgr.php
	<?php
	namespace app\plugins\controller\myusers;
	
	use think\Request;
	
	use app\common\FrmController;
	use app\model\Terminal;
	use app\plugins\controller\myusers\Myusers;
	
	class MyusersMgr extends FrmController
	{
	......   
	注：
	1、namespace规则：前面的“app\plugins\controller\”固定，后“myusers”为插件的英文名称
	2、需要继承FrmController
	3、访问地址是：/index.php/plugins/myusers.myusers_mgr/getUserList　myusers为插件的英文名称
十、www/index.html
	插件的首页，是必须的
	插件列表页面地址是：<a href="/plugins.html">
十一、www/detail.html
	插件其它页面，需要与否由插件功能决定

