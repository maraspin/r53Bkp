<?php

/////////////////////////////////////////////
//
// Simple Route 53 Backup/Restore  Script
// s.maraspin@mvassociati.it - 28/01/2014
//
/////////////////////////////////////////////

use Aws\Route53\Route53Client as R53;
use Aws\S3\S3Client as S3;

require 'vendor/autoload.php';

// Upload/download to S3 is aborted after this number of attempts
define('EXPIRE_AFTER_ATTEMPTS', 10);

// R53 SDK setting
define('MAX_AWS_FETCH_RESULTS', 50);

// Where shall R53 backups be saved if no custom parameter is provided?
define('S3_DEFAULT_BUCKET_PATH', 'Route53Backup');

// HostedZone To Be Backed Up/Restored
$s_domainName = null;

// If S3 is used for the backup, we upload/download backup to/from this bucket/location
$s_backupBucket = null;
$s_locationWithinBucket = S3_DEFAULT_BUCKET_PATH;

// Shall script run output be verbose?
$b_debugMode = false;

// Shall existing files be overwritten?
$b_forceFileOverWrite = false;

// If backup is handled locally, we use this location on FS
$s_localFile = null;

$as_credentials = array();

$s_message = "Valid params for ".$argv[0]." are: --domain [--file |" .
             " --bucket --location=" . S3_DEFAULT_BUCKET_PATH .
             "] --overwrite\n";

$i_argv = count($argv);
for ($x = 0; $x < $i_argv; $x++) {

	$s_argvParam = $argv[$x];

	switch ($s_argvParam) {

		case '-o':
		case '--overwrite':
			$b_forceFileOverWrite = true;
			break;

		case '-f':
		case '--file':
			$s_localFile = $argv[$x+1];
			$x++;
			break;

		case '-b':
		case '--bucket':
			$s_backupBucket = $argv[$x+1];
			$x++;
			break;

		case '-l':
		case '--location':
			$s_locationWithinBucket = $argv[$x+1];
			$x++;
			break;

		case '-d':
		case '--domain':
			$s_domainName = $argv[$x+1];
			$x++;
			break;

		default:
			if ($x > 0) {
				echo $s_message;
				exit;
			}

	}
}

if ( null === $s_domainName ||
    (null == $s_localFile && null == $s_backupBucket)
   ) {
	echo $s_message;
	exit;
}

if (file_exists($s_localFile) &&
	!$b_forceFileOverWrite) {
	throw new \Exception("File " . $s_localFile . " exists. Use -o option to overwrite");
}

$s_confFile = __DIR__ . '/aws_conf.php';
if (!file_exists($s_confFile) ||
    !is_array(include($s_confFile))) {
	echo "AWS Credentials not found. Please make sure you rename file " .
	     $s_confFile . ".dist to " . $s_confFile . " and set your AWS credentials\n";
}

$as_credentials = include($s_confFile);
$I_r53 = R53::factory($as_credentials);

// Hosted Zone ID for selected domain is looked up
$am_hostedZones = $I_r53->listHostedZones();
$b_resultFound = false;
foreach ($am_hostedZones['HostedZones'] as $am_currentHostedZone) {
	if ($am_currentHostedZone['Name'] == $s_domainName.".") {
		$s_hostedZoneId = $am_currentHostedZone['Id'];
		$b_resultFound = true;
	}
}

if (!$b_resultFound) {
	throw new DomainException('Domain ' . $s_domainName . ' not found');
}