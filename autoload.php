<?php
 /**
 * Plugin Name: lms helper
 * Plugin URI: https://github.com/falconchen/wp-skeleton-plugin.git 
 * Description: lms plugin for mpw
 * Version: 0.1
 * Author: Falcon
 * Author URI: https://www.cellmean.com
 * Text Domain: lms 
 * Domain Path: languages/
 * GitHub Branch: master
 * License: GPL2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

/** Load composer */
$composer = dirname(__FILE__) . '/vendor/autoload.php';
if ( file_exists($composer) ) {
    require_once $composer;
}
