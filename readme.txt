This directory contains the standard configuration and runtime scripts for
BZFlag Leagues United servers. To start, you must obtain the mapchange and
leagueOverSeer plugins used by BZFlag Leagues United and build them for your
local bzfs. mapchange is available from the "mapchange" branch of
https://github.com/macsforme/leaguesunited, and leagueOverseer is available
from the "release" branch of https://github.com/allejo/LeagueOverseer. Once
you have built and installed those two plugins, obtain this directory from the
git repository in the following way:

git clone -b serverconfig  https://github.com/macsforme/leaguesunited

Once you have obtained this directory, please copy the config.example.txt file
to "config.txt," and edit it with your bzfs list server key, your server
location and any other settings specific to your system (see the file for all
possible settings).

To start the server, execute the start.sh script. You may execute this script
from any directory (you need not have your working directory be this one). The
start.sh script should fork and then exit, spawning a loop which should start
the bzfs servers and restart them as necessary. It is safe to execute start.sh
if the servers are already running, so you may want to add that script to a
cron job or startup task so the servers automatically come up when you reboot
your system.

To stop the servers cleanly, execute the stop.sh script. If the servers are
not running, but fail to start, you may also try executing the stop.sh script
or removing the run directory and trying to start it again.

From time to time, the configuration may be updated in the git repository. To
update your servers to the latest configuration, execute stop.sh, followed by
update.sh, followed by start.sh. You cannot update the servers while they are
running, so if possible, wait until none of your servers have players on them.

To maintain the integrity of BZFlag Leagues United servers, and to maintain
consistency across all servers, please do not deviate from the standard
configuration provided here. If you think something needs to be changed,
contact the league administrators so they can decide on it and so they can
update the servers universally.
