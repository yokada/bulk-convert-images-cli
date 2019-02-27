<?php

$approot = dirname(dirname(__DIR__));
define( 'VENDOR_PATH', $approot.'/vendor');
define( 'WP_CLI', true);

require_once $approot . '/public/wp-config.php';

if ( ! defined( 'WP_CLI_ROOT' ) ) {
    define( 'WP_CLI_ROOT', VENDOR_PATH . '/wp-cli/wp-cli');
}
require_once WP_CLI_ROOT . '/php/utils.php';
WP_CLI\Utils\load_dependencies();
WP_CLI::set_logger(new WP_CLI\Loggers\Quiet);

require_once dirname(__DIR__).'/src/cli.php';
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    protected $ins;

    public function setUp() {
        $this->ins = new Bulk_Convert_Images();
    }

    public function testValidFileTypes() {
        $this->assertEquals(Bulk_Convert_Images_Logic::VALID_FILE_TYPES, ['jpg', 'jpeg', 'gif', 'png']);
    }

    public function testAllowedMimeTypes() {
        $this->assertEquals(Bulk_Convert_Images_Logic::ALLOWED_MIMETYPES, [
            'jpg' => 'image/jpg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ]);
    }

    /**
     * @dataProvider dataProviderGetExtension
     */
    public function testGetExtension($actual, $expected) {
        $this->assertEquals(Bulk_Convert_Images_Logic::get_extension($actual), $expected);
    }
    public function dataProviderGetExtension() {
        return [
            [ ['to' => 'jpg'], 'jpg'],
            [ ['to' => 'jpeg'], 'jpeg'],
            [ ['to' => 'png'], 'png'],
            [ ['to' => 'gif'], 'gif'],
            [ ['to' => 'webp'], false],
            [ ['to' => ''], false],
            [ ['to' => null], false],
            [ ['to' => 1], false],
            [ ['to' => false], false],
        ];
    }

    public function testGetPosts() {
        $posts = Bulk_Convert_Images_Logic::get_posts(1);
        $this->assertEquals(empty($posts), false);
    }

    /**
     * @dataProvider dataProviderAllowedFile
     */
    public function testAllowedFile($actualMimeType, $actualExt, $expected) {
        $this->assertEquals(Bulk_Convert_Images_Logic::allowed_file($actualMimeType, $actualExt), $expected);
    }
    public function dataProviderAllowedFile() {
        return [
            [ 'image/jpg', 'jpg', true ],
            [ 'image/jpeg', 'jpeg', true ],
            [ 'image/jpg', 'jpeg', false ],
            [ 'image/png', 'png', true ],
            [ 'image/png', 'gif', false ],
        ];
    }
}

