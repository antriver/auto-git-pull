#!/bin/sh

branch=master
directory=.
remote=origin

while getopts b:d:r: opt; do
	case $opt in
	b)
		branch=$OPTARG
		;;
	d)
		directory=$OPTARG
		;;
	r)
		remote=$OPTARG
		;;
	esac
done

shift $((OPTIND - 1))

echo "cd $directory" || { echo 'cd failed' ; exit 1; }
cd $directory || exit 1

#Download changes from origin
echo "git fetch $remote 2>&1"
git fetch $remote 2>&1  || { echo 'fetch failed' ; exit 1; }

#Discard local changes and use latest from remote
echo "git reset --hard $remote/$branch 2>&1"
git reset --hard $remote/$branch || { echo 'reset failed' ; exit 1; }

echo "git submodule init"
git submodule init  || { echo 'submodule init failed' ; exit 1; }

echo "git submodule update"
git submodule update  || { echo 'submodule update failed' ; exit 1; }

if [ -f "composer.json" ]; then
    echo "composer install"
    composer install || { echo 'composer install failed' ; exit 1; }
fi
