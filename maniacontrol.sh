#!/bin/sh
php maniacontrol.php SMS </dev/null >maniacontrol.log 2>&1 &
echo $!
echo $! > .pid
