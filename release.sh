#!/bin/sh -e

if [ -z "$1" ]; then
	echo "Please pass a version tag as an argument"
	exit 1
fi

BUILDDIR=/tmp/blowgun-builds
VERSION=$1
BUILDNAME="blowgun-${VERSION}"
OUTPUTFILE="${BUILDNAME}.deb"
S3_LOCATION=ss-packages/blowgun/
DPKG_DEB=`which dpkg-deb` || (echo "dpkg-deb is not installed. 'brew install dpkg' is your friend." && exit 2)
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

mkdir -p ${BUILDNAME}
mkdir -p ${BUILDNAME}/usr/local/bin
cp blowgun.phar ${BUILDNAME}/usr/local/bin/blowgun
cp install/bootstrapper ${BUILDNAME}/usr/local/bin/bootstrapper
chmod +x ${BUILDNAME}/usr/local/bin/bootstrapper

mkdir -p ${BUILDNAME}/etc/init.d/
cp install/blowgun.init ${BUILDNAME}/etc/init.d/blowgun
cp install/bootstrapper.init ${BUILDNAME}/etc/init.d/bootstrapper
chmod 0755 ${BUILDNAME}/etc/init.d/blowgun
chmod 0755 ${BUILDNAME}/etc/init.d/bootstrapper

mkdir -p ${BUILDNAME}/opt/blowgun

# setup control stuff
mkdir ${BUILDNAME}/DEBIAN
cp install/control ${BUILDNAME}/DEBIAN/control
cp install/postinst ${BUILDNAME}/DEBIAN/postinst
chmod 0775 ${BUILDNAME}/DEBIAN/postinst
$DPKG_DEB --build ${BUILDNAME}

printf "\nUploading ${OUTPUTFILE}\n"

echo " + ${OUTPUTFILE} --> https://s3-ap-southeast-2.amazonaws.com/${S3_LOCATION}${OUTPUTFILE}"
aws s3 cp --profile silverstripe --only-show-errors --acl "public-read" "${OUTPUTFILE}" "s3://${S3_LOCATION}" || break

rm -rf ${BUILDDIR}
