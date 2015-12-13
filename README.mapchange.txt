================================================================================
  BZFlag Server Plugin :: mapchange
================================================================================

This plugin was written by Gnurdux, modified by allejo to work with BZFlag
2.4.4, and then updated by Constitution with a few tweaks to make it work better
for BZFlag Leagues United.

This plugin loads a file with a list of configurations, and then when the
/mapchange command is executed, it writes a file with the next configuration to
use and then shuts down the server. Your map list file should have the following
format:

---------------------------------------
HiX hix.conf
Babel babel.conf
Pillbox pillbox.conf
Ducati ducati.conf
---------------------------------------

When you execute (for example) "/mapchange HiX", the plugin will write out
"hix.conf" to the specified file and then shut down the server.

To use this plugin, you must create a configuration file, which should have
the following format:

---------------------------------------
[mapchange]
	ConfigurationFile = maplist.txt
	OutputFile = map.out
---------------------------------------

Then, when you load the plugin, specify the name of the configuration file,
like this:

---------------------------------------
-loadplugin /path/to/mapchange.so,/path/to/configuration.txt
---------------------------------------

The commands enabled by this plugin are:

---------------------------------------
/mapchange
/maplist
/maprandom
---------------------------------------

Enjoy!
