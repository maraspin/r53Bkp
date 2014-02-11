<?php

/////////////////////////////////////////////
//
// Simple Route 53 Backup Script
// s.maraspin@mvassociati.it - 28/01/2014
//
/////////////////////////////////////////////

use Aws\Route53\Route53Client as R53;
use Aws\S3\S3Client as S3;

try {

	include('include/cli_app_init.php');

	$am_hostedZone = $I_r53->getHostedZone(array('Id' => $s_hostedZoneId));
	$i_recordCount = $am_hostedZone['HostedZone']['ResourceRecordSetCount'];

	$s_nextRecordName = null;
	$s_nextRecordType = null;
	$i = 0;

	// Keeps track of records that have been included into backup
	$as_bkpDoneFor = array();

	// There's a 100 record limit on call below, so multiple calls might be needed
	while ($i < $i_recordCount) {

		echo "Fetching Records...\n";
		$am_records = $I_r53->listResourceRecordSets(array(
				                              'HostedZoneId' => $s_hostedZoneId,
                                              'StartRecordName' => $s_nextRecordName,
                                              'StartRecordType' => $s_nextRecordType,
                                              'MaxItems' => MAX_AWS_FETCH_RESULTS,
		                                   )
		                                );

		foreach($am_records['ResourceRecordSets'] as $am_record) {

			$s_key = $am_record['Type'].'-'.$am_record['Name'];

			if (!array_key_exists($s_key, $as_bkpDoneFor)) {

				$am_output[] = $am_record;
				echo "Fethcing Record " . ++$i . ": " . $am_record['Name'] .
				     " (" . $am_record['Type'] . ")\n";
				$s_nextRecordName = $am_record['Name'];
				$s_nextRecordType = $am_record['Type'];

				// Making sure no record is taken into account twice
				$as_bkpDoneFor[$s_key] = true;

			}
		}
	}

	$s_bkpData = json_encode($am_output);


	// Shall backup be saved into a local file
	if (null != $s_localFile) {
		echo "Saving R53 Backup Data for " . $s_domainName . " into " .
		     $s_localFile . "\n";
		file_put_contents($s_localFile, $s_bkpData);
	}


	// Shall backup be uploaded to S3?
	if (null != $s_backupBucket) {
		$I_s3 = new \r53S3Bkp($as_credentials);
		$I_s3->putBkpData($s_backupBucket, $s_locationWithinBucket, $s_bkpData);
	}


} catch (\Exception $I_e) {
	echo $I_e->getMessage()."\n";
}

