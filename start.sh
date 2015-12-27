#!/bin/bash

# Termination function
function cleanupAndUnlock {
	# Clean up generated files
	for PORT in $BZLUSRVPORT
	do
		rm run/$PORT-pid.txt
		rm run/$PORT-plugins.txt
		rm run/$PORT-map.txt
		rm run/$PORT-maplist.txt
	done

	rm run/$BZLURPLYSRVPORT-pid.txt

	# Release lock file
	rmdir run/main.lock 2>/dev/null

	# Remove the keepalive file if we were not terminated by the stop script
	if [[ -f run/main.keepalive ]]
	then
		rm run/main.keepalive
	fi
}

# Change directory to the one containing this script, which allows this script to be called from anywhere
cd "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Ensure our needed directories exist
mkdir -p logs/errors recordings run

# Try to create lock file, or exit if it exists
if [[ -e run/main.lock ]]
then
	exit 0
elif [[ `mkdir run/main.lock 2>&1` ]]
then
	echo Unable to create lock file.
	exit 1
fi

# Touch keepalive file
touch run/main.keepalive

# Set default configuration values, then load configuration to override them as appropriate
BZBINDIR=/usr/local/bin
BZLIBDIR=/usr/local/lib/bzflag
BZLUSRVKEY=""
BZLUSRVADDR=""
BZLURPLYSRVPORT=5195
BZLUSRVPORT="5196 5197 5198"
BZLUSRVLOC=""

if [[ -f config.txt ]]
then
	source config.txt
fi

# Enter subshell
(
	# Set trap so we don't leave stale info behind upon exiting
	trap cleanupAndUnlock SIGINT SIGTERM

	# Start loop for each port
	for PORT in $BZLUSRVPORT
	do
		# Create map list
		echo -e "HiX HiX\nDucati Ducati\nBabel Babel\nPillbox Pillbox" > run/$PORT-maplist.txt

		# Start loop
		while [[ -e run/main.keepalive ]]
		do
			# Prepare configuration
			if [[ -f run/$PORT-map.txt ]]
			then
				BZLUMAP=`cat run/$PORT-map.txt`
			else
				BZLUMAP=HiX
				echo $BZLUMAP > run/$PORT-map.txt
			fi

			BZLUSRVTITLE="Testing Leagues United Match Server :: $BZLUMAP"
			if [[ $BZLUSRVLOC ]]
			then
				BZLUSRVTITLE="$BZLUSRVTITLE :: $BZLUSRVLOC"
			fi

			if [[ $BZLUSRVADDR ]]
			then
				BZLUSRVADDRARG="-publicaddr $BZLUSRVADDR:$PORT"
			else
				BZLUSRVADDRARG=
			fi

			echo -e "[leagueOverSeer]" > run/$PORT-plugins.txt
			echo -e "\tROTATIONAL_LEAGUE=true" >> run/$PORT-plugins.txt
			echo -e "\tMAPCHANGE_PATH=run/$PORT-map.txt" >> run/$PORT-plugins.txt
			echo -e "\tLEAGUE_OVERSEER_URL=http://leaguetest.fairserve.net/api/leagueOverseer" >> run/$PORT-plugins.txt
			echo -e "\tDEBUG_LEVEL=1" >> run/$PORT-plugins.txt
			echo -e "" >> run/$PORT-plugins.txt
			echo -e "[mapchange]" >> run/$PORT-plugins.txt
			echo -e "\tConfigurationFile=run/$PORT-maplist.txt" >> run/$PORT-plugins.txt
			echo -e "\tOutputFile=run/$PORT-map.txt" >> run/$PORT-plugins.txt

			if [[ $BZLUMAP == "Ducati" ]]
			then
				BZLUMAPARG="-conf support/Ducati.conf"
			else
				BZLUMAPARG="-world support/$BZLUMAP.bzw"
			fi

			# Start the server
			$BZBINDIR/bzfs \
				-p $PORT \
				-publickey $BZLUSRVKEY \
				-publictitle "$BZLUSRVTITLE" \
				$BZLUSRVADDRARG \
				-pidfile run/$PORT-pid.txt \
				-conf support/bzfs.txt \
				$BZLUMAPARG \
				-loadplugin $BZLIBDIR/leagueOverSeer.so,run/$PORT-plugins.txt \
				-loadplugin $BZLIBDIR/mapchange.so,run/$PORT-plugins.txt \
				-loadplugin $BZLIBDIR/TimeLimit.so,15,20,30 \
				-loadplugin $BZLIBDIR/logDetail.so \
				>> logs/$PORT.txt \
				2> logs/errors/$PORT.txt

			sleep 1
		done &
	done

	# Start loop for replay port
	while [[ -e run/main.keepalive ]]
	do
		# Prepare configuration
		BZLUSRVTITLE="Testing Leagues United Replay Server"
		if [[ $BZLUSRVLOC ]]
		then
			BZLUSRVTITLE="$BZLUSRVTITLE :: $BZLUSRVLOC"
		fi

		if [[ $BZLUSRVADDR ]]
		then
			BZLUSRVADDRARG="-publicaddr $BZLUSRVADDR:$BZLURPLYSRVPORT"
		else
			BZLUSRVADDRARG=
		fi

		# Start the server
		$BZBINDIR/bzfs \
			-p $BZLURPLYSRVPORT \
			-publickey $BZLUSRVKEY \
			-publictitle "$BZLUSRVTITLE" \
			$BZLUSRVADDRARG \
			-pidfile run/$BZLURPLYSRVPORT-pid.txt \
			-conf support/bzfs.txt \
			-replay \
			-loadplugin $BZLIBDIR/logDetail.so \
			>> logs/$BZLURPLYSRVPORT.txt \
			2> logs/errors/$BZLURPLYSRVPORT.txt

		sleep 1
	done &

	# Wait for all of those to be terminated and finish
	wait

	# Clean up
	cleanupAndUnlock
) &


# TODO
# * Create configuration option for a custom report pipe and implement in launch loop
