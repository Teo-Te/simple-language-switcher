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
        add_action('wp_footer', [$this, 'add_homepage_redirect_js']);
       
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
        $_COOKIE[$this->cookie_name] = $locale;
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
        
        // If page is set to 'all', don't change the cookie - leave it as is
        if ($page_locale === 'all') {
            return;
        }
        
        // If no ACF field set, don't change the cookie (backwards compatibility)
        if (!$page_locale) {
            return;
        }
        
        // Check if this locale is valid in our language settings
        $languages = $this->get_languages();
        if (!isset($languages[$page_locale]) || !$languages[$page_locale]['active']) {
            return;
        }
        
        // Get current cookie value
        $current_cookie = isset($_COOKIE[$this->cookie_name]) ? $_COOKIE[$this->cookie_name] : '';
        
        // Only update if cookie doesn't match page locale
        if ($current_cookie !== $page_locale) {
            $this->set_current_locale($page_locale);
        }
    }

    private function get_home_page_for_locale($locale) {
        // For en_US (main language), return the main home page
        if ($locale === 'en_US') {
            return home_url('/');
        }
        
        // Extract language code from locale (e.g., it_IT -> it)
        $lang_code = substr($locale, 0, 2);
        
        // Define home page patterns
        $home_patterns = [
            $lang_code,                                    // it, sq, de
            strtolower(str_replace('_', '-', $locale)),   // it-it, sq-al
            'home-' . $lang_code,                         // home-it, home-sq
            'index-' . $lang_code,                        // index-it, index-sq
        ];
        
        // Look for pages with home-like slugs AND the correct locale
        foreach ($home_patterns as $pattern) {
            $page = get_page_by_path($pattern);
            if ($page) {
                $page_locale = get_field('language_locale', $page->ID);
                if ($page_locale === $locale) {
                    return get_permalink($page->ID);
                }
            }
        }
        
        // If no pattern match, look for pages with 'home' in title AND correct locale
        $home_pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'meta_query' => [
                [
                    'key' => 'language_locale',
                    'value' => $locale,
                    'compare' => '='
                ]
            ],
            's' => 'home' // Search for 'home' in title/content
        ]);
        
        if (!empty($home_pages)) {
            return get_permalink($home_pages[0]->ID);
        }
        
        // Last resort: look for any page with the language pattern in slug
        foreach ($home_patterns as $pattern) {
            $pages = get_posts([
                'post_type' => 'page',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'name' => $pattern,
                'meta_query' => [
                    [
                        'key' => 'language_locale',
                        'value' => $locale,
                        'compare' => '='
                    ]
                ]
            ]);
            
            if (!empty($pages)) {
                return get_permalink($pages[0]->ID);
            }
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
        
        //Install language packs for new languages
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
                continue;
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
            } else {
                $results['failed'][] = $locale;
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
            return false;
        }
        
        if ($result === false) {
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

    public function add_homepage_redirect_js() {
        if (is_admin()) {
            return;
        }
        
        $current_locale = $this->get_current_locale();
        
        // If English, no need for JavaScript
        if ($current_locale === 'en_US') {
            return;
        }
        
        $language_home = $this->get_home_page_for_locale($current_locale);
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Redirect any links that point to home page
            $('a[href="/"], a[href="<?php echo home_url('/'); ?>"], a[href="<?php echo home_url(); ?>"]').each(function() {
                var $link = $(this);
                var originalHref = $link.attr('href');
                
                // Update href to language-specific homepage
                $link.attr('href', '<?php echo esc_js($language_home); ?>');

            });
            
            // Handle dynamically created links (like Woodmart elements)
            $(document).on('click', 'a[href="/"], a[href="<?php echo home_url('/'); ?>"], a[href="<?php echo home_url(); ?>"]', function(e) {
                e.preventDefault();
                window.location.href = '<?php echo esc_js($language_home); ?>';
            });
        });
        </script>
        <?php
    }
}

