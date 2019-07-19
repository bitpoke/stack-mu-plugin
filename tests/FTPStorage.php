<?php
namespace Stack\Tests\FTPStorage;

class FTPStorageTest extends \WP_UnitTestCase
{

    // Keep track how many times we did the setup, in order to create a new env for each use case
    private $run = 0;

    // URI of the FTP server. Default: ftp://localhost:2121
    private $ftpHost = null;

    const TESTS_NAMESPACE = 'test';

    /**
     *  Initial setup for the entire test run.
     *  It sets the FTP host used in tests, creates the initial test directory and set the prefix
     *  in order to separate each test run environment.
     */
    private function setupTestCase()
    {
        $ftpHost = getenv('UPLOADS_FTP_TEST_HOST', true) ?: 'localhost:2121';
        $this->ftpStorage = new \Stack\FTPStorage($ftpHost);

        // workaround for https://bugs.php.net/bug.php?id=77680
        $this->ftpStorage->setPrefix(self::TESTS_NAMESPACE);

        $buildNumber = getenv('DRONE_BUILD_NUMBER', true);
        $testPrefix = sprintf('%s/%s', self::TESTS_NAMESPACE, $buildNumber ? "ci-$buildNumber" : time());
        $this->ftpStorage->setPrefix($testPrefix);

        require_once(ABSPATH . WPINC . '/class-wp-image-editor.php');
        require_once(ABSPATH . WPINC . '/class-wp-image-editor-gd.php');
        require_once(ABSPATH . WPINC . '/class-wp-image-editor-imagick.php');
    }

    public function setUp()
    {
        parent::setUp();

        // setup initial environment for the entire test case
        if ($this->run == 0) {
            $this->setupTestCase();
        }

        $this->run++;

        // creates tests/:id/:run/wp-content/uploads
        $this->ftpStorage->appendPrefix($this->run);
    }

    public function imageEditorDataProvider()
    {
        return [
            // Waiting for https://core.trac.wordpress.org/ticket/42663
            // [ 'WP_Image_Editor_Imagick', 'imagick' ],
            [ 'WP_Image_Editor_GD' ]
        ];
    }

    /**
     * @slowThreshold 2000
     * @dataProvider imageEditorDataProvider
     */
    public function testImageUpload($editor, $requiredExtension = null)
    {
        if (!empty($requiredExtension) && !extension_loaded($requiredExtension)) {
            $this->markTestSkipped(
                "Extension '$requiredExtension' is not loaded"
            );
        }

        \add_filter('wp_image_editors', function ($editors) use ($editor) {
            return [$editor];
        });

        $filename = DIR_TESTDATA . '/images/canola.jpg';
        $contents = file_get_contents($filename);
        $upload = wp_upload_bits(basename($filename), null, $contents);

        $this->assertTrue(empty($upload['error']), print_r($upload, true));

        $id = $this->_make_attachment($upload);
        $path = wp_upload_dir()['path'];

        foreach (array(
            'canola.jpg',
            'canola-150x150.jpg',
            'canola-300x225.jpg'
        ) as $fileName) {
            $file = sprintf("%s/%s", $path, $fileName);

            $this->assertFileExists($file);
            wp_delete_file($file);
            $this->assertFileNotExists($file);
        }
    }

    /**
     *
     * Currentry only imagick supoports pdf thumbnails.
     * https://make.wordpress.org/core/2016/11/15/enhanced-pdf-support-4-7/
     *
     * @dataProvider imageEditorDataProvider
     * @slowThreshold 2000
     */
    public function testPDFUpload($editor, $requiredExtension = null)
    {
        if (!empty($requiredExtension) && !extension_loaded($requiredExtension)) {
            $this->markTestSkipped(
                "Extension '$requiredExtension' is not loaded"
            );
        }

        \add_filter('wp_image_editors', function ($editors) use ($editor) {
            return [$editor];
        });

        if (!$editor::supports_mime_type('application/pdf')) {
            $this->markTestSkipped(
                "Image editor '$editor' does not supports PDFs"
            );
        }

        $filename = DIR_TESTDATA . '/images/wordpress-gsoc-flyer.pdf';
        $contents = file_get_contents($filename);
        $upload = wp_upload_bits(basename($filename), null, $contents);

        $this->assertTrue(empty($upload['error']), print_r($upload, true));

        $id = $this->_make_attachment($upload);
        $path = wp_upload_dir()['path'];

        foreach (array(
            'wordpress-gsoc-flyer-pdf.jpg',
            'wordpress-gsoc-flyer-pdf-116x150.jpg',
            'wordpress-gsoc-flyer-pdf-232x300.jpg',
            'wordpress-gsoc-flyer-pdf-791x1024.jpg',
        ) as $fileName) {
            $file = sprintf("%s/%s", $path, $fileName);

            $this->assertFileExists($file);
            wp_delete_file($file);
            $this->assertFileNotExists($file);
        }
    }

    /**
     * FTP directories should be readable
     * TODO: test for writable once the following bug is fixed:
     *      * https://bugs.php.net/bug.php?id=77765
     */
    public function testDirIsReadable()
    {
        $path = wp_upload_dir()['path'];

        $this->assertDirectoryIsReadable($path);
    }
}
