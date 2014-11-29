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

echo "cd $directory"
cd $directory || exit 1

#Download changes from origin
echo "git fetch $remote 2>&1"
git fetch $remote 2>&1

#Discard local changes and use latest from remote
echo "git reset --hard $remote/$branch 2>&1"
git reset --hard $remote/$branch 2>&1

echo "git submodule init"
git submodule init

echo "git submodule update"
git submodule update

echo "composer install"
composer install
