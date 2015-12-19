#!/bin/bash

# Change directory to the one containing this script, which allows this script to be called from anywhere
cd "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# We only update if the servers are not running
if [[ -e run/main.lock ]]
then
	echo Please shut down the servers before updating.
	exit 1
fi

# Update
git pull https://github.com/macsforme/leaguesunited serverconfig:serverconfig
