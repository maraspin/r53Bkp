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
	$i_maxItems = min($i_recordCount, 100);

	$s_nextRecordName = null;
	$s_nextRecordType = null;
	$i = 0;

	$as_bkpDoneFor = array();

	while ($i < $i_recordCount) {

		echo "Fetching Records...\n";
		$am_records = $I_r53->listResourceRecordSets(array(
				                                          'HostedZoneId' => $s_hostedZoneId,
														  'StartRecordName' => $s_nextRecordName,
														  'StartRecordType' => $s_nextRecordType,
														  'MaxItems' => 100,
		                                                 )
		                                );

		foreach($am_records['ResourceRecordSets'] as $am_record) {

			$s_key = $am_record['Type'].'-'.$am_record['Name'];
			if (!array_key_exists($s_key, $as_bkpDoneFor)) {

				$am_output[] = $am_record;
				echo "Fethcing Record " . ++$i . ": " . $am_record['Name'] . " (" . $am_record['Type'] . ")\n";

				$s_nextRecordName = $am_record['Name'];
				$s_nextRecordType = $am_record['Type'];

				$as_bkpDoneFor[$s_key] = false;

			}

		}

	}

	$s_bkpData = json_encode($am_output);
	file_put_contents($s_localFile, $s_bkpData);

} catch (\Exception $I_e) {
	echo $I_e->getMessage()."\n";
}

