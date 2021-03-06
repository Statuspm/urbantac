<?php

/**
 * Localisation
 * */
load_plugin_textdomain('wc_catalog_restrictions', false, dirname(plugin_basename(__FILE__)) . '/');

/**
 * woocommerce_product_addons class
 * */
if (!class_exists('WC_Catalog_Restrictions')) {

    class WC_Catalog_Restrictions {

        public $template_url;
        public $version = '1.0.0';

        public function __construct() {

            if (!session_id()) {
                session_start();
            }

            define('WOOCOMMERCE_CATALOG_RESTRICTIONS_VERSION', $this->version);

            $this->template_url = apply_filters('woocommerce_catalog_restrictions_template_url', 'woocommerce-catalog-visibility-options/');

            require 'woocommerce-catalog-restrictions-functions.php';
            require 'shortcodes/class-wc-catalog-restrictions-location-picker-shortcode.php';
            require 'widgets/class-wc-catalog-restrictions-location-picker-widget.php';

            WC_Catalog_Restrictions_Location_Picker_ShortCode::instance();

            WC_Catalog_Restrictions_Location_Picker_Widget::register();

            if (is_admin()) {

                require 'classes/class-wc-catalog-restrictions-product-admin.php';
                require 'classes/class-wc-catalog-restrictions-category-admin.php';


                WC_Catalog_Restrictions_Product_Admin::instance();
                WC_Catalog_Restrictions_Category_Admin::instance();
            } else {

                require 'classes/class-wc-catalog-restrictions-query.php';
                require 'classes/class-wc-catalog-restrictions-filters.php';

                WC_Catalog_Restrictions_Query::instance();
                WC_Catalog_Restrictions_Filters::instance();
                //load after woocommerce

                if ($this->get_setting('_wc_restrictions_locations_enabled', 'yes') == 'yes') {
                    add_filter('template_redirect', array(&$this, 'template_redirect'), 999);
                }
            }

            if (is_admin() && !defined('DOING_AJAX')) {
                require 'woocommerce-catalog-restrictions-installer.php';
                $this->install();
            }

            //Setup Hooks to clear transients when a post is saved, a category is saved, a user changes their location, a user is updated, a user logs on / out. 
            add_action('save_post', array(&$this, 'clear_transients'));
            add_action('created_term', array(&$this, 'clear_transients'));
            add_action('edit_term', array(&$this, 'clear_transients'));
            add_action('edit_user_profile_update', array(&$this, 'clear_transients'));

            add_action('wp_login', array(&$this, 'clear_session_transients'));
            add_action('wp_logout', array(&$this, 'clear_session_transients'));
            add_action('wc_restrictions_location_updated', array(&$this, 'clear_session_transients'));
        }

        public function install() {
            register_activation_hook(__FILE__, 'activate_woocommerce_catalog_restrictions');
            register_deactivation_hook(__FILE__, 'deactivate_woocommerce_catalog_restrictions');
            if (get_option('woocommerce_catalog_restrictions_db_version') != $this->version) {
                add_action('init', 'install_woocommerce_catalog_restrictions', 1);
            }
        }

        public function clear_transients() {
            global $wpdb;
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_twccr%'");
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_twccr%'");

            wp_cache_flush();
        }

        public function clear_session_transients() {
            global $wpdb;
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_twccr_" . session_id() . "%'");
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_twccr_" . session_id() . "%'");

            wp_cache_flush();
        }

        /*
         * Template and Location Picker Function 
         */

        public function get_location_for_current_user() {
            $location = isset($_SESSION['wc_location']) ? $_SESSION['wc_location'] : '';
            return apply_filters('woocommerce_catalog_restrictions_get_user_location', $location);
        }

        public function template_redirect() {
            $location = $this->get_location_for_current_user();

            if (empty($location) && $this->get_setting('_wc_restrictions_locations_required', 'no') == 'yes') {
                $location_page_id = woocommerce_get_page_id('choose_location');
                if ($location_page_id && !is_page($location_page_id)) {
                    $_SESSION['wc-catalog-restrictions-return-url'] = add_query_arg(array('locationset' => '1'));
                    $location_url = get_permalink($location_page_id);
                    if ($location_url) {
                        wp_safe_redirect($location_url);
                        exit;
                    }
                }
            }

            if (is_page(woocommerce_get_page_id('choose_location'))) {

                if (isset($_POST['save-location'])) {
                    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRIPPED);
                    $_SESSION['wc_location'] = $location;

                    do_action('wc_restrictions_location_updated');

                    if (isset($_SESSION['wc-catalog-restrictions-return-url'])) {
                        $url = $_SESSION['wc-catalog-restrictions-return-url'];
                        unset($_SESSION['wc-catalog-restrictions-return-url']);
                        wp_safe_redirect($url);
                    } else {
                        wp_safe_redirect(get_site_url());
                    }
                    exit;
                } else {
                    
                }
            }
        }

        /*
         * Utility functions
         */

        public function plugin_url() {
            return plugin_dir_url(__FILE__);
        }

        public function plugin_path() {
            return untrailingslashit(plugin_dir_path(__FILE__));
        }

        public function get_setting($key, $default = null) {
            return get_option($key, $default);
        }

    }

}

$GLOBALS['wc_catalog_restrictions'] = new WC_Catalog_Restrictions();
?>