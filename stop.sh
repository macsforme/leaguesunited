#!/bin/bash

# Change directory to the one containing this script, which allows this script to be called from anywhere
cd "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# We only run if the lock file exists
if [[ ! -e run/main.lock ]]
then
	echo Servers not running.
	exit 1
fi

# Set default configuration values (only the ones we need), then load configuration to override them as appropriate
BZLURPLYSRVPORT=5195
BZLUSRVPORT="5196 5197 5198"

if [[ -f config.txt ]]
then
	source config.txt
fi

# Remove keepalive file so main script doesn't try to respawn the servers
rm run/main.keepalive 2>/dev/null

# Kill the servers (is it safe to cat the pidfiles into a command?)
for PORT in $BZLUSRVPORT
do
	kill `cat run/$PORT-pid.txt` 2>/dev/null
done
kill `cat run/$BZLURPLYSRVPORT-pid.txt` 2>/dev/null

# Give the start script a few seconds to remove the lock file, then go ahead and do it
COUNTDOWN=5

while [[ $COUNTDOWN -gt 0 ]] && [[ -e run/main.lock ]]
do
	sleep 1
	: $((--COUNTDOWN))
done

if [[ -e run/main.lock ]]
then
	rmdir run/main.lock
fi
