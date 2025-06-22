<?php
/**
 * Plugin Name: Simple Language Switcher
 * Description: Simple language switcher for Loco Translate ONLY
 * Version: 1.0.0
 * Author: Teo NIV
 */

if (!defined('ABSPATH')) exit;

define('SLS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SLS_PLUGIN_PATH', plugin_dir_path(__FILE__));

class SimpleLanguageSwitcher {
    
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_enqueue_scripts']);
        
        // Include required files
        require_once SLS_PLUGIN_PATH . 'includes/class-language-manager.php';
        require_once SLS_PLUGIN_PATH . 'includes/class-language-switcher-display.php';
        require_once SLS_PLUGIN_PATH . 'includes/class-locale-handler.php';
        require_once SLS_PLUGIN_PATH . 'includes/class-content-filter.php';
    }
    
    public function init() {
        new SLS_Language_Manager();
        new SLS_Switcher_Display();
        new SLS_Locale_Handler(); // This handles translations
        
        // Initialize content filter AFTER locale handler
        $GLOBALS['sls_content_filter'] = new SLS_Content_Filter();
    }
    
    public function admin_menu() {
        add_options_page(
            'Language Switcher Settings',
            'Language Switcher', 
            'manage_options',
            'simple-language-switcher',
            [$this, 'admin_page']
        );
    }
    
    public function admin_enqueue_scripts($hook) {
        if ($hook === 'settings_page_simple-language-switcher') {
            wp_enqueue_style('sls-admin', SLS_PLUGIN_URL . 'assets/css/admin.css', [], '1.0.0');
            wp_enqueue_script('sls-admin', SLS_PLUGIN_URL . 'admin/admin-scripts.js', ['jquery'], '1.0.0', true);
        }
    }
    
    public function frontend_enqueue_scripts() {
        wp_enqueue_style('sls-switcher', SLS_PLUGIN_URL . 'assets/css/switcher.css', [], '1.0.0');
        wp_enqueue_script('sls-switcher', SLS_PLUGIN_URL . 'assets/js/switcher.js', ['jquery'], '1.0.0', true);
    }
    
    public function admin_page() {
        include SLS_PLUGIN_PATH . 'admin/admin-page.php';
    }
}

new SimpleLanguageSwitcher();

function sls_get_current_locale() {
    global $sls_content_filter;
    if ($sls_content_filter && $sls_content_filter->manager) {
        return $sls_content_filter->manager->get_current_locale();
    }
    
    if (class_exists('SLS_Language_Manager')) {
        $manager = new SLS_Language_Manager();
        return $manager->get_current_locale();
    }
    
    return 'en_US';
}

function sls_should_show_content($post_id) {
    global $sls_content_filter;
    return $sls_content_filter ? $sls_content_filter->should_show_content($post_id) : true;
}