#!/bin/sh

src_dir="/trc"
dst_dir="/opt/trc"
update_dir="/opt/trc/update"
err_file="$dst_dir/installer.err"
result_file="$dst_dir/installer.done"
ent_id=`uuidgen -r 2>/dev/null`

if [ ! -d "$dst_dir" ]
then
  echo "部署目录不存在"
  exit -1
fi


function installLicense()
{
  if [ -f "$dst_dir/license.bin" ]
  then
    return
  fi
  
  \cp "$src_dir/license.bin" "$dst_dir/license.bin"
}

function installSSL()
{
  local cert_dir="$dst_dir/nginx/cert"
  if [ -f "$cert_dir/server.key" -a -f "$cert_dir/server.crt" ]
  then
    return
  fi
  
  if [ ! -d "$cert_dir" ]
  then
    mkdir -p "$cert_dir"
  fi
  
  openssl genrsa -out "$cert_dir/server.key" 2048
  if [ $? -ne 0 ]
  then
    echo "openssl error" >> "$err_file"
    exit -1
  fi
  
  openssl req -new -x509 -key "$cert_dir/server.key" -out "$cert_dir/ca.crt" -days 3650 <<EOF
cn
sd
jn
frmstock
trc
frmstock
frmstock@163.com
EOF
  if [ $? -ne 0 ]
  then
    echo "openssl error" >> "$err_file"
    exit -1
  fi
  
  openssl req -new -key "$cert_dir/server.key" -out "$cert_dir/server.csr" <<EOF
cn
sd
jn
frmstock
trc
frmstock
frmstock@163.com


EOF
  if [ $? -ne 0 ]
  then
    echo "openssl error" >> "$err_file"
    exit -1
  fi
  
  openssl x509 -req -days 3650 -in "$cert_dir/server.csr" -CA "$cert_dir/ca.crt" -CAkey "$cert_dir/server.key"  -CAcreateserial -out "$cert_dir/server.crt"
  if [ $? -ne 0 ]
  then
    echo "openssl error" >> "$err_file"
    exit -1
  fi
  
  return  
}

function installConf()
{
  \cp -f "$src_dir/etc/elasticsearch.yml" "$dst_dir/etc/"
  \cp -f "$src_dir/etc/mysql.cnf" "$dst_dir/etc/"
}

function installBin()
{
  \cp -rf "$src_dir/bin" "$dst_dir/bin"
}

function installPlugins()
{
  \cp -rf "$src_dir/agent/plug-ins" "$update_dir/plug-ins"
}

function genLinuxConf()
{
  local agent_dir="$1"
  echo "ip=$server_ip" > "$agent_dir/trc.conf"
  echo "port=$server_port" >> "$agent_dir/trc.conf"
  echo "entid=$ent_id" > "$agent_dir/entid"
}

function genWinConf()
{
  local agent_dir="$1"
  echo "$server_ip" > "$agent_dir/trc.conf/ip"
  echo "$server_port" > "$agent_dir/trc.conf/port"
  echo "$ent_id" > "$agent_dir/entid"
}

function genLinuxTar()
{
  local dir_name="$1"
  local tar_name="$2"
  
  rm -fr "$src_dir/agent/trc"
  mv "$src_dir/agent/$dir_name" "$src_dir/agent/trc"
  tar zcf "$update_dir/trc/$tar_name" -C "$src_dir/agent/" ./trc
}

function genWinZip()
{
  local dir_name="$1"
  local tar_name="$2"
  
  cd "$src_dir/"
  cd "$src_dir/agent"
  rm -fr "trc"
  mv "$dir_name" "trc"
  zip -q -r "$update_dir/trc/$tar_name" trc
  cd "$src_dir/"
}

function installCentos6_64()
{
  genLinuxConf "$src_dir/agent/centos6 64"
  genLinuxTar "centos6 64" "linux.x64.tar.gz"
}

function installCentos6_32()
{
  genLinuxConf "$src_dir/agent/centos6 32"
  genLinuxTar "centos6 32" "linux.x86.tar.gz"
}

function installCentos5_64()
{
  genLinuxConf "$src_dir/agent/centos5 64"
  genLinuxTar "centos5 64" "linux.l.x64.tar.gz"
}

function installCentos5_32()
{
  genLinuxConf "$src_dir/agent/centos5 32"
  genLinuxTar "centos5 32" "linux.l.x86.tar.gz"
}

function installWin32()
{
  genWinConf "$src_dir/agent/win32"
  genWinZip "win32" "trc.win.x86.zip"
}

function installWin64()
{
  genWinConf "$src_dir/agent/win64"
  genWinZip "win64" "trc.win.x64.zip"
}

function installAgent()
{
  mkdir -p "$update_dir/trc"
  
  installCentos6_64
  installCentos6_32
  installCentos5_64
  installCentos5_32
  
  installWin32
  installWin64
}

function initMysql()
{
  local tmp
  local count
  mysql -uroot -pmysql -h 127.0.0.1 2>/dev/null <<EOF
use trc
exit
EOF
  if [ $? -eq 0 ]
  then
    rm -f /var/lib/mysql/trc_outfile/ent_id
    mysql -uroot -pmysql -h 127.0.0.1 trc -e "select uuid from enterprise into outfile '/var/lib/mysql/trc_outfile/ent_id';"
    tmp=`cat /var/lib/mysql/trc_outfile/ent_id`
    let count=`echo -n $tmp | wc -m 2>/dev/null`
    if [ $count -eq 36 ]
    then
      ent_id=$tmp
      return 0
    fi
    
    echo "database init fail." >> "$err_file"
    exit 1
  fi
  
  mysql -uroot -pmysql -h 127.0.0.1 2>/dev/null <<EOF
/*
 Navicat Premium Data Transfer

 Source Server         : zxt-dev
 Source Server Type    : MySQL
 Source Server Version : 50568
 Source Host           : 172.18.12.1:3306
 Source Schema         : trc

 Target Server Type    : MySQL
 Target Server Version : 50568
 File Encoding         : 65001

 Date: 10/09/2022 11:27:58
*/

CREATE DATABASE \`trc\` CHARACTER SET 'utf8' COLLATE 'utf8_general_ci';
USE trc;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for alert_config
-- ----------------------------
DROP TABLE IF EXISTS \`alert_config\`;
CREATE TABLE \`alert_config\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`enterprise_id\` bigint(20) UNSIGNED NOT NULL,
  \`terminal_id\` bigint(20) UNSIGNED NOT NULL,
  \`status\` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0',
  PRIMARY KEY (\`id\`) USING BTREE,
  INDEX \`enterprise_id\`(\`enterprise_id\`) USING BTREE,
  UNIQUE INDEX \`terminal_id\`(\`terminal_id\`) USING BTREE,
  CONSTRAINT \`alert_config_ibfk_1\` FOREIGN KEY (\`enterprise_id\`) REFERENCES \`enterprise\` (\`id\`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT \`alert_config_ibfk_2\` FOREIGN KEY (\`terminal_id\`) REFERENCES \`terminal\` (\`id\`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of alert_config
-- ----------------------------

-- ----------------------------
-- Table structure for alert_config_detail
-- ----------------------------
DROP TABLE IF EXISTS \`alert_config_detail\`;
CREATE TABLE \`alert_config_detail\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`alert_config_id\` bigint(20) UNSIGNED NOT NULL,
  \`item\` tinyint(1) NULL DEFAULT NULL,
  PRIMARY KEY (\`id\`) USING BTREE,
  UNIQUE INDEX \`alert_config_id\`(\`alert_config_id\`, \`item\`) USING BTREE,
  CONSTRAINT \`alert_config_detail_ibfk_1\` FOREIGN KEY (\`alert_config_id\`) REFERENCES \`alert_config\` (\`id\`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of alert_config_detail
-- ----------------------------

-- ----------------------------
-- Table structure for arp_hosts
-- ----------------------------
DROP TABLE IF EXISTS \`arp_hosts\`;
CREATE TABLE \`arp_hosts\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`enterprise_id\` bigint(20) UNSIGNED NOT NULL COMMENT '所属企业',
  \`terminal_id\` bigint(20) UNSIGNED NULL COMMENT '终端ID',
  \`ip\` char(16) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'ip',
  \`mac\` char(17) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'mac',
  \`update_at\` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (\`id\`) USING BTREE,
  UNIQUE INDEX \`arp_hosts\`(\`enterprise_id\`, \`ip\`, \`mac\`) USING BTREE,
  INDEX \`enterprise_id\`(\`enterprise_id\`) USING BTREE,
  INDEX \`terminal_id\`(\`terminal_id\`) USING BTREE,
  CONSTRAINT \`arp_hosts_ibfk_1\` FOREIGN KEY (\`enterprise_id\`) REFERENCES \`enterprise\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT \`arp_hosts_ibfk_2\` FOREIGN KEY (\`terminal_id\`) REFERENCES \`terminal\` (\`id\`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of arp_hosts
-- ----------------------------


-- ----------------------------
-- Table structure for arp_log
-- ----------------------------
DROP TABLE IF EXISTS \`arp_log\`;
CREATE TABLE \`arp_log\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`terminal_id\` bigint(20) UNSIGNED NOT NULL COMMENT '终端ID',
  \`arp_id\` bigint(20) UNSIGNED NOT NULL,
  \`create_at\` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (\`id\`) USING BTREE,
  UNIQUE INDEX \`arp_log\`(\`terminal_id\`, \`arp_id\`) USING BTREE,
  INDEX \`terminal_id\`(\`terminal_id\`) USING BTREE,
  INDEX \`arp_id\`(\`arp_id\`) USING BTREE,
  CONSTRAINT \`arp_log_ibfk_1\` FOREIGN KEY (\`terminal_id\`) REFERENCES \`terminal\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT \`arp_log_ibfk_2\` FOREIGN KEY (\`arp_id\`) REFERENCES \`arp_hosts\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;


-- ----------------------------
-- Table structure for baseline
-- ----------------------------
DROP TABLE IF EXISTS \`baseline\`;
CREATE TABLE \`baseline\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`terminal_id\` bigint(20) UNSIGNED NOT NULL,
  \`item\` tinyint(11) UNSIGNED NOT NULL,
  \`result\` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  \`result_lst\` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  \`value\` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  \`mark\` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  \`update_at\` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (\`id\`) USING BTREE,
  UNIQUE INDEX \`terminal_id\`(\`terminal_id\`, \`item\`) USING BTREE,
  CONSTRAINT \`baseline_ibfk_1\` FOREIGN KEY (\`terminal_id\`) REFERENCES \`terminal\` (\`id\`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of baseline
-- ----------------------------

-- ----------------------------
-- Table structure for enterprise
-- ----------------------------
DROP TABLE IF EXISTS \`enterprise\`;
CREATE TABLE \`enterprise\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`uuid\` char(36) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (\`id\`) USING BTREE,
  UNIQUE INDEX \`uuid\`(\`uuid\`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of enterprise
-- ----------------------------
INSERT INTO \`enterprise\` VALUES (1, '$ent_id');

-- ----------------------------
-- Table structure for export_task
-- ----------------------------
DROP TABLE IF EXISTS \`export_task\`;
CREATE TABLE \`export_task\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`enterprise_id\` bigint(20) UNSIGNED NOT NULL,
  \`type\` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '任务类型',
  \`objid\` char(36) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '数据对象',
  \`uuid\` char(36) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '存储路径',
  \`file_name\` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '下载文件',
  \`status\` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '执行状态',
  \`create_at\` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  \`exec_at\` timestamp NULL DEFAULT NULL COMMENT '执行时间',
  \`finish_at\` timestamp NULL DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (\`id\`) USING BTREE,
  INDEX \`enterprise_id\`(\`enterprise_id\`) USING BTREE,
  UNIQUE INDEX \`uuid\`(\`uuid\`) USING BTREE,
  CONSTRAINT \`export_task_ibfk_1\` FOREIGN KEY (\`enterprise_id\`) REFERENCES \`enterprise\` (\`id\`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of export_task
-- ----------------------------

-- ----------------------------
-- Table structure for plugins
-- ----------------------------
DROP TABLE IF EXISTS \`plugins\`;
CREATE TABLE \`plugins\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`code\` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '插件名称',
  \`name\` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '插件中文名称',
  \`version\` char(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '插件版本',
  \`description\` varchar(256) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '插件简介',
  \`os_type\` tinyint(1) NULL DEFAULT 0 COMMENT '1:win, 2:linux',
  \`os_bits\` tinyint(1) NULL DEFAULT 0 COMMENT '1:32, 2:64',
  \`os_list\` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '值为centos5/redhat5时，意味着是低配，即.l.',
  \`create_at\` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (\`id\`) USING BTREE,
  UNIQUE INDEX \`version\`(\`version\`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of plugins
-- ----------------------------
INSERT INTO \`plugins\` VALUES (1,'procperf','进程监控','15eccb316b96f96fbe7bc61e433058bd','只适用64位的centos5和redhat5',2,2,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (2,'procperf','进程监控','161a2bbb519d107ee21b7cfccc43e270','',1,1,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (3,'checkbaseline','安全基线检测','17b69fc39c8d807f4a830d4ed2c72804','适用32位linux，但不包含centos5和redhat5',2,1,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (4,'sysperf','资源监控','1a3e7a1202c034784f86132ffccbf8c2','',1,1,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (5,'cntrec','容器外联监控','3bbe926d053bdfd75b1feaf738fd4116','只适用64位的centos5和redhat5',2,2,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (6,'privesccheck','进程提权检测','4013644c2462574cd628f85fb5f09486','适用32位linux，但不包含centos5和redhat5',2,1,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (7,'cntrpm','容器进程监控','418bf2460d7fbf0da743c54a33d54f20','适用32位linux，但不包含centos5和redhat5',2,1,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (8,'checkbaseline','安全基线检测','5279c5cf64484da138b32a64eb1ab3bc','适用64位linux，但不包含centos5和redhat5',2,2,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (9,'sysperf','资源监控','5b43ed95208281e1a58a3ff37afa9627','适用64位linux，但不包含centos5和redhat5',2,2,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (10,'tcpmon','TCP监控','62cd0d9746e84e95d5e91a3d7cb8d9f0','只适用64位的centos5和redhat5',2,2,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (11,'cntrec','容器外联监控','62e50196cbfc84064a10f7a8ca946366','只适用32位的centos5和redhat5',2,1,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (12,'lpm','软件管理','63a49c560605be11354b58d864c9ddd7','软件管理，适用64位linux，但不包含centos5和redhat5',2,2,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (13,'tcpmon','TCP监控','64a2dc56d9e8ca8a03ad6b21011aaf63','适用32位linux，但不包含centos5和redhat5',2,1,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (14,'sysperf','资源监控','6d713a2a0a82c0660b0587ed03c85274','只适用64位的centos5和redhat5',2,2,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (15,'privesccheck','进程提权检测','739c6b9ec1e828ce9557eed7c35e94c5','只适用32位的centos5和redhat5',2,1,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (16,'sysperf','资源监控','73a291f524ee684baca2399ae6e1556c','适用32位linux，但不包含centos5和redhat5',2,1,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (17,'systat','性能监控','75abe56e147ea0ae3c34f7a81af2b3e6','适用32位linux，但不包含centos5和redhat5',2,1,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (18,'cntrpm','容器进程监控','798ea27813ca8ae678b99552b55940ba','只适用64位的centos5和redhat5',2,2,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (19,'privesccheck','进程提权检测','7e487fc9c1c8791e52184ba6bd651054','只适用64位的centos5和redhat5',2,2,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (20,'cntrpm','容器进程监控','8514f7a12a1017684e2da28dd122c735','只适用32位的centos5和redhat5',2,1,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (21,'cntrec','容器外联监控','877f0e7a4f2de2f47d3e24de75481c54','适用64位linux，但不包含centos5和redhat5',2,2,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (22,'tcpmon','TCP监控','88f7985c05f760f27d012010713b6ae8','只适用32位的centos5和redhat5',2,1,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (23,'systat','性能监控','91056e95b1bd61c04be6bfcbb7263942','适用64位linux，但不包含centos5和redhat5',2,2,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (24,'checkbaseline','安全基线检测','92844c8626d19ea019eb2cdfe2883c9d','只适用32位的centos5和redhat5',2,1,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (25,'procperf','进程监控','aaf75b6e4a843a079da6b4adae2a1ba2','适用64位linux，但不包含centos5和redhat5',2,2,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (26,'systat','性能监控','ae4142df5fe5424316ec6b870b9a1471','只适用32位的centos5和redhat5',2,1,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (27,'lpm','软件管理','b27de3304b0e06e4f35b7393a49d730b','软件管理，适用32位linux，但不包含centos5和redhat5',2,1,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (28,'sysperf','资源监控','b3d1049474b3adb0fd17d994ad41a187','',1,2,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (29,'procperf','进程监控','ba5d95a96c79fd7f7fb06fa9280ebb0d','只适用32位的centos5和redhat5',2,1,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (30,'cntrpm','容器进程监控','bfc17d46cdcfe11a42409d1c8cc7b483','适用64位linux，但不包含centos5和redhat5',2,2,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (31,'privesccheck','进程提权检测','c29d6af4e9933b4557a3f7b580083347','适用64位linux，但不包含centos5和redhat5',2,2,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (32,'cntrec','容器外联监控','c45836bc5d0a06a75450ef9385397f7d','适用32位linux，但不包含centos5和redhat5',2,1,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (33,'sysperf','资源监控','c82831c9b3540e4bcba518bb5a4eda21','只适用32位的centos5和redhat5',2,1,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (34,'checkbaseline','安全基线检测','d180b2eb6781f15d1abe255dc2e9d6cc','只适用64位的centos5和redhat5',2,2,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (35,'procperf','进程监控','dbd341eee2698be7ba7c99146ab49bd1','适用32位linux，但不包含centos5和redhat5',2,1,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (36,'procperf','进程监控','dc585d3a602698912521bb6d9b8639e5','',1,2,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (37,'tcpmon','TCP监控','e38a3342e4ca2659ab35324927e15f71','适用64位linux，但不包含centos5和redhat5',2,2,'','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (38,'systat','性能监控','e6c0e65c9aff89833ba7a18ddcdd5c1c','只适用64位的centos5和redhat5',2,2,'centos5 redhat5','2015-10-01 00:12:00');
INSERT INTO \`plugins\` VALUES (39,'hostdiscover','主机发现','a49088ff9006da1a7c36ba570e776eb8','适用64位linux，但不包含centos5和redhat5',2,2,'','2024-01-17 09:56:07');
INSERT INTO \`plugins\` VALUES (40,'hostdiscover','主机发现','5716f9e407453c5162573d9ccda77c22','适用32位linux，但不包含centos5和redhat5',2,1,'','2024-01-17 10:05:15');
INSERT INTO \`plugins\` VALUES (41,'hostdiscover','主机发现','ce319fe6d615bb4c55b1e6096043c754','只适用64位的centos5和redhat5',2,2,'centos5 redhat5','2024-01-17 10:08:38');
INSERT INTO \`plugins\` VALUES (42,'hostdiscover','主机发现','77b66047e0f644e1c4ca7c5ff3d84fdb','只适用32位的centos5和redhat5',2,1,'centos5 redhat5','2024-01-17 10:11:45');
INSERT INTO \`plugins\` VALUES (43,'hostdiscover','主机发现','0c3b4c4c9aad03579f1f5492dceea017','',1,2,'','2024-01-17 10:24:51');
INSERT INTO \`plugins\` VALUES (44,'hostdiscover','主机发现','0abbeeda17340dec7790f5f00769dc80','',1,1,'','2024-01-17 10:28:00');

-- ----------------------------
-- Table structure for plugins_files
-- ----------------------------
DROP TABLE IF EXISTS \`plugins_files\`;
CREATE TABLE \`plugins_files\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`plugins_version\` char(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  \`file_type\` tinyint(1) NULL DEFAULT 0 COMMENT '1: plug-ins, 2:cron, 3:conf',
  \`file_md5\` char(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (\`id\`) USING BTREE,
  UNIQUE INDEX \`plugins_files\`(\`plugins_version\`, \`file_type\`, \`file_md5\`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of plugins_files
-- ----------------------------
INSERT INTO \`plugins_files\` VALUES (1,'15eccb316b96f96fbe7bc61e433058bd',1,'15eccb316b96f96fbe7bc61e433058bd');
INSERT INTO \`plugins_files\` VALUES (2,'15eccb316b96f96fbe7bc61e433058bd',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (3,'15eccb316b96f96fbe7bc61e433058bd',3,'24474b0a4c7825fc7270d6e7e240ac9b');
INSERT INTO \`plugins_files\` VALUES (4,'161a2bbb519d107ee21b7cfccc43e270',1,'161a2bbb519d107ee21b7cfccc43e270');
INSERT INTO \`plugins_files\` VALUES (5,'161a2bbb519d107ee21b7cfccc43e270',2,'1679091c5a880faf6fb5e6087eb1b2dc');
INSERT INTO \`plugins_files\` VALUES (6,'161a2bbb519d107ee21b7cfccc43e270',3,'45e0edaca8702e6e90d1d98cf3647d5f');
INSERT INTO \`plugins_files\` VALUES (7,'17b69fc39c8d807f4a830d4ed2c72804',1,'17b69fc39c8d807f4a830d4ed2c72804');
INSERT INTO \`plugins_files\` VALUES (8,'17b69fc39c8d807f4a830d4ed2c72804',2,'830c3c153c32a48d375da263e0644573');
INSERT INTO \`plugins_files\` VALUES (9,'1a3e7a1202c034784f86132ffccbf8c2',1,'1a3e7a1202c034784f86132ffccbf8c2');
INSERT INTO \`plugins_files\` VALUES (10,'1a3e7a1202c034784f86132ffccbf8c2',2,'98f13708210194c475687be6106a3b84');
INSERT INTO \`plugins_files\` VALUES (11,'1a3e7a1202c034784f86132ffccbf8c2',3,'a75e7ea5502d005d11a7a5594777c2c4');
INSERT INTO \`plugins_files\` VALUES (12,'3bbe926d053bdfd75b1feaf738fd4116',1,'3bbe926d053bdfd75b1feaf738fd4116');
INSERT INTO \`plugins_files\` VALUES (13,'3bbe926d053bdfd75b1feaf738fd4116',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (14,'3bbe926d053bdfd75b1feaf738fd4116',3,'6879d7612ba1723a4dacbb941f2da21c');
INSERT INTO \`plugins_files\` VALUES (15,'4013644c2462574cd628f85fb5f09486',1,'4013644c2462574cd628f85fb5f09486');
INSERT INTO \`plugins_files\` VALUES (16,'4013644c2462574cd628f85fb5f09486',2,'540d77959724caeb75b020a3be94b06c');
INSERT INTO \`plugins_files\` VALUES (17,'418bf2460d7fbf0da743c54a33d54f20',1,'418bf2460d7fbf0da743c54a33d54f20');
INSERT INTO \`plugins_files\` VALUES (18,'418bf2460d7fbf0da743c54a33d54f20',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (19,'418bf2460d7fbf0da743c54a33d54f20',3,'6879d7612ba1723a4dacbb941f2da21c');
INSERT INTO \`plugins_files\` VALUES (20,'5279c5cf64484da138b32a64eb1ab3bc',1,'5279c5cf64484da138b32a64eb1ab3bc');
INSERT INTO \`plugins_files\` VALUES (21,'5279c5cf64484da138b32a64eb1ab3bc',2,'830c3c153c32a48d375da263e0644573');
INSERT INTO \`plugins_files\` VALUES (22,'5b43ed95208281e1a58a3ff37afa9627',1,'5b43ed95208281e1a58a3ff37afa9627');
INSERT INTO \`plugins_files\` VALUES (23,'5b43ed95208281e1a58a3ff37afa9627',2,'b99f4f11a5f368acc74e9330bf902dc6');
INSERT INTO \`plugins_files\` VALUES (24,'5b43ed95208281e1a58a3ff37afa9627',3,'aec865822ddf86911385a2e9eff3127a');
INSERT INTO \`plugins_files\` VALUES (25,'62cd0d9746e84e95d5e91a3d7cb8d9f0',1,'62cd0d9746e84e95d5e91a3d7cb8d9f0');
INSERT INTO \`plugins_files\` VALUES (26,'62cd0d9746e84e95d5e91a3d7cb8d9f0',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (27,'62cd0d9746e84e95d5e91a3d7cb8d9f0',3,'0c0bf5901a5ae7adc850c545ad0a73d6');
INSERT INTO \`plugins_files\` VALUES (28,'62e50196cbfc84064a10f7a8ca946366',1,'62e50196cbfc84064a10f7a8ca946366');
INSERT INTO \`plugins_files\` VALUES (29,'62e50196cbfc84064a10f7a8ca946366',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (30,'62e50196cbfc84064a10f7a8ca946366',3,'6879d7612ba1723a4dacbb941f2da21c');
INSERT INTO \`plugins_files\` VALUES (31,'63a49c560605be11354b58d864c9ddd7',1,'63a49c560605be11354b58d864c9ddd7');
INSERT INTO \`plugins_files\` VALUES (32,'63a49c560605be11354b58d864c9ddd7',2,'dd8b04d29557d429b40acc64c431b8c8');
INSERT INTO \`plugins_files\` VALUES (33,'64a2dc56d9e8ca8a03ad6b21011aaf63',1,'64a2dc56d9e8ca8a03ad6b21011aaf63');
INSERT INTO \`plugins_files\` VALUES (34,'64a2dc56d9e8ca8a03ad6b21011aaf63',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (35,'64a2dc56d9e8ca8a03ad6b21011aaf63',3,'0c0bf5901a5ae7adc850c545ad0a73d6');
INSERT INTO \`plugins_files\` VALUES (36,'6d713a2a0a82c0660b0587ed03c85274',1,'6d713a2a0a82c0660b0587ed03c85274');
INSERT INTO \`plugins_files\` VALUES (37,'6d713a2a0a82c0660b0587ed03c85274',2,'b99f4f11a5f368acc74e9330bf902dc6');
INSERT INTO \`plugins_files\` VALUES (38,'6d713a2a0a82c0660b0587ed03c85274',3,'aec865822ddf86911385a2e9eff3127a');
INSERT INTO \`plugins_files\` VALUES (39,'739c6b9ec1e828ce9557eed7c35e94c5',1,'739c6b9ec1e828ce9557eed7c35e94c5');
INSERT INTO \`plugins_files\` VALUES (40,'739c6b9ec1e828ce9557eed7c35e94c5',2,'540d77959724caeb75b020a3be94b06c');
INSERT INTO \`plugins_files\` VALUES (41,'73a291f524ee684baca2399ae6e1556c',1,'73a291f524ee684baca2399ae6e1556c');
INSERT INTO \`plugins_files\` VALUES (42,'73a291f524ee684baca2399ae6e1556c',2,'b99f4f11a5f368acc74e9330bf902dc6');
INSERT INTO \`plugins_files\` VALUES (43,'73a291f524ee684baca2399ae6e1556c',3,'aec865822ddf86911385a2e9eff3127a');
INSERT INTO \`plugins_files\` VALUES (44,'75abe56e147ea0ae3c34f7a81af2b3e6',1,'75abe56e147ea0ae3c34f7a81af2b3e6');
INSERT INTO \`plugins_files\` VALUES (45,'75abe56e147ea0ae3c34f7a81af2b3e6',2,'b99f4f11a5f368acc74e9330bf902dc6');
INSERT INTO \`plugins_files\` VALUES (46,'798ea27813ca8ae678b99552b55940ba',1,'798ea27813ca8ae678b99552b55940ba');
INSERT INTO \`plugins_files\` VALUES (47,'798ea27813ca8ae678b99552b55940ba',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (48,'798ea27813ca8ae678b99552b55940ba',3,'6879d7612ba1723a4dacbb941f2da21c');
INSERT INTO \`plugins_files\` VALUES (49,'7e487fc9c1c8791e52184ba6bd651054',1,'7e487fc9c1c8791e52184ba6bd651054');
INSERT INTO \`plugins_files\` VALUES (50,'7e487fc9c1c8791e52184ba6bd651054',2,'540d77959724caeb75b020a3be94b06c');
INSERT INTO \`plugins_files\` VALUES (51,'8514f7a12a1017684e2da28dd122c735',1,'8514f7a12a1017684e2da28dd122c735');
INSERT INTO \`plugins_files\` VALUES (52,'8514f7a12a1017684e2da28dd122c735',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (53,'8514f7a12a1017684e2da28dd122c735',3,'6879d7612ba1723a4dacbb941f2da21c');
INSERT INTO \`plugins_files\` VALUES (54,'877f0e7a4f2de2f47d3e24de75481c54',1,'877f0e7a4f2de2f47d3e24de75481c54');
INSERT INTO \`plugins_files\` VALUES (55,'877f0e7a4f2de2f47d3e24de75481c54',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (56,'877f0e7a4f2de2f47d3e24de75481c54',3,'6879d7612ba1723a4dacbb941f2da21c');
INSERT INTO \`plugins_files\` VALUES (57,'88f7985c05f760f27d012010713b6ae8',1,'88f7985c05f760f27d012010713b6ae8');
INSERT INTO \`plugins_files\` VALUES (58,'88f7985c05f760f27d012010713b6ae8',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (59,'88f7985c05f760f27d012010713b6ae8',3,'0c0bf5901a5ae7adc850c545ad0a73d6');
INSERT INTO \`plugins_files\` VALUES (60,'91056e95b1bd61c04be6bfcbb7263942',1,'91056e95b1bd61c04be6bfcbb7263942');
INSERT INTO \`plugins_files\` VALUES (61,'91056e95b1bd61c04be6bfcbb7263942',2,'b99f4f11a5f368acc74e9330bf902dc6');
INSERT INTO \`plugins_files\` VALUES (62,'92844c8626d19ea019eb2cdfe2883c9d',1,'92844c8626d19ea019eb2cdfe2883c9d');
INSERT INTO \`plugins_files\` VALUES (63,'92844c8626d19ea019eb2cdfe2883c9d',2,'830c3c153c32a48d375da263e0644573');
INSERT INTO \`plugins_files\` VALUES (64,'aaf75b6e4a843a079da6b4adae2a1ba2',1,'aaf75b6e4a843a079da6b4adae2a1ba2');
INSERT INTO \`plugins_files\` VALUES (65,'aaf75b6e4a843a079da6b4adae2a1ba2',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (66,'aaf75b6e4a843a079da6b4adae2a1ba2',3,'24474b0a4c7825fc7270d6e7e240ac9b');
INSERT INTO \`plugins_files\` VALUES (67,'ae4142df5fe5424316ec6b870b9a1471',1,'ae4142df5fe5424316ec6b870b9a1471');
INSERT INTO \`plugins_files\` VALUES (68,'ae4142df5fe5424316ec6b870b9a1471',2,'b99f4f11a5f368acc74e9330bf902dc6');
INSERT INTO \`plugins_files\` VALUES (69,'b27de3304b0e06e4f35b7393a49d730b',1,'b27de3304b0e06e4f35b7393a49d730b');
INSERT INTO \`plugins_files\` VALUES (70,'b27de3304b0e06e4f35b7393a49d730b',2,'dd8b04d29557d429b40acc64c431b8c8');
INSERT INTO \`plugins_files\` VALUES (71,'b3d1049474b3adb0fd17d994ad41a187',1,'b3d1049474b3adb0fd17d994ad41a187');
INSERT INTO \`plugins_files\` VALUES (72,'b3d1049474b3adb0fd17d994ad41a187',2,'98f13708210194c475687be6106a3b84');
INSERT INTO \`plugins_files\` VALUES (73,'b3d1049474b3adb0fd17d994ad41a187',3,'a75e7ea5502d005d11a7a5594777c2c4');
INSERT INTO \`plugins_files\` VALUES (74,'ba5d95a96c79fd7f7fb06fa9280ebb0d',1,'ba5d95a96c79fd7f7fb06fa9280ebb0d');
INSERT INTO \`plugins_files\` VALUES (75,'ba5d95a96c79fd7f7fb06fa9280ebb0d',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (76,'ba5d95a96c79fd7f7fb06fa9280ebb0d',3,'24474b0a4c7825fc7270d6e7e240ac9b');
INSERT INTO \`plugins_files\` VALUES (77,'bfc17d46cdcfe11a42409d1c8cc7b483',1,'bfc17d46cdcfe11a42409d1c8cc7b483');
INSERT INTO \`plugins_files\` VALUES (78,'bfc17d46cdcfe11a42409d1c8cc7b483',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (79,'bfc17d46cdcfe11a42409d1c8cc7b483',3,'6879d7612ba1723a4dacbb941f2da21c');
INSERT INTO \`plugins_files\` VALUES (80,'c29d6af4e9933b4557a3f7b580083347',1,'c29d6af4e9933b4557a3f7b580083347');
INSERT INTO \`plugins_files\` VALUES (81,'c29d6af4e9933b4557a3f7b580083347',2,'540d77959724caeb75b020a3be94b06c');
INSERT INTO \`plugins_files\` VALUES (82,'c45836bc5d0a06a75450ef9385397f7d',1,'c45836bc5d0a06a75450ef9385397f7d');
INSERT INTO \`plugins_files\` VALUES (83,'c45836bc5d0a06a75450ef9385397f7d',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (84,'c45836bc5d0a06a75450ef9385397f7d',3,'6879d7612ba1723a4dacbb941f2da21c');
INSERT INTO \`plugins_files\` VALUES (85,'c82831c9b3540e4bcba518bb5a4eda21',1,'c82831c9b3540e4bcba518bb5a4eda21');
INSERT INTO \`plugins_files\` VALUES (86,'c82831c9b3540e4bcba518bb5a4eda21',2,'b99f4f11a5f368acc74e9330bf902dc6');
INSERT INTO \`plugins_files\` VALUES (87,'c82831c9b3540e4bcba518bb5a4eda21',3,'aec865822ddf86911385a2e9eff3127a');
INSERT INTO \`plugins_files\` VALUES (88,'d180b2eb6781f15d1abe255dc2e9d6cc',1,'d180b2eb6781f15d1abe255dc2e9d6cc');
INSERT INTO \`plugins_files\` VALUES (89,'d180b2eb6781f15d1abe255dc2e9d6cc',2,'830c3c153c32a48d375da263e0644573');
INSERT INTO \`plugins_files\` VALUES (90,'dbd341eee2698be7ba7c99146ab49bd1',1,'dbd341eee2698be7ba7c99146ab49bd1');
INSERT INTO \`plugins_files\` VALUES (91,'dbd341eee2698be7ba7c99146ab49bd1',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (92,'dbd341eee2698be7ba7c99146ab49bd1',3,'24474b0a4c7825fc7270d6e7e240ac9b');
INSERT INTO \`plugins_files\` VALUES (93,'dc585d3a602698912521bb6d9b8639e5',1,'dc585d3a602698912521bb6d9b8639e5');
INSERT INTO \`plugins_files\` VALUES (94,'dc585d3a602698912521bb6d9b8639e5',2,'1679091c5a880faf6fb5e6087eb1b2dc');
INSERT INTO \`plugins_files\` VALUES (95,'dc585d3a602698912521bb6d9b8639e5',3,'45e0edaca8702e6e90d1d98cf3647d5f');
INSERT INTO \`plugins_files\` VALUES (96,'e38a3342e4ca2659ab35324927e15f71',1,'e38a3342e4ca2659ab35324927e15f71');
INSERT INTO \`plugins_files\` VALUES (97,'e38a3342e4ca2659ab35324927e15f71',2,'a7adf4644bacad2a42d772c84024e325');
INSERT INTO \`plugins_files\` VALUES (98,'e38a3342e4ca2659ab35324927e15f71',3,'0c0bf5901a5ae7adc850c545ad0a73d6');
INSERT INTO \`plugins_files\` VALUES (99,'e6c0e65c9aff89833ba7a18ddcdd5c1c',1,'e6c0e65c9aff89833ba7a18ddcdd5c1c');
INSERT INTO \`plugins_files\` VALUES (100,'e6c0e65c9aff89833ba7a18ddcdd5c1c',2,'b99f4f11a5f368acc74e9330bf902dc6');
INSERT INTO \`plugins_files\` VALUES (101,'a49088ff9006da1a7c36ba570e776eb8',1,'a49088ff9006da1a7c36ba570e776eb8');
INSERT INTO \`plugins_files\` VALUES (102,'a49088ff9006da1a7c36ba570e776eb8',2,'95ca0d5095c0dd840132e9d439ade01c');
INSERT INTO \`plugins_files\` VALUES (103,'5716f9e407453c5162573d9ccda77c22',1,'5716f9e407453c5162573d9ccda77c22');
INSERT INTO \`plugins_files\` VALUES (104,'5716f9e407453c5162573d9ccda77c22',2,'95ca0d5095c0dd840132e9d439ade01c');
INSERT INTO \`plugins_files\` VALUES (105,'ce319fe6d615bb4c55b1e6096043c754',1,'ce319fe6d615bb4c55b1e6096043c754');
INSERT INTO \`plugins_files\` VALUES (106,'ce319fe6d615bb4c55b1e6096043c754',2,'95ca0d5095c0dd840132e9d439ade01c');
INSERT INTO \`plugins_files\` VALUES (107,'77b66047e0f644e1c4ca7c5ff3d84fdb',1,'77b66047e0f644e1c4ca7c5ff3d84fdb');
INSERT INTO \`plugins_files\` VALUES (108,'77b66047e0f644e1c4ca7c5ff3d84fdb',2,'95ca0d5095c0dd840132e9d439ade01c');
INSERT INTO \`plugins_files\` VALUES (109,'0c3b4c4c9aad03579f1f5492dceea017',1,'0c3b4c4c9aad03579f1f5492dceea017');
INSERT INTO \`plugins_files\` VALUES (110,'0c3b4c4c9aad03579f1f5492dceea017',2,'66752b84a910bab95ae654c215075c18');
INSERT INTO \`plugins_files\` VALUES (111,'0abbeeda17340dec7790f5f00769dc80',1,'0abbeeda17340dec7790f5f00769dc80');
INSERT INTO \`plugins_files\` VALUES (112,'0abbeeda17340dec7790f5f00769dc80',2,'66752b84a910bab95ae654c215075c18');

-- ----------------------------
-- Table structure for task
-- ----------------------------
DROP TABLE IF EXISTS \`task\`;
CREATE TABLE \`task\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`enterprise_id\` bigint(20) UNSIGNED NOT NULL COMMENT '所属企业',
  \`uuid\` char(36) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '唯一标识',
  \`name\` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '任务名称',
  \`content\` mediumtext CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '脚本内容',
  \`type\` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '任务类型  0:unkown 1:windows 2:linux',
  \`status\` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '状态  0:新建 1:已发布',
  \`is_debug\` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '调试信息，linux -x',
  \`is_log\` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '日志信息',
  \`is_error\` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '错误信息， win',
  \`create_at\` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  \`update_at\` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (\`id\`) USING BTREE,
  UNIQUE INDEX \`uuid\`(\`uuid\`) USING BTREE,
  INDEX \`enterprise_id\`(\`enterprise_id\`) USING BTREE,
  CONSTRAINT \`task_ibfk_1\` FOREIGN KEY (\`enterprise_id\`) REFERENCES \`enterprise\` (\`id\`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '任务信息表' ROW_FORMAT = Compact;

-- ----------------------------
-- Records of task
-- ----------------------------

-- ----------------------------
-- Table structure for task_terminal
-- ----------------------------
DROP TABLE IF EXISTS \`task_terminal\`;
CREATE TABLE \`task_terminal\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`task_id\` bigint(20) UNSIGNED NOT NULL COMMENT '任务ID',
  \`terminal_id\` bigint(20) UNSIGNED NOT NULL COMMENT '终端ID',
  \`status\` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '状态  0:未领取  1:已领取 2:执行中 3:已完成',
  \`update_at\` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (\`id\`) USING BTREE,
  UNIQUE INDEX \`task_id\`(\`task_id\`, \`terminal_id\`) USING BTREE,
  INDEX \`task_terminal_ibfk_2\`(\`terminal_id\`) USING BTREE,
  CONSTRAINT \`task_terminal_ibfk_1\` FOREIGN KEY (\`task_id\`) REFERENCES \`task\` (\`id\`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT \`task_terminal_ibfk_2\` FOREIGN KEY (\`terminal_id\`) REFERENCES \`terminal\` (\`id\`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '任务终端关系表' ROW_FORMAT = Compact;

-- ----------------------------
-- Records of task_terminal
-- ----------------------------


-- ----------------------------
-- Table structure for terminal
-- ----------------------------
DROP TABLE IF EXISTS \`terminal\`;
CREATE TABLE \`terminal\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`enterprise_id\` bigint(20) UNSIGNED NOT NULL COMMENT '所属企业',
  \`uuid\` char(36) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '唯一标识',
  \`type\` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0' COMMENT '终端类型  0:unkown 1:windows 2:linux',
  \`act_time\` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '最近活动时间',
  \`reg_time\` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '注册时间',
  \`host_ip\` char(15) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '主机IP',
  \`host_name\` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '主机名',
  \`host_os\` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '操作系统',
  \`host_version\` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '系统版本',
  \`host_bits\` char(2) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '系统位数 64,32',
  \`host_uptime\` int(11) UNSIGNED NULL DEFAULT NULL COMMENT '开机时间',
  \`version\` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '程序版本',
  PRIMARY KEY (\`id\`) USING BTREE,
  UNIQUE INDEX \`uuid\`(\`uuid\`) USING BTREE,
  INDEX \`enterprise_id\`(\`enterprise_id\`) USING BTREE,
  CONSTRAINT \`terminal_ibfk_1\` FOREIGN KEY (\`enterprise_id\`) REFERENCES \`enterprise\` (\`id\`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '终端信息表' ROW_FORMAT = Compact;

-- ----------------------------
-- Records of terminal
-- ----------------------------


-- ----------------------------
-- Table structure for test
-- ----------------------------
DROP TABLE IF EXISTS \`test\`;
CREATE TABLE \`test\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`group_id\` bigint(20) UNSIGNED NULL DEFAULT NULL,
  \`terminal_id\` bigint(20) UNSIGNED NULL DEFAULT NULL,
  \`innerId\` bigint(20) UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (\`id\`) USING BTREE,
  UNIQUE INDEX \`group_id\`(\`group_id\`, \`innerId\`) USING BTREE,
  UNIQUE INDEX \`terminal_id\`(\`terminal_id\`) USING BTREE,
  INDEX \`group_id_2\`(\`group_id\`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of test
-- ----------------------------
INSERT INTO \`test\` VALUES (1, 1, 1, 1);
INSERT INTO \`test\` VALUES (2, 1, 2, 2);
INSERT INTO \`test\` VALUES (4, 1, 3, 3);
INSERT INTO \`test\` VALUES (6, 2, 4, 1);

-- ----------------------------
-- Table structure for user
-- ----------------------------
DROP TABLE IF EXISTS \`user\`;
CREATE TABLE \`user\`  (
  \`id\` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`enterprise_id\` bigint(20) UNSIGNED NOT NULL COMMENT '所属企业',
  \`username\` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '用户账号',
  \`password\` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '用户密码',
  \`email\` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '邮箱地址',
  \`reg_time\` int(11) UNSIGNED NULL DEFAULT NULL COMMENT '注册时间',
  \`act_time\` int(11) UNSIGNED NULL DEFAULT NULL COMMENT '活动时间',
  \`update_at\` int(11) UNSIGNED NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (\`id\`) USING BTREE,
  UNIQUE INDEX \`username\`(\`username\`) USING BTREE,
  INDEX \`enterprise_id\`(\`enterprise_id\`) USING BTREE,
  CONSTRAINT \`user_ibfk_1\` FOREIGN KEY (\`enterprise_id\`) REFERENCES \`enterprise\` (\`id\`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of user
-- ----------------------------
INSERT INTO \`user\` VALUES (1, 1, 'admin', 'C714CE5095C61E7F57DF9BAB0924AD3F', '', 0, 0, 0);

SET FOREIGN_KEY_CHECKS = 1;
exit
EOF
}

installLicense
installSSL

initMysql
installConf

installPlugins
installAgent
installBin

touch "$result_file"
