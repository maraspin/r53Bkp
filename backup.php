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
				echo "Fethcing Record " . ++$i . ": " . $am_record['Name'] . " (" . $am_record['Type'] . ")\n";

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
		echo "Saving R53 Backup Data for " . $s_domainName . " into " . $s_localFile . "\n";
		file_put_contents($s_localFile, $s_bkpData);
	}

	// Shall backup be uploaded to S3?
	if (null != $s_backupBucket) {

		$i_date = date("Ymd-Hi-s");
		$s_S3fileName = $i_date . ".json";

		$I_s3 = S3::factory($as_credentials);

		// What's the involved region?
		$s_bucketRegion = $I_s3->getBucketLocation(array(
				'Bucket' => $s_backupBucket,
		));
		$I_s3->setRegion($s_bucketRegion['Location']);

		if (substr($s_locationWithinBucket,-1) != '/') {
			$s_locationWithinBucket .= '/';
		}

		echo "Saving R53 Backup Data for " . $s_domainName . " into S3 at " .
		     $s_backupBucket . '/' . $s_locationWithinBucket . $s_domainName . "\n";

		$i_actionStarted = time();
		$i_attempts = 0;
		$b_status = true;

		// Upload is attempted more than once (max EXPIRE_AFTER_ATTEMPTS times)
		do {

			echo "Attempt: " . ++$i_attempts."\n";
			$i_passedTime = time() - $i_actionStarted;

			try {

				$s_S3destination = $s_locationWithinBucket . $s_domainName . '/'. $s_S3fileName;
				$I_s3->upload($s_backupBucket , $s_S3destination, $s_bkpData);

			} catch (\Exception $I_e) {

				$b_status = false;
				if ($i_attempts < EXPIRE_AFTER_ATTEMPTS) {
					echo "A problem Occurred: " . $I_e->getMessage() . "\n Trying to recover...\n";
				}

			}

			sleep($i_attempts);

		} while (!$b_status && $i_attempts < EXPIRE_AFTER_ATTEMPTS);

		if (!$b_status) {
			throw new \Exception('Something went wrong and upload could not be made after ' . $i_attempts . ' attempts');
		}

	}

} catch (\Exception $I_e) {
	echo $I_e->getMessage()."\n";
}

