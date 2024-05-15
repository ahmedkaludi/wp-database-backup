<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\MultipartUploader;
use Aws\S3\Exception\MultipartUploadException;

function wpdbbkp_backblazeb2_s3_sendfile($bucketHost,$bucketName,$region,$keyId,$applicationKey,$filePath){
$keyName = basename($filePath);

try {
    // Instantiate the S3 client with Backblaze B2 S3 compatible endpoint
    $s3Client = new S3Client([
        'version' => 'latest',
        'region'  => $region,
        'endpoint' => $bucketHost, // Adjust based on your B2 endpoint
        'credentials' => [
            'key'    => $keyId,
            'secret' => $applicationKey,
        ]
    ]);

    // Prepare the upload
    $uploader = new MultipartUploader($s3Client, $filePath, [
        'bucket' => $bucketName,
        'key'    => $keyName,
    ]);

    // Perform the upload
    try {
        $result = $uploader->upload();
        return "<br> Upload Database Backup on s3 bucket " . $result['ObjectURL'] . "\n";
    } catch (MultipartUploadException $e) {
        $uploader->abort();
        error_log('b');
        return "Error during upload: " . $e->getMessage() . "\n";
    }

} catch (AwsException $e) {
    return  "Error: " . $e->getMessage() . "\n";
}

}




