<?php

use Aws\S3\S3Client as S3;

class r53S3Bkp {

	/**
	 * Upload/download to/from S3 is aborted after this number of attempts
	 * @var int
	 */
	const EXPIRE_AFTER_ATTEMPTS = 10;


	/**
	 * S3 Client
	 *
	 * @var Aws\S3\S3Client
	 */
	protected $I_s3;


	/**
	 * AWS Credentials
	 * @param array
	 */
	public function __construct($as_credentials) {
		$this->I_s3 = S3::factory($as_credentials);
	}


	public function putBkpData($s_backupBucket, $s_locationWithinBucket, $s_data) {

		$s_date = date("Ymd-Hi-s");
		$s_S3fileName = $s_locationWithinBucket . '/' . $s_date . ".json";
		$this->attemptOperation('write', $s_backupBucket, $s_S3fileName, $s_data);

	}


	public function getBkpData($s_backupBucket, $s_locationWithinBucket) {
		$this->attemptOperation('read', $s_backupBucket, $s_locationWithinBucket);
	}


	protected function attemptOperation($s_operation = 'read',
			                            $s_bucket,
			                            $s_path,
			                            $s_data = '') {

		// What's the involved region?
		$s_bucketRegion = $this->I_s3->getBucketLocation(array(
				'Bucket' => $s_bucket,
		));
		$this->I_s3->setRegion($s_bucketRegion['Location']);

		$s_action = 'read' == $s_operation?'Fetching':'Saving';
		echo $s_action . " R53 Backup Data through S3 at " . $s_bucket .
			 '/' . $s_path . "\n";

		$i_actionStarted = time();
		$i_attempts = 0;
		$b_status = true;

		// UL/DL is attempted more than once (max EXPIRE_AFTER_ATTEMPTS times)
		do {

			echo "Attempt: " . ++$i_attempts."\n";
			$i_passedTime = time() - $i_actionStarted;

			try {

				if ('write' == $s_operation) {
					$this->I_s3->upload($s_bucket , $s_path, $s_data);
				} else {
					// $I_s3->upload($s_backupBucket , $s_S3destination, $s_bkpData);
				}

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


	private function getLatestBackupFileName($s_bucket, $s_location) {

		$as_items = $this->I_s3->listObjects();
		sort($as_items);
		$as_fileNameParts = explode("/",array_pop($as_items));

		return array_pop($as_fileNameParts);
	}

}

