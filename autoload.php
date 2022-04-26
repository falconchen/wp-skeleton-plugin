<?php
 /**
 * Plugin Name: Sb Starberry
 * Plugin URI: https://github.com/falconchen/wp-skeleton-plugin.git 
 * Description: starberry for sb
 * Version: 0.1
 * Author: Falcon
 * Author URI: https://www.google.com
 * Text Domain: lms 
 * Domain Path: languages/
 * GitHub Branch: master
 * License: private
 */

/** Load composer */
$composer = dirname(__FILE__) . '/vendor/autoload.php';
if ( file_exists($composer) ) {
    
    require_once $composer;
    
    define('SB_PATH', rtrim(plugin_dir_path(__FILE__),DIRECTORY_SEPARATOR));//当前插件目录路径
    define('SB_REL_PATH', dirname(plugin_basename(__FILE__))); //相对路径            
    define('SB_URL', plugins_url('', __FILE__));//插件url
    SB\BootStrap::instance();
}

if( defined('SB_DEV') && SB_DEV ){
    if(!function_exists('opstore_product_thumb_wrapp')){
        function opstore_product_thumb_wrapp(){
            $gallery = get_post_meta(get_the_ID(), '_product_image_gallery', true);
            if($gallery == ''){
                $class = 'no-flip';
            }else{
                $class = 'flip';
            }
            
            $size = 'shop_catalog';
            echo '<div class="opstore-thumb-wrapp '.esc_attr($class).'">';
            echo '<div class="opstore-img-before">';
            echo woocommerce_template_loop_product_thumbnail($size);
            echo '</div>';
            //opstore_loop_product_thumbnail_hover(); //移除用于本地开发
            echo '</div>';
        }
    }
}


