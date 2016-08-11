#!/bin/sh -e

if [ -z "$1" ]; then
	echo "Please pass a version tag as an argument"
	exit 1
fi

BUILDDIR=/tmp/blowgun-builds
VERSION=$1
BUILDNAME="blowgun-${VERSION}"
OUTPUTFILE="${BUILDNAME}.phar"
S3_LOCATION=ss-packages/blowgun/
COMPOSER=`which composer` || (echo "composer is not installed. See https://getcomposer.org/" && exit 2)
PHAR_COMPOSER=`which phar-composer` || (echo "phar-composer is not installed. See https://github.com/clue/phar-composer#install" && exit 2)

if [ -d "${BUILDDIR}" ]; then
	rm -rf ${BUILDDIR}
fi

mkdir -p ${BUILDDIR}
cd $BUILDDIR

if [ -d "${VERSION}" ]; then
	rm -rf ${VERSION}
fi

mkdir -p ${VERSION}
cd ${VERSION}

git clone git@github.com:silverstripe-platform/blowgun.git .
git checkout ${VERSION}
$COMPOSER install --no-dev --prefer-dist
$PHAR_COMPOSER build .
chmod a+x blowgun.phar
mv blowgun.phar $OUTPUTFILE

printf "\nUploading ${OUTPUTFILE}\n"

echo " + ${OUTPUTFILE} --> https://s3-ap-southeast-2.amazonaws.com/${S3_LOCATION}${OUTPUTFILE}"
aws s3 cp --profile silverstripe --only-show-errors --acl "public-read" "${OUTPUTFILE}" "s3://${S3_LOCATION}" || break

rm -rf ${BUILDDIR}
