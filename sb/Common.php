<?php

namespace SB;

class Common
{


    public static function get_plugin_version()
    {
        $plugin_data = get_plugin_data(SB_PATH . '/autoload.php');
        // return $plugin_data['Version'];
        return $plugin_data['Version'];
    }

    public static function  object2array($object)
    {
        return json_decode(json_encode($object), 1);
    }

    public static function allowed_classification()
    {
        $classification = [
            ['slug' => 'sbn-skincare', 'name' => '護膚保養品'],
            ['slug' => 'sbn-make-up', 'name' => '彩妝品'],
            ['slug' => 'sbn-hair-care', 'name' => '頭髮護理'],
            ['slug' => 'sbn-ladies-fragrance', 'name' => '女士香水'],
            ['slug' => 'sbn-mens-fragrance', 'name' => '男士香水'],
            ['slug' => 'sbn-mens-skincare', 'name' => '男士護膚品'],
        ];

        return $classification;
    }

    public static function create_classification()
    {
        $cats = Common::allowed_classification();
        foreach ($cats as $cat) {

            $cat_exist = term_exists($cat['slug'], 'product_cat');
            if (!$cat_exist) {

                $result = wp_insert_term(
                    $cat['name'], // the term 
                    'product_cat', // the taxonomy
                    array(
                        'description' => $cat['name'],
                        'slug' => $cat['slug'],
                        'parent' => 0
                    )
                );                
            }
        }
    }

    public static function prodcatgname2slug($ProdCatgName)
    {

        $classification = 'sbn-' . html_entity_decode($ProdCatgName, ENT_QUOTES | ENT_HTML5);
        return sanitize_title($classification);
    }
}
