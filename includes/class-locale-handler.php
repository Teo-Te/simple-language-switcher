<?php
class SLS_Locale_Handler {
    
    private $current_locale;
    
    public function __construct() {
        // Only handle frontend locale - NEVER admin
        add_filter('locale', [$this, 'set_locale'], 1);
        add_action('init', [$this, 'load_textdomains'], 20);
        
        // Reload textdomains when language changes via AJAX
        add_action('wp_ajax_sls_set_language', [$this, 'reload_textdomains']);
        add_action('wp_ajax_nopriv_sls_set_language', [$this, 'reload_textdomains']);
    }
    
    public function set_locale($locale) {
        if (is_admin()) {
            return $locale;
        }
        
        // Only handle frontend
        $manager = new SLS_Language_Manager();
        $this->current_locale = $manager->get_current_locale();
        
        error_log("SLS Locale Handler: Setting FRONTEND locale to {$this->current_locale}");
        
        return $this->current_locale;
    }
    
    public function load_textdomains() {
        if (is_admin()) return;
        
        if (!$this->current_locale) {
            $manager = new SLS_Language_Manager();
            $this->current_locale = $manager->get_current_locale();
        }
        
        error_log("SLS Locale Handler: Loading textdomains for {$this->current_locale}");
        
        // Unload existing textdomains
        unload_textdomain('woocommerce');
        unload_textdomain('woodmart');
        
        // Load WooCommerce textdomain
        if (class_exists('WooCommerce')) {
            $woo_paths = [
                WP_LANG_DIR . '/loco/plugins/woocommerce-' . $this->current_locale . '.mo',
                WP_PLUGIN_DIR . '/woocommerce/i18n/languages/woocommerce-' . $this->current_locale . '.mo',
                WP_LANG_DIR . '/plugins/woocommerce-' . $this->current_locale . '.mo'
            ];
            
            foreach ($woo_paths as $path) {
                if (file_exists($path)) {
                    load_textdomain('woocommerce', $path);
                    error_log("SLS Locale Handler: Loaded WooCommerce from: {$path}");
                    break;
                }
            }
        }
        
        // Load theme textdomain (Woodmart)
        $theme_paths = [
            WP_LANG_DIR . '/loco/themes/woodmart-' . $this->current_locale . '.mo',
            get_template_directory() . '/languages/woodmart-' . $this->current_locale . '.mo',
            WP_LANG_DIR . '/themes/woodmart-' . $this->current_locale . '.mo'
        ];
        
        foreach ($theme_paths as $path) {
            if (file_exists($path)) {
                load_textdomain('woodmart', $path);
                error_log("SLS Locale Handler: Loaded Woodmart from: {$path}");
                break;
            }
        }
    }
    
    public function reload_textdomains() {
        error_log("SLS Locale Handler: Reloading textdomains after AJAX language change");
        
        // Reset current locale
        $this->current_locale = null;
        
        // Reload textdomains
        $this->load_textdomains();
    }
}