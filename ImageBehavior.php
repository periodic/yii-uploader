<?php

/**
 * Image uploading behavior, now with dependency injection.
 *
 * -- TODO: Check file type/extension against whitelist.
 *
 **/
class ImageBehavior extends CActiveRecordBehavior
{
    /**
     * The configuration array for the uploader class.  Formatted similarly to a behavior configuration array.  It should implement IUploader.
     *
     * @var IUploader
     **/
    public $uploader;

    /**
     * Sizes to save.
     *
     * These should be the various sizes to keep saved, this is an array in the following form:
     * <pre>
     * array(
     *   name => array(
     *     'width' => number,
     *     'height' => number,
     *   ),
     *   name => array(
     *     'width' => number,
     *     'height' => number,
     *     'thumbnail' => url,
     *     'resizeType' => ['fixed-width' | 'adaptive' | 'crop'],
     *     'cropOffsetX' => number,
     *     'cropOffsetY' => number,
     *     'cropWidth'  => number,
     *     'cropHeight' => number,
     *   ),
     *   ...
     * )
     *
     * @var array
     **/
    public $sizes = array();

    /**
     * Name of the field in the model to store the image's name under.
     *
     * @var string
     **/
    public $nameField;

    /**
     * A disambituation prefix to use in the case that there are multiple images per model.
     *
     * @var string
     **/
    public $namePrefix;

    /**
     * Stores the image file path until a save is made. Set via the setter below.
     *
     * @var string
     **/
    private $image;

    /**
     * The uploader object.
     *
     * @var IUploader
     **/
    private $_uploader;

    /**
     * Magic getter.  Used to get the image urls by size easily.
     *
     * @return void
     **/
    public function __get($name) {
        if (preg_match('/(\w+)Url/', $name, $matches) > 0) {
            return $this->getUrl($matches[1]);
        }

        return parent::__get($name);
    }

    /**
     * Sets the image file to be saved
     *
     * @return void
     **/
    public function setImage($file) {
        if (is_object($file))
            $this->image = $file->tempName;
        else
            $this->image = $file;
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

        // By default we do an adaptive resize.
        if (empty($sizeParams['resizeType']))
            $sizeParams['resizeType'] = 'adaptive';
    }

    /**
     * The mime types we accept and their extension mappings.
     *
     * @var string
     **/
    private static $mimeTypes = array(
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/png' => 'png'
    );

    /**
     * Before-save handler.  Just updates the name field if necessary.
     *
     * @return void
     **/
    public function beforeSave($event) {
        if (! empty($this->image)) {
            $mimeType = CFileHelper::getMimeType($this->image);
            if (isset(self::$mimeTypes[$mimeType]))
                $this->owner->{$this->nameField} = self::$mimeTypes[$mimeType];
            else {
                $event->isValid = false;
                $this->owner->addError($this->nameField, 'Invalid image format.');
            }
        }
    }

    /**
     * After save, use the uploader to actually save the image.
     *
     * @return void
     **/
    public function afterSave($event) {
        // Skip this step if we aren't saving a new image.
        if (empty($this->image)) return;

        foreach ($this->sizes as $size => $sizeParams) {
            $this->save($this->image, $size, $sizeParams);
        }
    }

    /**
     * Resize an image, using $fromimage as the source, saving into $toimage, with the options $opts.
     *
     * @return void
     **/
    public function recrop($fromSize, $toSize, $opts) {
        $imageH = fopen($this->getUrl($fromSize), 'r');

        $tmpFile = tempnam(sys_get_temp_dir(), '');
        $tmpH = fopen($tmpFile, 'w+');

        fwrite($tmpH, stream_get_contents($imageH));
        fclose($tmpH);

        $opts['width'] = $this->sizes[$toSize]['width'];
        $opts['height'] = $this->sizes[$toSize]['height'];
        $opts['resizeType'] = 'crop';

        $this->save($tmpFile, $toSize, $opts);

        unlink($tmpFile);
    }

    /**
     * Save an image.
     *
     * @return void
     **/
    private function save($imagePath, $size, $sizeParams) {
            /* If the width is specified, assume width and height are specified 
                and resize to that size via adaptiveResize.
             */
            if (isset($sizeParams['width'])) {
                $tmpfile = tempnam(sys_get_temp_dir(), '');

                $thumbFactory = PhpThumbFactory::create($imagePath);
                $thumbFactory->setOptions(array('resizeUp' => true )); // Force resizing up if image is too small.

                if (empty($sizeParams['resizeType']))
                    $sizeParams['resizeType'] = 'fixed-width';


                if ($sizeParams['resizeType'] == 'fixed-width') {
                    $thumbFactory
                        ->resize($sizeParams['width'])
                        ->save($tmpfile);
                } elseif ($sizeParams['resizeType'] == 'crop') {
                    $thumbFactory
                        ->crop($sizeParams['cropOffsetX'], $sizeParams['cropOffsetY'], $sizeParams['cropWidth'], $sizeParams['cropHeight'])
                        ->adaptiveResize($sizeParams['width'], $sizeParams['height'])
                        ->save($tmpfile);
                } elseif ($sizeParams['resizeType'] == 'adaptive') {
                    $thumbFactory
                        ->adaptiveResize($sizeParams['width'], $sizeParams['height'])
                        ->save($tmpfile);
                } else {
                    throw new Exception("Unsupported resize type.");
                }

                $this->_uploader->put($this->imageKey($size), $tmpfile);

                unlink($tmpfile);
            } else {
                /* With no width we just upload the original size. */
                $this->_uploader->put($this->imageKey($size), $this->image);
            }
    }

    /**
     * Clears the image.
     *
     * @return void
     **/
    public function clear() {
        foreach ($this->sizes as $size => $sizeParams) {
            $this->_uploader->delete($this->imageKey($size));
        }
        $this->owner->{$this->nameField} = null;
        $this->image = null;
    }

    /**
     * Generate the key for the image, as stored in the model.
     *
     * @return string
     **/
    private function imageKey($size) {
        return sprintf("%s/%d/%s%s.%s", get_class($this->owner), $this->owner->primaryKey, $this->namePrefix, $size, $this->owner->{$this->nameField});
    }

    /**
     * returns the URL for a size.
     *
     * @return string
     **/
    public function getUrl($size) {
        if ($this->hasImage())
            return $this->_uploader->get($this->imageKey($size));
        else
            if (isset($this->sizes[$size]['placeholder']))
                return $this->sizes[$size]['placeholder'];
            else
                return $this->_uploader->get($this->imageKey($size));
    }

    /**
     * undocumented function
     *
     * @return void
     **/
    public function hasImage() {
        return isset($this->owner->{$this->nameField});
    }

    /**
     * Make a placehold.it URL.
     *
     * @param  integer width
     * @param  integer height
     * @return void
     **/
    public static function placeholditUrl($width, $height) {
        return "http://placehold.it/{$width}x{$height}";
    }

} // END class ImageBehavior
