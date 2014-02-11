<?php

/////////////////////////////////////////////
//
// Simple Route 53 Backup/Restore Script
// s.maraspin@mvassociati.it - 11/01/2014
//
/////////////////////////////////////////////

use Aws\Route53\Route53Client as R53;

try {

	include('include/cli_app_init.php');

	// Get latest backup from S3
	// if (null == $s_fileName) {
	//	$s_fileName = getLatestBackupFileName();
	// }

	// Shall backup be saved into a local file
	if (null != $s_localFile) {
		echo "Fetching R53 Backup Data for " . $s_domainName . " from " .
		     $s_localFile . "\n";
		$s_bkpData = file_get_contents($s_localFile);
	}

	// Shall backup be uploaded to S3?
	if (null != $s_backupBucket) {
		$I_s3 = new \r53S3Bkp($as_credentials);
		$s_bkpData = $I_s3->getBkpData($s_backupBucket, $s_locationWithinBucket);
	}

	$as_dnsRecords = json_decode($s_bkpData, true);
	$i_recordCount = count($as_dnsRecords);

	$i_iterations = ceil($i_recordCount / MAX_AWS_FETCH_RESULTS);
	$am_records = array();

	for ($x = 0; $x < $i_iterations; $x++) {

		$i_maxResults = min($i_recordCount, (($x+1) * MAX_AWS_FETCH_RESULTS));
		for ($i = ($x * MAX_AWS_FETCH_RESULTS); $i < $i_maxResults; $i++ ) {
			echo "Adding ".$as_dnsRecords[$i]["Name"]."\n";
			$am_changes[] = array('Action' => 'CREATE',
				                  'ResourceRecordSet' => $as_dnsRecords[$i]
			                );
		}

		$am_data = array('HostedZoneId' => $s_hostedZoneId,
				'ChangeBatch' => array(
						'Changes' => $am_changes
				)
		);

		$I_r53->changeResourceRecordSets($am_data);

	}

} catch (\Exception $I_e) {
	echo $I_e->getMessage()."\n";
}
