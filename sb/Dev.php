<?php

namespace SB;

class Dev
{

    public static $remote_upload_url = 'https://i-masterclass.com/wp-content/uploads';

    public function __construct()
    {
        
        //add_filter('wp_get_attachment_image_src', [$this, 'get_thumbnail_filter'], 99, 4);
    }

    public function get_thumbnail_filter($image, $attachment_id, $size = 'thumbnail', $icon = false)
    {
        $this->may_get_thumbnail_remote($attachment_id, $size, $icon);
        return $image;
    }

    function may_get_thumbnail_remote($attachment_id, $size = 'thumbnail', $icon = false)
    {
        $intermediate = image_get_intermediate_size($attachment_id, $size);
        $upload_dir = wp_upload_dir();

        if (!$intermediate or !file_exists($upload_dir['basedir'] . '/' . $intermediate['path'])) {

            if (!($file = get_attached_file($attachment_id))){
                return false;
            }
                
            if (!is_file($file)) {
                $remote_url =
                     self::$remote_upload_url . str_replace($upload_dir['basedir'], '', $file);
                $remote_content = file_get_contents($remote_url);
                file_put_contents($file, $remote_content);
            }
        }
    }


}
