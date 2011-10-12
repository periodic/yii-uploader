<?php

/**
 * File uploading behavior, now with dependency injection.
 *
 **/
class FileBehavior extends CActiveRecordBehavior
{
    /**
     * The configuration array for the uploader class.  Formatted similarly to a behavior configuration array.  It should implement IUploader.
     *
     * @var IUploader
     **/
    public $uploader;

    /**
     * Name of the field in the model to store the image's name under.
     *
     * @var string
     **/
    public $nameField;

    /**
     * The name to save the file with.
     *
     * @var string
     **/
    public $name;

    /**
     * The mime types we accept and their extension mappings.
     *
     * @var string
     **/
    public $mimeTypes = array(
        'image/jpeg' => 'jpg',
        'application/zip' => 'zip',
        'audio/mpeg' => 'mp3',
        'application/pdf' => 'pdf',
        'application/x-pdf' => 'pdf',
    );

    /**
     * Stores the file file path until a save is made. Set via the setter below.
     *
     * @var string
     **/
    private $file;

    /**
     * Holds the old filename for deletion!
     *
     * @var string
     **/
    private $_oldFile;

    /**
     * The uploader object.
     *
     * @var IUploader
     **/
    private $_uploader;

    /**
     * Sets the image file to be saved.  I
     *
     * @param  mixed The filename of the file on the filesystem or a CUploadedFile object.
     * @return void
     **/
    public function setFile($file) {
        if (is_object($file)) {
            $this->file = $file->tempName;
            $this->name = pathinfo($file->name, PATHINFO_BASENAME);
        } else {
            $this->file = $file;
            $this->name = pathinfo($file, PATHINFO_BASENAME);
            /*
            if (empty($this->name))  // If they didn't supply a name, we'll default it to the name field.
                $this->name = $this->nameField;
             */
        }
    }

    /**
     * Set the name the file will be saved with, sans extension.
     *
     * @return void
     **/
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * The attach function overrides the attach on in the parent to also set up the _uploader.
     *
     * @return void
     **/
    public function attach($component) {
        parent::attach($component);

        // Setup uploader.
        $this->_uploader = new $this->uploader['class'];
        foreach ($this->uploader as $key => $value) {
            if ($key == 'class') continue;

            $this->_uploader->$key = $value;
        }
    }

    /**
     * Before-save handler.  Just updates the name field if necessary.
     *
     * @return void
     **/
    public function beforeSave($event) {
        if (! empty($this->file)) {
            $mimeType = CFileHelper::getMimeType($this->file);
            if (isset($this->mimeTypes[$mimeType])) {

                if (isset($this->owner->{$this->nameField})) // Save old filename for deletion.
                    $this->_oldFile = $this->getKey();

                $this->owner->{$this->nameField} = $this->name;
            } else {
                $event->isValid = false;
                $this->owner->addError($this->nameField, 'Invalid file format.');
            }
        }
    }

    /**
     * After save, use the uploader to actually save the file.
     *
     * @return void
     **/
    public function afterSave($event) {
        // Skip this step if we aren't saving a new image.
        if (empty($this->file)) return;

        // Need to delete first incase they have the same name.
        if (isset($this->_oldFile))
            $this->_uploader->delete($this->_oldFile);

        $this->save($this->file);
    }

    /**
     * Save a file.
     *
     * @return void
     **/
    private function save($filePath) {
        $this->_uploader->put($this->getKey(), $this->file);
    }

    /**
     * Clears the file.
     *
     * @return void
     **/
    public function clear() {
        $this->_uploader->delete($this->getKey());
        $this->owner->{$this->nameField} = null;
        $this->file = null;
    }

    /**
     * Generate the key for the file, as stored in the model.
     *
     * @return string
     **/
    private function getKey() {
        return sprintf("%s/%d/%s/%s",
            get_class($this->owner),
            $this->owner->primaryKey,
            $this->nameField,
            isset($this->owner->{$this->nameField}) ? $this->owner->{$this->nameField} : $this->name);
    }

    /**
     * returns the URL for the file
     *
     * @return string
     **/
    public function getUrl() {
        if (isset($this->owner->{$this->nameField}))
            return $this->_uploader->get($this->getKey());
        else
            return null;
    }
} // END class ImageBehavior
