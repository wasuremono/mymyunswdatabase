<?
// COMP3311 13s2 Assignment 3
// Global configuration file
// Written by John Shepherd, 2008...2013
// This file must be included at the start of all scripts

// Global configuration constants

define("BASE_DIR","/home/kngx286/Comp3311/ass3");
define("LIB_DIR",BASE_DIR."/lib");
define("DB_CONNECTION","dbname=ass3");

// Important libraries which are always included

require_once(LIB_DIR."/db.php");
require_once(LIB_DIR."/rules.php");
require_once(LIB_DIR."/ass3.php");

# libs: include a bunch of libraries
# - library names supplied as arguments
# - names containing / or .php are assumed to be local libraries
# - all other names are assumed to be libraries from SYS/lib

function libs()
{
	$libs = func_get_args();
	foreach ($libs as $lib)
	{
		if (strstr($lib,'/') || (strstr($lib,'.php')))
			$libFile = $lib;
		else
			$libFile = LIB_DIR."/$lib.php";
		require_once("$libFile");
	}
}

?>
