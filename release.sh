#!/bin/sh

BUILDDIR=./.build
LATEST_TAG=`git describe --abbrev=0 --tags`
S3_LOCATION=ss-packages/blowgun/

rm -rf ${BUILDDIR} && mkdir -p ${BUILDDIR}

phar-composer build .
chmod a+x blowgun.phar
cp blowgun.phar .build/blowgun

cp -R scripts ${BUILDDIR}
cd ${BUILDDIR}

printf "\nCompressing scripts\n"

tar -zcf scripts.tar.gz scripts
rm -rf scripts

printf "\nUploading\n"

for file in *
do
	echo " + ${file} --> https://s3-ap-southeast-2.amazonaws.com/${S3_LOCATION}${file}"
	aws s3 cp --profile silverstripe --only-show-errors --acl "public-read" "$file" "s3://${S3_LOCATION}" || break
done
