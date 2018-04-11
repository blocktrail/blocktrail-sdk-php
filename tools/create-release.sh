git status

# desired new tag
TAG=$1
if [ "${TAG}" = "" ]; then
    echo "Provide a tag!"
    exit -1
fi

# strips off the v from your input
TAG=$(echo $TAG | sed 's/^v//g')

# update version number in src/Blocktrail.php
sed -ri 's/const SDK_VERSION = "[0-9]+\.[0-9]+\.[0-9]+";/const SDK_VERSION = "'$TAG'";/g' $(git rev-parse --show-toplevel)/src/Blocktrail.php

echo "tag with v$TAG? (y/N): ";
read in

if [ "${in}" != "y" ] && [ "${in}" != "Y" ]; then
    echo "don't proceed with release"
    exit 1
fi

# commit the updated version number
git commit -am "release v${TAG}"

# tag the version
git tag -s -m "v$TAG" v$TAG

echo "push? (y/N): ";
read in

if [ "${in}" != "y" ] && [ "${in}" != "Y" ]; then
    echo "don't push"
    exit 1
fi

# push
git push
git push --tags

