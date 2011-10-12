<?php
/**
 * Interface for uploading files.  Could be to a directory or a cloud account.
 */
interface IUploader {

    /**
     * Uploads a file to the specified location and names it appropriately.
     *
     * @param  string Key to store the file under.
     * @param  string Path to the file.
     * @param  string Mimetype to store under, if necessary.
     * @return void
     **/
    public function put($key, $filePath, $mimetype = null);

    /**
     * Get the url for an uploaded file based on key.
     *
     * @param  string Key the file was stored under.
     * @return string Url for the file.
     **/
    public function get($key);

    /**
     * Get the contents of a file based on the key.
     *
     * @param  string Key the file was stored under.
     * @return string The file contents as via file_get_contents()
     **/
    public function getContents($key);

    /**
     * Remove an item by key.
     *
     * @param  string Key the file was stored under.
     * @return void
     **/
    public function delete($key);
}

