# Yii Uploader

This is a yii extension/component to help generalize file management.  The point is to allow you to separate the way files are stored from the act of storing them. This makes it easy to move directories or even backing stores.

I have included an interface class, as well as two classes that implement the interface.  They are both components, so they can be defined in the Yii config, or they can be instantiated normally.  One is the FileUploader for dealing with local files, and the second is S3Uplaoder which stores files on S3.  They are fairly simple and do not implement some things like permissions which you may want to be more cautious about and may be implemented at a later date.

## Basic interface

The idea is that the interface supports three main operations, get, put, delete.  These have the following signatures:

    string get(string $key);
    void put(string $key, string $path);

The first will get a reference (URL) to a file, the second will store a new file in the file store, and the third will remove a file from the file store.  Each file is identified by a unique key, which is usually a path of some sort, but can be anything you'd want.  This lets the store be anything from a filesystem, to a database to S3.  There is no guarantee on the structure of the key.

There is a fourth function, getContents, which will get the contents of the file instead of just a URL.

## Instances

### S3Uploader

This system was originally written to encapsulate storing files on S3 and test with local files on development machines.  This is why the system mirrors the operations on S3.  ,e.  There are three properties that need to be set.

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

With these set, put will upload a file, get will return the public URL to the file, and delete will remove a file.  getContents will download the file.

You'll see that this class requires the amazon SDK.  I haven't found a good integreation with Yii, and I trust you to include it yourself.  I wrote a wrapper script that just includes the SDK files and put it in the SDK directory to work well with the Yii autoimporter.  You'll see the second line of the file is
    Yii::import('application.vendor.amazon.AmazonSDK', 'true');

### FileUploader

File uploader stores files on the local filesystem, and then publishes them as assets when needed. It's configuration is as follows.

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

## Examples

Descriptions of FileBehavior and ImageBehavior coming soon.

TODO
