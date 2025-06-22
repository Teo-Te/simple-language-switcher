<?php
class SLS_Content_Filter {
    
    private $manager;
    
    public function __construct() {
        $this->manager = new SLS_Language_Manager();
        
        // Add content filtering with highest priority (for posts/products only)
        add_action('pre_get_posts', [$this, 'filter_content_by_locale'], 1);
        
        // Add debug info to admin bar for testing
        add_action('admin_bar_menu', [$this, 'add_debug_to_admin_bar'], 999);
    }
    
    /**
     * Filter content by locale using pre_get_posts with highest priority
     * (This handles posts and products, but NOT pages)
     */
    public function filter_content_by_locale($query) {
        // Skip admin and non-main queries
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Skip pages - no filtering for pages, just show them
        if ($query->is_page()) {
            return;
        }
        
        // Get current locale from Language Manager (proper way)
        $current_locale = $this->manager->get_current_locale();
        
        // Add meta_query to filter by language_locale ACF field
        $existing_meta_query = $query->get('meta_query');
        
        if (!is_array($existing_meta_query)) {
            $existing_meta_query = [];
        }
        
        // Add our locale filtering
        $locale_meta_query = [
            'relation' => 'OR',
            [
                'key'     => 'language_locale',
                'value'   => $current_locale,
                'compare' => '='
            ],
            [
                'key'     => 'language_locale',
                'value'   => 'all',
                'compare' => '='
            ],
            [
                'key'     => 'language_locale',
                'compare' => 'NOT EXISTS'
            ]
        ];
        
        // Combine with existing meta queries if any
        if (!empty($existing_meta_query)) {
            $new_meta_query = [
                'relation' => 'AND',
                $existing_meta_query,
                $locale_meta_query
            ];
        } else {
            $new_meta_query = $locale_meta_query;
        }
        
        $query->set('meta_query', $new_meta_query);
    }
    
    /**
     * Helper method to check if content should be shown for current locale
     */
    public function should_show_content($post_id) {
        // Use Language Manager's method instead of duplicating logic
        $current_locale = $this->manager->get_current_locale();
        $content_locale = get_field('language_locale', $post_id);
        
        // Show if no ACF field set (backwards compatibility)
        if (!$content_locale) {
            return true;
        }
        
        // Show if set to "all"
        if ($content_locale === 'all') {
            return true;
        }
        
        // Show if matches current locale
        return $content_locale === $current_locale;
    }
    
    /**
     * Get current locale - USE LANGUAGE MANAGER'S METHOD
     */
    public function get_current_locale() {
        return $this->manager->get_current_locale();
    }
    
    /**
     * Add debug info to admin bar for testing
     */
    public function add_debug_to_admin_bar($admin_bar) {
        if (!current_user_can('administrator')) {
            return;
        }
        
        $current_locale = $this->manager->get_current_locale();
        $cookie_value = isset($_COOKIE['sls_current_locale']) ? $_COOKIE['sls_current_locale'] : 'Not set';
        
        if (is_page()) {
            global $post;
            $page_locale = get_field('language_locale', $post->ID);
            $admin_bar->add_menu([
                'id'    => 'sls_debug',
                'title' => "SLS Debug: {$current_locale} | Page: {$page_locale} | Cookie: {$cookie_value}",
                'href'  => '#',
            ]);
        } else {
            $admin_bar->add_menu([
                'id'    => 'sls_debug',
                'title' => "SLS Debug: {$current_locale} | Cookie: {$cookie_value}",
                'href'  => '#',
            ]);
        }
    }
}