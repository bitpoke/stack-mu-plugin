<?php
namespace Stack\Tests;

class MediaStorageTest extends \WP_UnitTestCase
{
    private $mediaStoragePlugin = null;

    public function setUp()
    {
        require_once(ABSPATH . WPINC . '/class-wp-image-editor.php');
        require_once(ABSPATH . WPINC . '/class-wp-image-editor-gd.php');
        require_once(ABSPATH . WPINC . '/class-wp-image-editor-imagick.php');
    }

    public function imageEditorDataProvider()
    {
        return [
            // Waiting for https://core.trac.wordpress.org/ticket/42663
            // [ 'WP_Image_Editor_Imagick', 'imagick' ],
            [ 'WP_Image_Editor_GD' ]
        ];
    }

    public function testMediaFilesystemIsEnabled()
    {
        $this->assertContains("media", stream_get_wrappers());
        $path = wp_upload_dir()['path'];
        $this->assertStringStartsWith("media://", $path);
    }

    /**
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

    public function testDirIsWritable()
    {
        $path = wp_upload_dir()['path'];

        $this->assertDirectoryIsWritable($path);
    }
}
