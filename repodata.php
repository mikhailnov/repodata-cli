#!/bin/env -S php -d display_errors

<?php

function _echo_help(string $argv0, int $exit_code)
{
	echo "Usage: " . $argv0 . " get-package-version path/to/repomd.xml package-name" . PHP_EOL;
	echo "Example: " . $argv0 . " get-package-version test-repo/repodata/repomd.xml dos2unix" . PHP_EOL;
	echo "Package version will be printined to stdout." . PHP_EOL;
	exit($exit_code);
}

function _get_db_path(string $file)
{
	$db_path = null;
	$reader = new XMLReader();
	if ($reader->open($file) != true) {
		fprintf(STDERR, "Error opening file" . $file . PHP_EOL);
		return null;
	}
	/* Example XML:
	 <repomd>
	   <data type="primary_db">
	     <location href="repodata/xxx.sqlite.bz2"/>
	   </data>
	  </repomd>
	 */ 
	// are we already inside <data type="primary_db">
	$inside_data = false;
	while($reader->read()) {
		if($reader->nodeType != XMLReader::ELEMENT)
			continue;
		if ($inside_data == false) {
			if($reader->localName != 'data')
				continue;
			$data = array();
			if ($reader->getAttribute('type') != 'primary_db')
				continue;
			$inside_data = true;
		} else {
			if($reader->localName != 'location')
				continue;
			$db_path = $reader->getAttribute('href');
			break;
		}
	}
	$db_path = dirname($file) . "/" . basename($db_path);
	if (!file_exists($db_path)) {
		fprintf(STDERR, "Error trying to format path to DB, file %s does not exist" . PHP_EOL, $file);
		$db_path = null;
	}
	return $db_path;
}

function _decompress_db(string $file)
{
	// file to string
	// maybe https://www.php.net/manual/ru/function.bzread.php -> example #1?
	$str = file_get_contents($file);
	// === or == ?
	if ($str == null || $str === false) {
		fprintf(STDERR, "Error trying to read file %s" . PHP_EOL, $file);
		return null;
	}
	$decompressed_str = bzdecompress($str);
	if (empty($decompressed_str)) {
		fprintf(STDERR, "Error decompressing a bzip-compressed string" . PHP_EOL);
		return null;
	}
	unset($str);
	return $decompressed_str;
}

function _sql_get_package_version(string $file, string $package)
{
	// https://ru.stackoverflow.com/a/1116276
	if (preg_match("/^[a-zA-Z0-9\-_]+$/", $package) != 1) {
		fprintf(STDERR, "Incorrect package name" . PHP_EOL);
		return null;
	}
	$sql = "SELECT version FROM packages WHERE name = '" . $package . "' ORDER BY version DESC;";
	$db = new SQLite3($file);
	$results = $db->query($sql);
	if ($results == false) {
		fprintf(STDERR, "Error running SQL query" . PHP_EOL);
		return null;
	}
	return $results->fetchArray()['version'];
}

function _get_package_version(string $repomd, string $package)
{
	/* Here memory usage is x2 of sqlite DB size because
	 * we first store an auncompressed DB in memory and then
	 * write in into a temporary file, ususually in tmpfs.
	 * It can be reduced later if needed by decompressing *.sqlite.bz2
	 * into a temp file directly. */
	$db_path = _get_db_path($repomd);
	if ($db_path == null) {
		fprintf(STDERR, "Error getting path to SQLite DB" . PHP_EOL);
		exit(1);
	}
	fprintf(STDERR, "Opening DB:" . PHP_EOL . $db_path . PHP_EOL);
	$str = _decompress_db($db_path);
	if ($str == null) {
		fprintf(STDERR, "Error decompressing" . PHP_EOL);
		exit(1);
	}
	// empty 1st arg results into using $TMPDIR or /tmp if $TMPDIR is not set
	$tmp = tempnam('', 'repodata_');
	if ($tmp == false) {
		fprintf(STDERR, "Error creating temporary file" . PHP_EOL);
		exit(1);
	}
	register_shutdown_function('unlink', $tmp);
	$rc = file_put_contents($tmp, $str);
	// XXX Is it a correct check?
	if ($rc <= 0 || $rc === false) {
		fprintf(STDERR, "Error dumping decompressed sqlite into file %s" . PHP_EOL, $tmp);
		exit(1);
	}
	unset($rc);
	unset($str);
	//echo "Wrote to TMP " . $tmp . PHP_EOL;
	$version = _sql_get_package_version($tmp, $package);
	if ($version == null) {
		fprintf(STDERR, "Error getting version of package %s from DB %s" . PHP_EOL, $package, $tmp);
		exit(1);
	}
	return $version;
}

if (isset($argv[1])) {
	$argv1 = $argv[1];
} else {
	$argv1 = "";
}
switch ($argv1) {
	case "get-package-version":
		//$repomd = "test-repo/repodata/repomd.xml";
		//$package = "dos2unix";
		$repomd = null;
		$package = null;
		if (isset($argv[2]))
			$repomd = $argv[2];
		if (isset($argv[3]))
			$package = $argv[3];
		if (($repomd == null) || ($package == null)) {
			_echo_help(1);
			exit(1);
		}
		$version = _get_package_version($repomd, $package);
		if ($version == null) {
			fprintf(STDERR, "Error getting package version %s" . PHP_EOL, $tmp);
			exit(1);
		}
		fprintf(STDOUT, $version . PHP_EOL);
		break;
	case 'help':
		_echo_help($argv[0], 0);
		exit(0);
		break;
	default:
		_echo_help($argv[0], 1);
		exit(1);
		break;
};
