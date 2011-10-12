<?php
Yii::import('application.vendor.amazon.AmazonSDK', 'true');

/**
 * Uploads a file to S3.
 **/
class S3Uploader extends CComponent implements IUploader
{
    /**
     * Bucket to save files in.
     *
     * @var string
     **/
    public $bucket;

    /**
     * S3 API key
     *
     * @var string
     **/
    public $apiKey;

    /**
     * S3 Secret key
     *
     * @var string
     **/
    public $secret;

    /**
     * Initialize the component.  Overrides CComponent.
     *
     * @return void
     **/
    public function init() { }

    /**
     * Save a file to S3.
     *
     * @return boolean        Success/failure
     **/

    public function put($key, $filepath, $mimetype = null) {
        $s3 = $this->connectToS3();

        $key = self::safeKey($key);

        if ($mimetype === null)
            $mimetype = CFileHelper::getMimeType($filepath);

        // TODO: batch these two operations.  However, sometimes it seems like the 
        // batching doesn't work even with a send().
        $s3->create_object($this->bucket, $key, array(
            'fileUpload' => $filepath,
            'contentType' => $mimetype,
        ));

        // Make it public
        $s3->set_object_acl($this->bucket, $key, AmazonS3::ACL_PUBLIC);
    }

    /**
     * Get the URL for an S3 resource.
     *
     * @return string The URL.
     **/
    public function get($key) {
        $s3 = $this->connectToS3();

        $key = self::safeKey($key);

        return $s3->get_object_url($this->bucket, $key);
    }

    /**
     * Get the contents of an S3 resource.
     *
     * @return void
     **/
    public function getContents($key) {
        $s3 = $this->connectToS3();

        $key = self::safeKey($key);

        if ($s3->if_object_exists($this->bucket, $key))
            return file_get_contents($this->get($key));
        else
            return false;
    }

    /**
     * Delete a file from S3.
     *
     * @return boolean Success/failure
     **/
    public function delete($key) {
        $s3 = $this->connectToS3();

        $key = self::safeKey($key);

        if ($s3->if_bucket_exists($this->bucket)
            && $s3->if_object_exists($this->bucket, $key)
        )  {
            return $s3->delete_object($this->bucket, $key)->isOK();
        }

        return true;
    }


    /**
     * Clean a filename to remove special characters.  AWS does not support some 
     * special characters in the filenames.  This is done by being a bit tricky and 
     * using urlencode followed by a preg_replace to remove the special characters.
     *
     * @return void
     **/
    public static function safeKey($str) {
        return preg_replace('/(%[0-9A-Fa-f]{2}|\+)/', '', preg_replace('/%2[fF]/', '/', urlencode($str)));
    }

    /* Holds the s3 object. */
    private $s3;
    /**
     * Makes sure we're connect to S3 and our parameters are set from the 
     * application parameters if they weren't passed in.
     *
     * @return AmazonS3 The S3 object.
     **/
    private function connectToS3() {
        if (! isset($this->s3)) {
            if (! isset($this->bucket))    $this->bucket    = Yii::app()->params['S3Bucket'];
            if (! isset($this->apiKey))    $this->apiKey    = Yii::app()->params['S3ApiKey'];
            if (! isset($this->secret))    $this->secret    = Yii::app()->params['S3SecretKey'];
            $this->s3 = new AmazonS3($this->apiKey, $this->secret);
        }
        return $this->s3;
    }

} // END class S3Uploader
