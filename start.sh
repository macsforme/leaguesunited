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
		rm run/$PORT-bzfs.txt
	done

	if [[ $BZLURPLYSRVPORT ]]
	then
		rm run/$BZLURPLYSRVPORT-pid.txt
		rm run/$BZLURPLYSRVPORT-bzfs.txt
		rm run/$BZLURPLYSRVPORT-plugins.txt
	fi

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
BZLUDBGLVL=""
BZLUBANFILE=support/bans.txt

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
		echo -e "HiX HiX\nDucati Ducati\nDucatiMini DucatiMini\nBabel Babel\nPillbox Pillbox" > run/$PORT-maplist.txt

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

			BZLUSRVTITLE="Official Leagues United Match Server :: $BZLUMAP"
			if [[ $BZLUSRVLOC ]]
			then
				BZLUSRVTITLE="$BZLUSRVTITLE :: $BZLUSRVLOC"
			fi
			echo -publictitle \"$BZLUSRVTITLE\" > run/$PORT-bzfs.txt

			if [[ $BZLUSRVADDR ]]
			then
				BZLUSRVADDRARG="$BZLUSRVADDR:$PORT"
			else
				BZLUSRVADDRARG=
			fi

			echo -publickey $BZLUSRVKEY >> run/$PORT-bzfs.txt

			echo -p $PORT >> run/$PORT-bzfs.txt

			echo -pidfile run/$PORT-pid.txt >> run/$PORT-bzfs.txt

			echo -conf support/bzfs.txt >> run/$PORT-bzfs.txt

			echo -e "[leagueOverSeer]" > run/$PORT-plugins.txt
			echo -e "\tROTATIONAL_LEAGUE=true" >> run/$PORT-plugins.txt
			echo -e "\tMAPCHANGE_PATH=run/$PORT-map.txt" >> run/$PORT-plugins.txt
			echo -e "\tLEAGUE_OVERSEER_URL=http://leaguesunited.org/api/leagueOverseer" >> run/$PORT-plugins.txt
			echo -e "\tDEBUG_LEVEL=0" >> run/$PORT-plugins.txt
			echo -e "" >> run/$PORT-plugins.txt
			echo -e "[mapchange]" >> run/$PORT-plugins.txt
			echo -e "\tConfigurationFile=run/$PORT-maplist.txt" >> run/$PORT-plugins.txt
			echo -e "\tOutputFile=run/$PORT-map.txt" >> run/$PORT-plugins.txt
			echo -e "" >> run/$PORT-plugins.txt
			echo -e "[ServerControl]" >> run/$PORT-plugins.txt
			echo -e "\tBanFile=$BZLUBANFILE" >> run/$PORT-plugins.txt

			if [[ $BZLUMAP == "Ducati" ]]
			then
				BZLUMAPARG="-conf support/Ducati.conf"
			elif [[ $BZLUMAP == "DucatiMini" ]]
			then
				BZLUMAPARG="-conf support/DucatiMini.conf"
			else
				BZLUMAPARG="-world support/$BZLUMAP.bzw"
			fi
			echo $BZLUMAPARG >> run/$PORT-bzfs.txt

			echo \
				-loadplugin $BZLIBDIR/leagueOverSeer.so,run/$PORT-plugins.txt \
				-loadplugin $BZLIBDIR/mapchange.so,run/$PORT-plugins.txt \
				-loadplugin $BZLIBDIR/serverControl.so,run/$PORT-plugins.txt \
				-loadplugin $BZLIBDIR/TimeLimit.so,15,20,30 >> run/$PORT-bzfs.txt

			if [[ "$BZLUDBGLVL" == 1 ]]
			then
				echo -d >> run/$PORT-bzfs.txt
			elif [[ "$BZLUDBGLVL" == 2 ]]
			then
				echo -dd >> run/$PORT-bzfs.txt
			elif [[ "$BZLUDBGLVL" == 3 ]]
			then
				echo -ddd >> run/$PORT-bzfs.txt
			elif [[ "$BZLUDBGLVL" == 4 ]]
			then
				echo -dddd >> run/$PORT-bzfs.txt
			else
				echo -loadplugin $BZLIBDIR/logDetail.so >> run/$PORT-bzfs.txt
			fi

			echo -ts >> run/$PORT-bzfs.txt

			echo -banfile $BZLUBANFILE >> run/$PORT-bzfs.txt

			# Start the server
			$BZBINDIR/bzfs \
				-conf run/$PORT-bzfs.txt \
				-publicaddr $BZLUSRVADDRARG \
				>> logs/$PORT.txt \
				2> logs/errors/$PORT.txt

			sleep 1
		done &
	done

	# Start loop for replay port
	if [[ $BZLURPLYSRVPORT ]]
	then
		while [[ -e run/main.keepalive ]]
		do
			# Prepare configuration
			BZLUSRVTITLE="Official Leagues United Replay Server"
			if [[ $BZLUSRVLOC ]]
			then
				BZLUSRVTITLE="$BZLUSRVTITLE :: $BZLUSRVLOC"
			fi
			echo -publictitle \"$BZLUSRVTITLE\" > run/$BZLURPLYSRVPORT-bzfs.txt

			if [[ $BZLUSRVADDR ]]
			then
				BZLUSRVADDRARG="-publicaddr $BZLUSRVADDR:$BZLURPLYSRVPORT"
			else
				BZLUSRVADDRARG=
			fi

			echo -p $BZLURPLYSRVPORT >> run/$BZLURPLYSRVPORT-bzfs.txt

			echo -publickey $BZLUSRVKEY >> run/$BZLURPLYSRVPORT-bzfs.txt

			echo -pidfile run/$BZLURPLYSRVPORT-pid.txt >> run/$BZLURPLYSRVPORT-bzfs.txt

			echo -conf support/bzfs.txt >> run/$BZLURPLYSRVPORT-bzfs.txt

                        echo -e "[ServerControl]" >> run/$BZLURPLYSRVPORT-plugins.txt
                        echo -e "\tBanFile=$BZLUBANFILE" >> run/$BZLURPLYSRVPORT-plugins.txt

			echo -loadplugin $BZLIBDIR/serverControl.so,run/$BZLURPLYSRVPORT-plugins.txt >> run/$BZLURPLYSRVPORT-bzfs.txt

			if [[ "$BZLUDBGLVL" == 1 ]]
			then
				echo -d >> run/$BZLURPLYSRVPORT-bzfs.txt
			elif [[ "$BZLUDBGLVL" == 2 ]]
			then
				echo -dd >> run/$BZLURPLYSRVPORT-bzfs.txt
			elif [[ "$BZLUDBGLVL" == 3 ]]
			then
				echo -ddd >> run/$BZLURPLYSRVPORT-bzfs.txt
			elif [[ "$BZLUDBGLVL" == 4 ]]
			then
				echo -dddd >> run/$BZLURPLYSRVPORT-bzfs.txt
			else
				echo -loadplugin $BZLIBDIR/logDetail.so >> run/$BZLURPLYSRVPORT-bzfs.txt
			fi

			echo -ts >> run/$BZLURPLYSRVPORT-bzfs.txt

			echo -banfile $BZLUBANFILE >> run/$BZLURPLYSRVPORT-bzfs.txt

			echo -replay >> run/$BZLURPLYSRVPORT-bzfs.txt

			# Start the server
			$BZBINDIR/bzfs \
				-conf run/$BZLURPLYSRVPORT-bzfs.txt \
				$BZLUSRVADDRARG \
				>> logs/$BZLURPLYSRVPORT.txt \
				2> logs/errors/$BZLURPLYSRVPORT.txt

			sleep 1
		done &
	fi

	# Wait for all of those to be terminated and finish
	wait

	# Clean up
	cleanupAndUnlock
) &


# TODO
# * Create configuration option for a custom report pipe and implement in launch loop
