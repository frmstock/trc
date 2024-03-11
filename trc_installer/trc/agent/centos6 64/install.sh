#!/bin/bash

myselfname=$0
self_dir=`dirname $0 2>/dev/null`

bit=`getconf LONG_BIT`
if [ $bit -ne 64 ]
then
  echo "This installation package is only applicable to 64 bit system."
  exit -1
fi

# todo check


# check self
if [ ! -f "$self_dir/libfrm.so" ]
then
  echo "This installation package is damaged."
  exit -1
fi

if [ ! -f "$self_dir/trc" ]
then
  echo "This installation package is damaged."
  exit -1
fi

if [ ! -f "$self_dir/jq" ]
then
  echo "This installation package is damaged."
  exit -1
fi

if [ ! -f "$self_dir/curl" ]
then
  echo "This installation package is damaged."
  exit -1
fi

if [ ! -d "$self_dir/conf" ]
then
  echo "This installation package is damaged."
  exit -1
fi

if [ ! -d "$self_dir/cron" ]
then
  echo "This installation package is damaged."
  exit -1
fi

if [ ! -d "$self_dir/plug-ins" ]
then
  echo "This installation package is damaged."
  exit -1
fi


# create the required folder
mkdir -p "/usr/local/trc" 2>/dev/null
if [ ! -d "/usr/local/trc" ]
then
  echo "The folder(/usr/local/trc) creation failed."
  exit -1
fi

if [ ! -d "/usr/lib64/" ]
then
  mkdir -p "/usr/lib64/"
  if [ ! -d "/usr/lib64/" ]
  then
    echo "The folder(/usr/lib64/) creation failed."
    exit -1
  fi
fi


# install
mv "$self_dir/trc" "/usr/local/trc/"
mv "$self_dir/trc.conf" "/usr/local/trc/"
mv "$self_dir/entid" "/usr/local/trc/"
mv "$self_dir/libfrm.so" "/usr/lib64/"
mv "$self_dir/conf" "/usr/local/trc/" 2>/dev/null
mv "$self_dir/cron" "/usr/local/trc/" 2>/dev/null
mv "$self_dir/plug-ins" "/usr/local/trc/" 2>/dev/null
tmp=`echo "{\"author\":\"frmstock\"}" | jq -r .author 2>/dev/null`
if [ "x$tmp" != "xfrmstock" ]
then
  mv "$self_dir/jq" "/usr/bin/"
fi
curl --version >/dev/null 2>&1
if [ $? -ne 0 ]
then
  mv "$self_dir/curl" "/usr/bin/"
fi

# check installation
if [ ! -f "/usr/local/trc/trc" ]
then
  echo "Installation failed."
  exit -1
fi

if [ ! -f "/usr/lib64/libfrm.so" ]
then
  echo "Installation failed."
  exit -1
fi

if [ ! -f "/usr/bin/jq" ]
then
  echo "Installation failed."
  exit -1
fi

if [ ! -f "/usr/bin/curl" ]
then
  echo "Installation failed."
  exit -1
fi

if [ ! -d "/usr/local/trc/conf" ]
then
  echo "Installation failed."
  exit -1
fi

if [ ! -d "/usr/local/trc/cron" ]
then
  echo "Installation failed."
  exit -1
fi

if [ ! -d "/usr/local/trc/plug-ins" ]
then
  echo "Installation failed."
  exit -1
fi

rm -fr $0 curl jq 2>/dev/null
