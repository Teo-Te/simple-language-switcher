<?php

class SLS_Language_Manager {
    
    private $option_name = 'sls_languages';
    private $cookie_name = 'sls_current_locale';
    
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_sls_save_languages', [$this, 'save_languages']);
        add_action('wp_ajax_sls_set_language', [$this, 'set_language_ajax']);
        add_action('wp_ajax_nopriv_sls_set_language', [$this, 'set_language_ajax']);
        add_action('wp_ajax_sls_check_language_status', [$this, 'check_language_status_ajax']);
        add_action('init', [$this, 'handle_language_switch']);
        add_action('wp', [$this, 'sync_cookie_with_page_locale'], 5);
       
    }
    
    public function register_settings() {
        register_setting('sls_settings', $this->option_name);
    }

    public function check_language_status_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'sls_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $statuses = $this->get_language_statuses();
        wp_send_json_success($statuses);
    }
    
    public function get_languages() {
        $default = [
            'en_US' => [
                'code' => 'en',
                'name' => 'English',
                'flag' => '&#127482;&#127480;',
                'locale' => 'en_US',
                'active' => true
            ]
        ];
        
        return get_option($this->option_name, $default);
    }
    
    public function get_current_locale() {
        // Check if language switch was requested
        if (isset($_GET['switch_lang'])) {
            $requested_locale = sanitize_text_field($_GET['switch_lang']);
            $this->set_current_locale($requested_locale);
            return $requested_locale;
        }
        
        // Check cookie
        if (isset($_COOKIE[$this->cookie_name])) {
            return sanitize_text_field($_COOKIE[$this->cookie_name]);
        }
        
        // Default to first active language
        $languages = $this->get_languages();
        foreach ($languages as $locale => $lang) {
            if ($lang['active']) {
                return $locale;
            }
        }
        
        return 'en_US';
    }
    
    public function set_current_locale($locale) {
        setcookie($this->cookie_name, $locale, time() + (30 * DAY_IN_SECONDS), '/');
        $_COOKIE[$this->cookie_name] = $locale; // Set for current request
    }
    
    public function get_current_language() {
        $current_locale = $this->get_current_locale();
        $languages = $this->get_languages();
        
        if (isset($languages[$current_locale])) {
            return $languages[$current_locale]['code'];
        }
        
        return 'en';
    }
    
    public function handle_language_switch() {
        if (isset($_GET['switch_lang'])) {
            $locale = sanitize_text_field($_GET['switch_lang']);
            $this->set_current_locale($locale);
            
            // Redirect to clean URL
            $redirect_url = remove_query_arg('switch_lang');
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    public function set_language_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'sls_switch_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $locale = sanitize_text_field($_POST['locale']);
        $this->set_current_locale($locale);
        
        // Find the home page for this locale
        $redirect_url = $this->get_home_page_for_locale($locale);
        
        wp_send_json_success([
            'locale' => $locale,
            'redirect_url' => $redirect_url
        ]);
    }

    public function sync_cookie_with_page_locale() {
        // Skip admin pages
        if (is_admin()) {
            return;
        }
        
        // Skip AJAX requests
        if (wp_doing_ajax()) {
            return;
        }
        
        // Only handle pages
        if (!is_page()) {
            return;
        }
        
        global $post;
        if (!$post) {
            return;
        }
        
        // Get the page's language_locale ACF field
        $page_locale = get_field('language_locale', $post->ID);
        
        error_log("SLS Cookie Sync: Page {$post->ID} ({$post->post_name}) has locale: " . ($page_locale ?: 'none'));
        
        // If page is set to 'all', don't change the cookie - leave it as is
        if ($page_locale === 'all') {
            error_log("SLS Cookie Sync: Page set to 'all' - keeping current cookie");
            return;
        }
        
        // If no ACF field set, don't change the cookie (backwards compatibility)
        if (!$page_locale) {
            error_log("SLS Cookie Sync: No ACF field set - keeping current cookie");
            return;
        }
        
        // Check if this locale is valid in our language settings
        $languages = $this->get_languages();
        if (!isset($languages[$page_locale]) || !$languages[$page_locale]['active']) {
            error_log("SLS Cookie Sync: Locale '{$page_locale}' not valid or not active");
            return;
        }
        
        // Get current cookie value
        $current_cookie = isset($_COOKIE[$this->cookie_name]) ? $_COOKIE[$this->cookie_name] : '';
        
        // Only update if cookie doesn't match page locale
        if ($current_cookie !== $page_locale) {
            error_log("SLS Cookie Sync: Updating cookie from '{$current_cookie}' to '{$page_locale}'");
            
            // Set the new locale (same as language switcher)
            $this->set_current_locale($page_locale);
            
            // IMPORTANT: Trigger locale change for Loco Translate
            $this->trigger_locale_change($page_locale);
        } else {
            error_log("SLS Cookie Sync: Cookie already matches page locale: {$page_locale}");
        }
    }

    private function get_home_page_for_locale($locale) {
        // For en_US (main language), return the main home page
        if ($locale === 'en_US') {
            return home_url('/');
        }
        
        // For other languages, look for pages with that locale
        $pages_with_locale = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'language_locale',
                    'value' => $locale,
                    'compare' => '='
                ]
            ]
        ]);
        
        if (!empty($pages_with_locale)) {
            // Look for home-like patterns first
            $home_patterns = [
                strtolower(str_replace('_', '-', $locale)), // it-it
                substr($locale, 0, 2), // it
            ];
            
            foreach ($pages_with_locale as $page) {
                foreach ($home_patterns as $pattern) {
                    if ($page->post_name === $pattern) {
                        return get_permalink($page->ID);
                    }
                }
            }
            
            // If no pattern match, use the first page with that locale
            return get_permalink($pages_with_locale[0]->ID);
        }
        
        // Fallback to main home
        return home_url('/');
    }
    
    public function save_languages() {
        if (!wp_verify_nonce($_POST['nonce'], 'sls_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $languages = sanitize_text_field($_POST['languages']);
        $languages = json_decode(stripslashes($languages), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid JSON data');
            return;
        }
        
        // NEW: Install language packs for new languages
        $installation_results = $this->install_language_packs($languages);
        
        // Update the plugin's language settings
        update_option($this->option_name, $languages);
        
        // Send response with installation results
        if (!empty($installation_results['failed'])) {
            $message = 'Languages saved! Some language packs could not be installed: ' . implode(', ', $installation_results['failed']);
            wp_send_json_success($message);
        } else {
            wp_send_json_success('Languages saved and language packs installed successfully!');
        }
    }

    private function install_language_packs($new_languages) {
        $results = ['installed' => [], 'failed' => [], 'already_installed' => []];
        
        // Get currently available languages in WordPress
        $available_languages = $this->get_available_wp_languages();
        $installed_languages = get_available_languages();
        
        foreach ($new_languages as $locale => $lang_data) {
            if (!$lang_data['active']) {
                continue; // Skip inactive languages
            }
            
            $locale = $lang_data['locale'];
            
            // Skip if already installed
            if (in_array($locale, $installed_languages) || $locale === 'en_US') {
                $results['already_installed'][] = $locale;
                continue;
            }
            
            // Try to install the language pack
            if ($this->install_language_pack($locale)) {
                $results['installed'][] = $locale;
                error_log("SLS: Successfully installed language pack for {$locale}");
            } else {
                $results['failed'][] = $locale;
                error_log("SLS: Failed to install language pack for {$locale}");
            }
        }
        
        return $results;
    }

    private function install_language_pack($locale) {
        if (!function_exists('wp_download_language_pack')) {
            require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        }
        
        // Try to download and install the language pack
        $result = wp_download_language_pack($locale);
        
        if (is_wp_error($result)) {
            error_log("SLS: Error installing {$locale}: " . $result->get_error_message());
            return false;
        }
        
        if ($result === false) {
            error_log("SLS: Language pack for {$locale} not available or failed to install");
            return false;
        }
        
        return true;
    }

    private function get_available_wp_languages() {
        $transient_key = 'sls_available_languages';
        $languages = get_transient($transient_key);
        
        if (false === $languages) {
            $request = wp_remote_get('https://api.wordpress.org/translations/core/1.0/');
            
            if (is_wp_error($request)) {
                return [];
            }
            
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);
            
            if (isset($data['translations'])) {
                $languages = array_column($data['translations'], 'language');
                set_transient($transient_key, $languages, HOUR_IN_SECONDS);
            } else {
                $languages = [];
            }
        }
        
        return $languages;
    }

    public function is_language_available($locale) {
        $available = $this->get_available_wp_languages();
        return in_array($locale, $available) || $locale === 'en_US';
    }

    public function is_language_installed($locale) {
        $installed = get_available_languages();
        return in_array($locale, $installed) || $locale === 'en_US';
    }

    public function get_language_statuses() {
        $languages = $this->get_languages();
        $statuses = [];
        
        foreach ($languages as $locale => $lang_data) {
            $statuses[$locale] = [
                'installed' => $this->is_language_installed($lang_data['locale']),
                'available' => $this->is_language_available($lang_data['locale']),
                'locale' => $lang_data['locale']
            ];
        }
        
        return $statuses;
    }
    
    public function get_common_flags() {
        return [
            'en' => '&#127482;&#127480;', // 🇺🇸 US
            'gb' => '&#127468;&#127463;', // 🇬🇧 UK
            'sq' => '&#x1f1e6;&#x1f1f1;', // 🇦🇱 Albania
            'es' => '&#127466;&#127480;', // 🇪🇸 Spain
            'fr' => '&#127467;&#127479;', // 🇫🇷 France
            'de' => '&#127465;&#127466;', // 🇩🇪 Germany
            'it' => '&#127470;&#127481;', // 🇮🇹 Italy
            'pt' => '&#127477;&#127481;', // 🇵🇹 Portugal
            'nl' => '&#127475;&#127473;', // 🇳🇱 Netherlands
            'ru' => '&#127479;&#127482;', // 🇷🇺 Russia
            'zh' => '&#127464;&#127475;', // 🇨🇳 China
            'ja' => '&#127471;&#127477;', // 🇯🇵 Japan
            'ar' => '&#127462;&#127466;', // 🇦🇪 UAE (for Arabic)
        ];
    }
}

