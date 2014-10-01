#!/bin/bash
# Adapted from stage1/docker-php

export PATH=$PATH:/home/travis/.phpenv/bin
eval "$(phpenv init -)"
phpenv rehash 2>/dev/null

# Exit on first error
set -e

# Mount rount
mount -t tmpfs none /root

# Kill background processes on exit
trap 'kill $(jobs -p)' SIGINT SIGTERM EXIT

# Workaround with cgroup for recent version of docker
cgroups-umount
cgroups-mount

# Start docker daemon
docker -d -H 0.0.0.0:2375 -H unix:///var/run/docker.sock 2>> /dev/null >> /dev/null &
sleep 2

# $init is set by sekexe
cd $(dirname $init)/..
export TRAVIS=true; php vendor/bin/phpunit
