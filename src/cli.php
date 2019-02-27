<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}


class Bulk_Convert_Images_Logic {

    const VALID_FILE_TYPES = ['jpg', 'jpeg', 'gif', 'png'];
    const IMAGECREATE_FUNC = [
        'jpg' => 'imagecreatefromjpeg',
        'jpeg' => 'imagecreatefromjpeg',
        'png' => 'imagecreatefrompng',
        'gif' => 'imagecreatefromgif',
    ];
    const IMAGECONVERT_FUNC = [
        'jpg' => 'imagejpeg',
        'jpeg' => 'imagejpeg',
        'png' => 'imagepng',
        'gif' => 'imagegif',
    ];
    const ALLOWED_MIMETYPES = [
        'jpg' => 'image/jpg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
    ];

    public static function get_posts($paged = 1, $postsPerPage = 100) {
        return get_posts([
            'post_type' => 'attachment',
            'posts_per_page' => $postsPerPage,
            'paged' => $paged,
            'order_by' => 'ID',
            'order' => 'ASC',
            'post_mime_type' => implode(',',array_values(self::ALLOWED_MIMETYPES)),
        ]);
    }

    public static function get_extension($assoc_args) {
        if (!isset($assoc_args['to']) || empty($assoc_args['to'])) {
            return false;
        } else {
            $newExt = $assoc_args['to'];
            if (!in_array($newExt, self::VALID_FILE_TYPES)) {
                return false;
            }
        }
        return $newExt;
    }

    public static function allowed_file($mimeType, $ext) {
        return isset(self::ALLOWED_MIMETYPES[$ext]) && self::ALLOWED_MIMETYPES[$ext] == $mimeType && in_array($ext, self::VALID_FILE_TYPES);
    }

    public static function valid_guid($uploadDir, $guid) {
        $validExts = '(?:'.implode('|', self::VALID_FILE_TYPES).')';
        $regex = '#\A' . $uploadDir['baseurl'] . '/.*\.'.$validExts.'\z#';
        return (bool) preg_match($regex, $guid);
    }

    public static function valid_save_path($uploadDir, $willSavePath, $origPath, $newExt, $origExt) {
        if (mb_strpos($willSavePath, $uploadDir['basedir']) !== 0) {
            return false;
        } elseif (realpath($origPath) === false ) {
            return false;
        } else {
            $newPath = dirname($willSavePath) . '/' . basename($willSavePath, '.' . $newExt);
            $oldPath = dirname($origPath) . '/' . basename($origPath, '.' . $origExt);
            if ($newPath !== $oldPath) {
                return false;
            }
        }
        return true;
    }

    public static function update_attachment($uploadDir, $ID, $guid, $newMimeType) {
        global $wpdb;
        $sql = "UPDATE $wpdb->posts SET guid = '%s', post_mime_type = '%s' WHERE ID = %d;";
        $wpdb->query( $wpdb->prepare( $sql, $guid, $newMimeType, $ID ) );

        // update meta
        //$meta = wp_get_attachment_metadata($ID);
        $filepath = str_replace($uploadDir['baseurl'] . '/', '', $guid);
        update_attached_file($ID, $filepath);
    }
}


/**
 * メディア画像を指定形式に変換します
 * @todo WP-CLI のシェル引数のバリデーションがどうなっているか確認 escapeshellarg とか使っているのかどうか.
 */
class Bulk_Convert_Images extends WP_CLI_Command {

    protected $uploadDir;

    public function __construct()
    {
        $this->uploadDir = wp_upload_dir();
        if ($this->uploadDir['error'] !== false ) {
            WP_CLI::error(sprintf('wp_upload_dir failed, error: %s', $this->uploadDir['error']), true);
        }
    }

    /**
    *
    *
    * ## OPTIONS
    *
    * [--images=<images>]
    * : A folder path contains image files.
    *
    * ## EXAMPLES
    *
    *    $ vendor/bin/wp bulk-convert-images register-testdata --images='test/helper/images/*.png'
    *
    * @subcommand register-testdata
    */
    public function register_testdata( $args, $assoc_args )
    {
        WP_CLI::runcommand( 'media import ' . $assoc_args['images'] );
    }

    /**
     * ## OPTIONS
     *
     * [--to=<filetype>]
     * : A folder path contains image files.
     * @subcommand convert
     */
    public function convert($args, $assoc_args) {
        $newExt = Bulk_Convert_Images_Logic::get_extension($assoc_args);
        if (empty($newExt)) {
            WP_CLI::error('--to parameter should be in ' . implode(', ', Bulk_Convert_Images_Logic::VALID_FILE_TYPES), true);
        }

        $paged = 1;

        while (true) {
            $posts = Bulk_Convert_Images_Logic::get_posts($paged);
            if (empty($posts)) {
                break;
            }

            foreach ($posts as $post) {
                // create base image
                $imagePath = get_attached_file($post->ID);
                $imageFileInfo = new SplFileInfo($imagePath);
                $origExt = $imageFileInfo->getExtension();
                $imageUrl = wp_get_attachment_url($post->ID);

                // 拡張子とmimeTypeによる検証
                if (!Bulk_Convert_Images_Logic::allowed_file($post->post_mime_type, $origExt)) {
                    WP_CLI::warning(sprintf('Not allowed file specified, postId: %d, postMimeType: %s, origExt: %s', $post->ID, $post->post_mime_type, $origExt));
                    continue;
                }

                // 画像URL検証
                $guid = mb_substr($imageUrl, 0, mb_strrpos($imageUrl, '/')) . '/' . basename($imagePath, '.' . $origExt) . '.' . $newExt;
                if (!Bulk_Convert_Images_Logic::valid_guid($this->uploadDir, $guid)) {
                    WP_CLI::warning(sprintf('guid is invalid, postId: %d, guid: %s, imagePath: %s', $post->ID, $guid, $imagePath));
                    continue;
                }

                // 元画像のロード
                $imageRes = Bulk_Convert_Images_Logic::IMAGECREATE_FUNC[$origExt]($imagePath);
                if (empty($imageRes)) {
                    WP_CLI::warning(sprintf('Image Creation failed, postId: %d, imagePath: %s, origExt: %s', $post->ID, $imagePath, $origExt));
                    continue;
                }

                // 画像保存パス
                $willSavePath = dirname($imagePath) . '/' . basename($imagePath, '.' . $origExt) . '.' . $newExt;
                if (!Bulk_Convert_Images_Logic::valid_save_path($this->uploadDir, $willSavePath, $imagePath, $newExt, $origExt)) {
                    WP_CLI::warning(sprintf('Invalid savepath detected, postId: %d, willSavePath: %s', $post->ID, $willSavePath));
                    imagedestroy($imageRes);
                    continue;
                }

                // 画像変換
                $imageConvertResult = Bulk_Convert_Images_Logic::IMAGECONVERT_FUNC[$newExt]($imageRes, $willSavePath);
                if (empty($imageConvertResult)) {
                    WP_CLI::warning(sprintf('Image Conversion failed, postId: %d, willSavePath: %s', $post->ID, $willSavePath));
                    imagedestroy($imageRes);
                    continue;
                }

                imagedestroy($imageRes);

                Bulk_Convert_Images_Logic::update_attachment($this->uploadDir, $post->ID, $guid, Bulk_Convert_Images_Logic::ALLOWED_MIMETYPES[$newExt]);
            }
            $paged++;
        }

        // create thumbnails
        WP_CLI::runcommand( 'media regenerate --yes' );

        WP_CLI::success('convert succeeded.');
    }
}

WP_CLI::add_command( 'bulk-convert-images', 'Bulk_Convert_Images' );

