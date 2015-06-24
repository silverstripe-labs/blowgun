#!/bin/sh -e

BUILDDIR=./.build
LATEST_TAG=`git describe --abbrev=0 --tags`
S3_LOCATION=ss-packages/blowgun/

DPKG_DEB=`which dpkg-deb` || (echo "dpkg-deb is not installed. 'brew install dpkg' is your friend." && exit 2)
PHAR_COMPOSER=`which phar-composer` || (echo "phar-composer isnt installed. see https://github.com/clue/phar-composer#install" && exit 2)

rm -rf ${BUILDDIR} && mkdir -p ${BUILDDIR}

phar-composer build .
chmod a+x blowgun.phar
mv blowgun.phar .build/blowgun

cp -R scripts ${BUILDDIR}
cd ${BUILDDIR}

printf "\nCompressing scripts\n"

tar -zcf scripts.tar.gz scripts
rm -rf scripts

VERSION="blowgun_latest"
mkdir $VERSION

mkdir -p $VERSION/usr/local/bin
cp blowgun $VERSION/usr/local/bin/blowgun
cp ../install/bootstrapper $VERSION/usr/local/bin/bootstrapper

mkdir -p $VERSION/etc/init.d/
cp ../install/blowgun.init $VERSION/etc/init.d/blowgun
cp ../install/bootstrapper.init $VERSION/etc/init.d/bootstrapper
chmod 0755 $VERSION/etc/init.d/blowgun
chmod 0755 $VERSION/etc/init.d/bootstrapper

mkdir -p $VERSION/opt/blowgun
cp ../scripts/* $VERSION/opt/blowgun/

# setup control stuff
mkdir $VERSION/DEBIAN
cp ../install/control $VERSION/DEBIAN/control
cp ../install/postinst $VERSION/DEBIAN/postinst
chmod 0775 $VERSION/DEBIAN/postinst
$DPKG_DEB --build $VERSION

rm -rf $VERSION

printf "\nUploading\n"

for file in *
do
	echo " + ${file} --> https://s3-ap-southeast-2.amazonaws.com/${S3_LOCATION}${file}"
	aws s3 cp --profile silverstripe --only-show-errors --acl "public-read" "$file" "s3://${S3_LOCATION}" || break
done

rm -rf ${BUILDDIR}
