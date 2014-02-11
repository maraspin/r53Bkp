<?php

/////////////////////////////////////////////
//
// Simple Route 53 Backup Script
// s.maraspin@mvassociati.it - 28/01/2014
//
/////////////////////////////////////////////

use Aws\Route53\Route53Client as R53;
use Aws\S3\S3Client as S3;

require 'vendor/autoload.php';

// Upload/download to S3 is aborted after this number of attempts
define('EXPIRE_AFTER_ATTEMPTS', 10);

// HostedZone To Be Backed Up/Restored
$s_hostedZone = null;

// If S3 is used for the backup, we upload/download backup to/from this bucket/location
$s_backupBucket = null;
$s_locationWithinBucket = null;

// Shall script run output be verbose?
$b_debugMode = false;

// Shall existing files be overwritten?
$b_forceFileOverWrite = false;

// If backup is handled locally, we use this location on FS
$s_localFile = null;

$as_credentials = array();

$i_argv = count($argv);
for ($x = 0; $x < $i_argv; $x++) {

	$s_argvParam = $argv[$x];

	switch ($s_argvParam) {

		case '-f':
		case '--force':
			$b_forceFileOverWrite = true;
			break;

		case '-d':
		case '--debug':
			$b_debugMode = true;
			break;

		case '-o':
		case '--outputFile':
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

		case '-z':
		case '--zone':
			$s_hostedZone = $argv[$x+1];
			$x++;
			break;

		default:
			if ($x > 0) {
				echo "Valid params for ".$argv[0]." are: --zone [--outputfile | --bucket --location] --debug --force\n";
				exit;
			}

	}
}

if ( null === $s_hostedZone ||
    (null == $s_localFile && null == $s_backupBucket) ||
    (null != $s_localFile && null != $s_backupBucket)
   ) {
	echo "Usage: ".$argv[0]." -z hostedZone [-d -f][-o fileName| -b bucket [-l locationWithinBucket]]\n";
	exit;
}

if (file_exists($s_localFile) &&
	!$b_forceFileOverWrite) {
	throw new \Exception("File " . $s_localFile . " exists. Use -f option to overwrite");
}

if (null === $s_locationWithinBucket) {
	$s_locationWithinBucket = 'Route53Backup';
}

$s_confFile = __DIR__ . '/aws_conf.php';
if (!file_exists($s_confFile) ||
    !is_array(include($s_confFile))) {
	echo "AWS Credentials not found. Please make sure you create file " . $s_confFile .
	     ", containing the following:\n\n<?php\nreturn array('key' => 'YOUR_KEY', 'secret'=>'YOUR_SECRET');\n\n";
}

$as_credentials = include($s_confFile);

$I_s3 = S3::factory($as_credentials);
$I_r53 = R53::factory($as_credentials);

$am_hostedZones = $I_r53->listHostedZones();
$b_resultFound = false;
foreach ($am_hostedZones['HostedZones'] as $am_currentHostedZone) {
	if ($am_currentHostedZone['Name'] == $s_hostedZone.".") {
		$s_hostedZoneId = $am_currentHostedZone['Id'];
		$b_resultFound = true;
	}
}

if (!$b_resultFound) {
	throw new DomainException('Domain ' . $s_hostedZone . ' not found');
}


