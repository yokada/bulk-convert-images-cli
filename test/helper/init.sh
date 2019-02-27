#!/bin/sh -x

cd `dirname $0`
projectdir=`pwd`/../../..

mysql -u root -prootpass -e 'drop database if exists bulk_convert_images;'
mysql -u root -prootpass -e 'create database if not exists `bulk_convert_images` default character set utf8mb4;'
cd ${projectdir}
source .env
vendor/bin/wp core install --title=bulk_convert_images --admin_user=admin --admin_password=admin --admin_email=test@example.com --url=${WP_CONTENT_URL} --path=public/wordpress
vendor/bin/wp bulk-convert-images register-testdata --images='bulk-convert-images-cli/test/helper/images/*.png'
