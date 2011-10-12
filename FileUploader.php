<?php

/**
 * Uploads a file using a directory to store it.
 **/
class FileUploader extends CComponent implements IUploader
{
    /**
     * The base directory to use.  This holds the files.  They will be published to the assets folder if the URL is requested.
     *
     * @var string
     **/
    public $directory;

    /**
     * Permissions for newly created directories.
     *
     * @var integer
     **/
    public $dirMode = 0775;

    /**
     * Subdirectory that is publicly accessible.  Only things under this subdirectory will be accessible by URL.
     *
     * @var string
     **/
    public $publicSubdir = '';

    /**
     * Url of published asset folder.
     *
     * @var string
     **/
    private $assetUrl;

    /**
     * Initialize the component.  Overrides CComponent.
     *
     * @return void
     **/
    public function init() { }

    /**
     * Save a file.
     *
     * @return boolean        Success/failure
     **/
    public function put($key, $filepath, $mimetype = null) {
        $path = $this->makePath($key);

        // Create directory if it doesn't exist.
        $dir = $dirname($path);
        if (! is_dir($dir))
            mkdir($dir, $this->dirMode, true);

        return copy($filepath, $path);
    }

    /**
     * Get the URL for a resource.
     *
     * @return string The URL.
     **/
    public function get($key) {
        $this->assetUrl = Yii::app()->assetManager->publish($this->directory . DIRECTORY_SEPARATOR . $this->publicSubdir); 

        return $this->assetUrl . '/' . str_replace($this->publicSubdir, '', $key);
    }

    /**
     * Get the contents of a resource.
     *
     * @return string
     **/
    public function getContents($key) {
        $path = $this->makePath($key);
        if (file_exists($path))
            return file_get_contents($path);
        else
            return false;
    }

    /**
     * Delete a file.
     *
     * @return boolean Success/failure
     **/
    public function delete($key) {
        return unlink($this->makePath($key));
    }

    /**
     * Make sure the key is safe.
     *
     * @return string
     **/
    private function safeKey($key) {
        // Remove references to up-directories to avoid escaping our directory.
        return preg_replace('/\.\.\//', '', $key);
    }

    /**
     * Make a path from a key.
     *
     * @return string
     **/
    private function makePath($key) {
        return $this->directory . DIRECTORY_SEPARATOR . $this->safeKey($key);
    }


} // END class S3Uploader
