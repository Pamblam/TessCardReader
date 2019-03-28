#!/usr/bin/php
<?php

/**
 * add_data.php
 * Add data to a data file.
 * Usage: ./add_Data.php -f [data_file_to_clean.txt] -d ["data to add"]
 * 
 * - Appends a line to end of file
 * - ./clean_data.php should be run after this script
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit','200M');

$assets = realpath(dirname(dirname(__FILE__)));

$args = getopt('f:d:');

if(empty($args) || empty($args['f'])){
	fwrite(STDERR, "Error: Missing option [-f]\n");
	fwrite(STDERR, "Usage: ./add_Data.php -f [data_file_to_clean.txt] -d [\"data to add\"]");
	exit(1);
}

$path = "$assets/data/{$args['f']}";

if(!file_exists($path)){
	fwrite(STDERR, "Error: Data file not found: $path\n");
	fwrite(STDERR, "Usage: ./add_Data.php -f [data_file_to_clean.txt] -d [\"data to add\"]");
	exit(1);
}

if(!is_readable($path) || !is_writable($path)){
	fwrite(STDERR, "Error: Data file not readable/writable: $path\n");
	fwrite(STDERR, "Usage: ./add_Data.php -f [data_file_to_clean.txt] -d [\"data to add\"]");
	exit(1);
}

if(empty($args['d'])){
	fwrite(STDERR, "Error: Missing option [-d]\n");
	fwrite(STDERR, "Usage: ./add_Data.php -f [data_file_to_clean.txt] -d [\"data to add\"]");
	exit(1);
}

file_put_contents($path, "\n{$args['d']}\n", FILE_APPEND | LOCK_EX);

echo "done\n";