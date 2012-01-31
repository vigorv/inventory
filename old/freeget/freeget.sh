#!/bin/sh
# 

# PROVIDE: freeget
# REQUIRE: DAEMON
# BEFORE:  LOGIN
# KEYWORD: shutdown

# Define these freeget_* variables in one of these files:
#	/etc/rc.conf
#	/etc/rc.conf.local
#	/etc/rc.cond.d/freeget
#
# Variables:
#	freeget_enable (YES/NO)
#	freeget_config (path to freegetd.conf)
#	freeget_args (string of arguments)
#
# DO NOT CHANGE THESE DEFAULT VALUES HERE
#
. /etc/rc.subr

name="freeget"
rcvar=`set_rcvar`

load_rc_config $name

: ${freeget_enable="NO"}
: ${freeget_config="/usr/local/etc/freegetd.conf"}
: ${freeget_args=""}
: ${freeget_user="root"}

procname="/usr/bin/perl"
command="/usr/local/bin/freegetd"
command_args="${freeget_config} ${freeget_args} &"

pidfile="/var/run/freeget.pid"

require_files="${freeget_config}"

start_postcmd="ps -o 'pid,command'|grep ${command}|head -1|awk -- '{print \$1}' > ${pidfile}"

run_rc_command "$1"
