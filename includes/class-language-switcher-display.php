<?php
class SLS_Switcher_Display {
    
    public function __construct() {
        add_shortcode('language_switcher', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_ajax_data']);
    }

    public function enqueue_ajax_data() {
        wp_localize_script('sls-switcher', 'sls_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sls_switch_nonce')
        ]);
    }

    public function display_switcher() {
        $manager = new SLS_Language_Manager();
        $languages = $manager->get_languages();
        $current_locale = $manager->get_current_locale();
        
        include SLS_PLUGIN_PATH . 'templates/switcher-dropdown.php';
    }
    
    public function shortcode($atts) {
        ob_start();
        $this->display_switcher();
        return ob_get_clean();
    }
    
    // NEW: Generate language switch URL
    public function get_language_url($target_locale) {
        return add_query_arg('switch_lang', $target_locale);
    }
}

