<?php


namespace SB;
//use Symfony\Component\Dotenv\Dotenv;


class BootStrap {
    

        private static $instance;
                

        private function __construct()
        {   
            

            $this->define_constants();
            
            //register_activation_hook(SB_PATH.'/autoload.php',[$this,'activate_classification']);

            add_action('plugins_loaded', [$this, 'load_textdomain']); //load language
            $this->admin = new Admin();  
             
            $this->front = new Front();
            if( defined('SB_DEV') && SB_DEV ){
                $this->dev = new Dev();
            }
            // $dotenv = new Dotenv();
            // $dotenv->load(SB_PATH.'/.env');            
            // var_dump($_ENV['DB_USER']);            
        }

        

        private function define_constants() //后面没有 '/'
        {
            

        }

        public function load_textdomain()
        {
                load_plugin_textdomain('sb', false, SB_REL_PATH . '/languages/');
        }

        public static function instance()
        {
            if (!isset(self::$instance) && !(self::$instance instanceof BootStrap)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        

        
}