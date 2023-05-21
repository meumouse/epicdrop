<?php

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit; }

class Woo_EpicDrop_Init {

    public function __construct() {

        /* Include Files */
        require_once EPICDROP_DIR . '/includes/view/admin/configuration.php';
        require_once EPICDROP_DIR . '/includes/view/front/form.php';
        
        /* Include Classes */
        require_once EPICDROP_DIR . '/includes/classes/admin/class-epicdrop-hosts.php';
        require_once EPICDROP_DIR . '/includes/classes/admin/class-epicdrop-configuration.php';
        require_once EPICDROP_DIR . '/includes/classes/admin/class-epicdrop-configuration-save.php';
                          
        /* Register Hooks */
        register_activation_hook( __FILE__, array( $this, 'activation' ) );
        
        add_action( 'admin_menu', array( new EpicDrop_Configuration(), 'getConfigurationPage' ) );
        add_action( 'admin_post_epicdrop_importer_save_configuration', array( new EpicDrop_Configuration_Save(), 'saveConfiguration' ) );
    //    add_action( 'rest_api_init', 'epicdrop_register_route_api' );

        add_action( 'init', function () {
            if (isset( $_REQUEST['clear_log']) && '1' == $_REQUEST['clear_log']) {
                @unlink( EPICDROP_DIR . '/debug.log');
                wp_redirect(get_admin_url() . 'edit.php?post_type=product&page=epicdrop_settings&clear');
            }
        } );

        add_action( 'rest_api_init', function () {
            register_rest_route( 'v1', 'epicdrop', array(
                    'methods' => 'GET, POST', 
                    'callback' => array( $this, 'epicdrop_extension_connect' ),
                    'permission_callback' => '__return_true'
            ) );
        });
    }

    /**
     * Class responsible for all the front actions.
     * 
     * @return 
     * @since 1.0.0
     */
    public function epicdrop_extension_connect() {
        require_once( EPICDROP_DIR . '/includes/classes/front/Importer.php');
    }
    
    public function activation() {
        if (!get_option('importer_setting')) {
            add_option( 'importer_setting',
                array(
                    'secret_key' => md5(rand()),
                    'advance_option' => 1,
                    'affiliate_id' => '',
                    'replace_texts' => '',
                    'log' => 0
                )
            );
        }
    }
    
    /**
     * Create debug logs
     * 
     * @return string
     * @since 1.0.0
     */
    public static function addLog( $method, $message = null, $filePath = null, $line = null) {
        $log = gmdate('d/m/Y H:i:s') . "\t" . $method . "\t" . $message . "\t" . $filePath . "\t" . $line . PHP_EOL;
        $config = get_option('importer_setting');
        if ($config['log']) {
            error_log($log, 3, EPICDROP_DIR . 'debug.log');
        }
    }

    /**
     * Register routes for API connection
     * 
     * @return array
     * @since 1.0.0
     */
    /*
    public function epicdrop_register_route_api() {
        register_rest_route( 'v1', 'epicdrop', array(
            'methods' => 'GET, POST', 
            'callback' => array( $this, 'importer' ),
            'permission_callback' => '__return_true'
        ) );
    }*/
    
    /**
     * Get options for import in Chrome extension
     * 
     * @return array
     * @since 1.0.0
     */
    public static function getImportOptions() {
        return array(
            array(
                'name' => 'name',
                'label' => __('Nome', 'epicdrop'),
                'desc' => __('Obrigatório', 'epicdrop')
            ),
            array(
                'name' => 'sku',
                'label' => __('SKU', 'epicdrop'),
                'desc' => __('Obrigatório', 'epicdrop')
            ),
            array(
                'name' => 'upc',
                'label' => __('UPC', 'epicdrop'),
                'desc' => ''
            ),
            array(
                'name' => 'description',
                'label' => __('Descrição', 'epicdrop'),
                'desc' => ''
            ),
            array(
                'name' => 'price',
                'label' => __('Preço', 'epicdrop'),
                'desc' => __('Obrigatório', 'epicdrop')
            ),
            array(
                'name' => 'weight',
                'label' => __('Peso', 'epicdrop'),
                'desc' => ''
            ),
            array(
                'name' => 'image',
                'label' => __('Imagem', 'epicdrop'),
                'desc' => ''
            ),
            array(
                'name' => 'brand',
                'label' => __('Fabricante', 'epicdrop'),
                'desc' => __('Novo fabricante será criado se não existir.', 'epicdrop')
            ),
            array(
                'name' => 'category',
                'label' => __('Categoria', 'epicdrop'),
                'desc' => __('Nova categoria será criada se não existir.', 'epicdrop')
            ),
            array(
                'name' => 'variant',
                'label' => __('Variação', 'epicdrop'),
                'desc' => __('Novo atributo de variação será criado se não existir.', 'epicdrop')
            ),
            array(
                'name' => 'feature',
                'label' => __('Recurso', 'epicdrop'),
                'desc' => __('Novo atributo de recurso será criado se não existir.', 'epicdrop')
            ),
            array(
                'name' => 'review',
                'label' => __('Avaliações', 'epicdrop'),
                'desc' => ''
            ),
            /*array(
                'name' => 'meta_title',
                'label' => __('Meta title', 'epicdrop'),
                'desc' => ''
            ),
            array(
                'name' => 'meta_description',
                'label' => __('Meta description', 'epicdrop'),
                'desc' => ''
            ),
            array(
                'name' => 'meta_keyword',
                'label' => __('Meta keyword', 'epicdrop'),
                'desc' => ''
            )*/
        );
    }
    
    /**
     * Get categories for product WooCommerce
     * 
     * @return array
     * @since 1.0.0
     */
    public static function getCategories() {
        $catArgs = array(
            'orderby'    => 'name',
            'order'      => 'asc',
            'hide_empty' => false,
        );
        
        $categories = array();
        foreach (get_terms('product_cat', $catArgs) as $cat) {
            $categories[] = array(
                'term_id' => $cat->term_id,
                'name' => htmlspecialchars_decode($cat->name)
            );
        }
        return $categories;
    }
    
    /**
     * Get tax for product WooCommerce
     * 
     * @return array
     * @since 1.0.0
     */
    public static function getTaxClasses() {
        return array_map(
            function ( $tax) {
                return array(
                    'slug' => str_replace(' ', '-', strtolower($tax)),
                    'name' => $tax
                );
            },
            WC_Tax::get_tax_classes()
        );
    }
    
    /**
     * Functions for replacement texts
     * 
     * @return string
     * @since 1.0.0
     */
    public static function slugify( $text) {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // transliterate
        //$text = iconv('utf-8', 'us-ascii//TRANSLIT', utf8_encode($text));

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, '-');

        // remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return '';
        }
        return $text;
    }

}

new Woo_EpicDrop_Init();