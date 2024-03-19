扩展插件功能


开发一个功能插件，假设插件名称为“myplugins”，需要开发三处程序
1、探针端插件程序，包含以下文件
   插件程序：myplugins(linux)/myplugins.exe(win)，放入目录./plug-ins
   定时执行配置文件：myplugins.conf，放入目录./cron
   插件配置文件：myplugins.conf，放入目录./conf，如果插件没有配置项，则不需要此文件
2、服务端数据入库程序
   分两步完成
   一是准备数据库，建库建表，可自行准备，也可和平台使用同一个mysql
   二是开发数据库入库程序
3、服务端web展示页面
   开发php和html页面


下面以一个具体示例，讲讲各部分开发的约束规范，工程为trc_demo。
插件名称为“myusers”，功能是获取系统用户列表，每天执行一次，采集的数据入库到mysql中，和平台使用同一个，并通过web查看
一、探针端插件程序，插件配置文件
   文件名称：./conf/myusers.conf
   此示例不需要此配置文件
   该配置文件平台不会使用，是插件使用的，具体内容自定义
二、探针端插件程序，定时执行配置文件
   文件名称：./cron/myusers.conf
   内容只有一行，linux如下：
   plugins_interval=1440
   win如下：
   1440
   说明：
   1、1440是一天的分钟数，即24*60，是插件执行的时间间隔，最小为5，最好是2的倍数，如6，即每6分钟执行一次
   2、等号前后没有空格
   3、采集utf-8编码，在win下编辑时，不要换行，因win下的换行符和linux下的不同
三、探针端插件程序，插件程序
   文件名称(linux)：./plug-ins/myusers
   文件名称(win)：./plug-ins/myusers.exe
   这里只给出linux下插件的源码示例
   插件开发是语言无关的，可自行选择自己擅长的开发语言，这里为了简单使用shell编写
   #!/bin/sh
   
   users=`cat /etc/passwd 2>/dev/null | cut -d: -f1 2>/dev/null | tr '\n'  ' ' 2>/dev/null`
   echo "{\"list\":\"$users\"}" > "$TRC_TMP/result/myusers.rst"
   说明：
   1、采集数据以json的形式写入文件中，文件名为myusers.rst
   2、数据文件的位置为“$TRC_TMP/result/myusers.rst”，其中变量$TRC_TMP，从环境变量中取值
   3、写数据文件时，非追加模式，文件若不存在时需创建，存在时覆盖

四、服务端数据入库程序
   1、创建表
   DROP TABLE IF EXISTS `myusers`;
   CREATE TABLE `myusers`  (
     `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
     `terminal_id` bigint(20) UNSIGNED NOT NULL,
     `list` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
     `update_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
     PRIMARY KEY (`id`) USING BTREE,
     UNIQUE INDEX `terminal_id`(`terminal_id`) USING BTREE,
     CONSTRAINT `myusers_ibfk_1` FOREIGN KEY (`terminal_id`) REFERENCES `terminal` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
   ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;
   2、入库程序，做成镜像，在docker中以单独的容器运行
   这里为了简单使用shell编写
   #!/bin/sh
   redis_server=127.0.0.1
   redis_port=6379
   
   mysql_server=127.0.0.1
   mysql_port=3306
   mysql_user=root
   mysql_passwd=mysql
   
   temp_file="plugins_myusers.tmp"
   
   while true
   do
     redis-cli -h "$redis_server" -p "$redis_port" brpop plugins:myusers 10 > "$temp_file" 2>/dev/null
     code=$?
     if [ $code -eq 0 ]
     then
       key=`sed -n '1p' "$temp_file" 2>/dev/null`
       if [ "x$key" = "xplugins:myusers" ]
       then
         value=`cat "$temp_file" 2>/dev/null`
         msg=`echo "${value:15}" 2>/dev/null | jq -rc '.' 2>/dev/null`
         
         entid=`echo "$msg" 2>/dev/null | jq -r '.entid' 2>/dev/null`
         objid=`echo "$msg" 2>/dev/null | jq -r '.objid' 2>/dev/null`
         if [ "x$entid" != "xnull" -a "x$objid" != "xnull" ]
         then
           users=`echo "$msg" 2>/dev/null | jq -r '.list' 2>/dev/null`
           if [ "x$users" != "xnull" ]
           then
             mysql -h "$mysql_server" -P "$mysql_port" -u"$mysql_user" -p"$mysql_passwd" -e \
                      "insert into \`trc\`.\`myusers\`(\`terminal_id\`, \`list\`) \
                       values($objid, '"$users"') \ 
                       on duplicate key update \
                       terminal_id=$objid, update_at=now();\n"
           fi
         fi
       fi
     fi
      
     rm -f "$temp_file"
   done
   说明：插件上报的数据从redis中取，队列的key是“plugins:myusers”
五、服务端web展示页面
   这部分的代码还未做分离，暂只能在原工程中添加插件的代码，以后再做分离
   1、php端的源码在工程trc_console中写
   2、静态页面在工程trc_nginx中添加
   这部分代码简单，这里不再列出，自行查看示例源码
   这部分修改完成后，需要重新生成镜像及容器


打包部署，部署自己的插件程序前，需要正确的安装平台，且平台是运行状态，假设安装的参数都是默认值
一、探针端和入库端
   1、生成镜像
   docker build -t myusers:1 .
   2、部署镜像
   docker run -d --name trc_myusers --net=container:trc_main myusers:1
   3、部署插件
   docker run --rm --name trc_myusers_agent -v /opt/trc/data/trc:/opt/trc --net=container:trc_main myusers:1 /install_plugins
   部署后，就可以直接平台上的插件下发功能直接把此插件下发相应终端上进行安装
二、展示端
   1、生成镜像
   cd trc_console; docker build -t trc_console:1.1 .
   cd trc_nginx; docker build -t trc_nginx:1.1 .
   2、重新创建容器
   trcctr stop
   docker rm trc_console
   docker rm trc_nginx
   docker run -d --name trc_console -v /etc/localtime:/etc/localtime:ro -v "/opt/trc/data/trc/task":/opt/trc/tp5/task/ --net=container:trc_main trc_console:1.1
   docker run -d --name trc_nginx -v /etc/localtime:/etc/localtime:ro -v "/opt/trc/data/trc/task":/opt/trc/www/task -v "/opt/trc/data/trc/export":/opt/trc/www/export -v "/opt/trc/data/trc/update":/opt/trc/www/update -v "/opt/trc/data/trc/nginx/cert":/opt/trc/cert/ --net=container:trc_main trc_nginx:1.1
三、部署后，启动顺序
   1、trcctr start
   2、docker start trc_myusers
   
