<?php
class SLS_Content_Filter {
    
    private $manager;
    
    public function __construct() {
        $this->manager = new SLS_Language_Manager();
        
        // Add content filtering with highest priority (for posts/products only)
        add_action('pre_get_posts', [$this, 'filter_content_by_locale'], 1);
        
        // add_filter('woocommerce_product_query_meta_query', [$this, 'filter_product_widgets'], 10, 2);
        add_action('pre_get_posts', [$this, 'filter_all_widget_queries'], 999);

        // Filter Woodmart related products shortcode
        add_filter('woodmart_related_products_args', [$this, 'filter_woodmart_related_shortcode'], 10, 1);

        // Filtering Cateogories
        add_filter('get_term', [$this, 'translate_category_globally'], 10, 2);
        add_filter('get_terms', [$this, 'translate_categories_in_array'], 10, 2);

        
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

    public function filter_woodmart_related_shortcode($products_atts) {
        if (is_admin()) {
            return $products_atts;
        }
        
        // Check if we have product IDs to filter
        if (!isset($products_atts['include']) || empty($products_atts['include'])) {
            return $products_atts;
        }
        
        $current_locale = $this->manager->get_current_locale();
        $product_ids = explode(',', $products_atts['include']);
        $filtered_ids = [];
        
        error_log("SLS Woodmart Related: Original IDs: " . $products_atts['include']);
        error_log("SLS Woodmart Related: Filtering for locale: {$current_locale}");
        
        // Filter each product ID based on locale
        foreach ($product_ids as $product_id) {
            $product_id = trim($product_id);
            
            if (empty($product_id)) {
                continue;
            }
            
            $product_locale = get_field('language_locale', $product_id);
            
            // Include if no locale set (backwards compatibility)
            if (!$product_locale) {
                $filtered_ids[] = $product_id;
                continue;
            }
            
            // Include if set to "all"
            if ($product_locale === 'all') {
                $filtered_ids[] = $product_id;
                continue;
            }
            
            // Include if matches current locale
            if ($product_locale === $current_locale) {
                $filtered_ids[] = $product_id;
                continue;
            }
            
            error_log("SLS Woodmart Related: Excluded product {$product_id} (locale: {$product_locale})");
        }
        
        // Update the include parameter with filtered IDs
        $products_atts['include'] = implode(',', $filtered_ids);
        
        error_log("SLS Woodmart Related: Filtered IDs: " . $products_atts['include']);
        
        return $products_atts;
    }
    
    /**
     * Filter product widgets (top rated, featured, etc.)
     */
    // public function filter_product_widgets($meta_query, $query) {
    //     if (is_admin()) return $meta_query;
        
    //     // Get the calling function to see what's triggering this
    //     $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
    //     $is_widget_call = false;
        
    //     foreach ($backtrace as $trace) {
    //         if (isset($trace['function'])) {
    //             // Look for widget-specific AND related product function calls
    //             if (in_array($trace['function'], [
    //                 'get_top_rated_products',
    //                 'get_featured_products',
    //                 'get_sale_products',
    //                 'get_best_selling_products',
    //                 'widget',
    //                 // Add related product functions
    //                 'woocommerce_output_related_products',
    //                 'get_related',
    //                 'get_related_products',
    //                 'output_related_products',
    //                 'related_products',
    //                 'woocommerce_related_products',
    //                 'woocommerce_output_related_products_args',
    //                 'get_related_products_and_upsells'
    //             ])) {
    //                 $is_widget_call = true;
    //                 break;
    //             }
    //         }
            
    //         // Also check class names for related products
    //         if (isset($trace['class'])) {
    //             if (strpos($trace['class'], 'Related') !== false || 
    //                 strpos($trace['class'], 'Widget') !== false) {
    //                 $is_widget_call = true;
    //                 break;
    //             }
    //         }
    //     }
        
    //     // Only apply to widget calls, not shop page queries
    //     if (!$is_widget_call) {
    //         return $meta_query;
    //     }
        
    //     $current_locale = $this->manager->get_current_locale();
        
    //     $meta_query[] = [
    //         'relation' => 'OR',
    //         [
    //             'key'     => 'language_locale',
    //             'value'   => $current_locale,
    //             'compare' => '='
    //         ],
    //         [
    //             'key'     => 'language_locale',
    //             'value'   => 'all',
    //             'compare' => '='
    //         ],
    //         [
    //             'key'     => 'language_locale',
    //             'compare' => 'NOT EXISTS'
    //         ]
    //     ];
        
    //     return $meta_query;
    // }

    public function filter_all_widget_queries($query) {
        if (is_admin() || $query->is_main_query()) {
            return;
        }
        
        // Only filter product and post queries
        $post_type = $query->get('post_type');
        if (!in_array($post_type, ['product', 'post'])) {
            return;
        }
        
        // Check if this is coming from a widget/elementor context
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        $is_widget_context = false;
        
        foreach ($backtrace as $trace) {
            if (isset($trace['class']) || isset($trace['function'])) {
                $context = ($trace['class'] ?? '') . '::' . ($trace['function'] ?? '');
                if (strpos($context, 'Elementor') !== false ||
                    strpos($context, 'Widget') !== false ||
                    strpos($context, 'woodmart') !== false) {
                    $is_widget_context = true;
                    error_log("SLS Nuclear Filter: Found widget context: {$context}");
                    break;
                }
            }
        }
        
        if (!$is_widget_context) {
            return;
        }
        
        error_log("SLS Nuclear Filter: Filtering {$post_type} widget query");
        
        // Apply locale filtering DIRECTLY (not through filter_content_by_locale)
        $current_locale = $this->manager->get_current_locale();
        
        // Get existing meta query
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
        
        error_log("SLS Nuclear Filter: Applied locale filter for {$current_locale}");
    }

    public function translate_category_globally($term, $taxonomy) {
        // Only translate product categories and blog categories on frontend
        if (is_admin() || !in_array($taxonomy, ['product_cat', 'category']) || !isset($term->name)) {
            return $term;
        }
        
        // Prevent infinite loops - check if we're already translating this term
        static $translating_terms = [];
        $term_key = $term->term_id . '_' . $taxonomy;
        
        if (isset($translating_terms[$term_key])) {
            return $term;
        }
        
        $translating_terms[$term_key] = true;
        
        try {
            $current_locale = $this->manager->get_current_locale();
            
            // Skip if English (default)
            if ($current_locale === 'en_US') {
                unset($translating_terms[$term_key]);
                return $term;
            }
            
            // Extract language code (e.g., it_IT -> it)
            $lang_code = substr($current_locale, 0, 2);
            
            // Get translated name from ACF field
            $translated_name = get_field('category_name_' . $lang_code, $taxonomy . '_' . $term->term_id);
            
            // Update term name if translation exists
            if ($translated_name && $translated_name !== $term->name) {
                $term->name = $translated_name;
            }
            
        } catch (Exception $e) {
            error_log("SLS Category Translation Error: " . $e->getMessage());
        }
        
        unset($translating_terms[$term_key]);
        return $term;
    }
    
    /**
     * Translate categories when retrieved as arrays (e.g., get_terms())
     */
    public function translate_categories_in_array($terms, $taxonomies) {
        // Only translate if product_cat or category is in the taxonomies array
        $target_taxonomies = ['product_cat', 'category'];
        $has_target_taxonomy = false;
        
        foreach ($target_taxonomies as $target_tax) {
            if (in_array($target_tax, (array)$taxonomies)) {
                $has_target_taxonomy = true;
                break;
            }
        }
        
        if (is_admin() || !$has_target_taxonomy) {
            return $terms;
        }
        
        // Prevent infinite loops
        static $translating_array = false;
        if ($translating_array) {
            return $terms;
        }
        
        $translating_array = true;
        
        try {
            $current_locale = $this->manager->get_current_locale();
            
            // Extract language code
            $lang_code = substr($current_locale, 0, 2);
            
            // Get ALL category counts in ONE query (much faster!)
            $product_category_ids = [];
            foreach ($terms as $term) {
                if (isset($term->taxonomy) && $term->taxonomy === 'product_cat') {
                    $product_category_ids[] = $term->term_id;
                }
            }
            
            // Get counts for all categories at once
            $category_counts = [];
            if (!empty($product_category_ids)) {
                $category_counts = $this->get_all_locale_product_counts($product_category_ids, $current_locale);
            }
            
            // Translate each term in the array
            foreach ($terms as $term) {
                if (isset($term->taxonomy) && in_array($term->taxonomy, $target_taxonomies)) {
                    // Translate the name
                    if ($current_locale !== 'en_US') {
                        $translated_name = get_field('category_name_' . $lang_code, $term->taxonomy . '_' . $term->term_id);
                        
                        if ($translated_name && $translated_name !== $term->name) {
                            $term->name = $translated_name;
                        }
                    }
                    
                    // Update count for product categories (from pre-calculated array)
                    if ($term->taxonomy === 'product_cat' && isset($category_counts[$term->term_id])) {
                        $term->count = $category_counts[$term->term_id];
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("SLS Category Array Translation Error: " . $e->getMessage());
        }
        
        $translating_array = false;
        return $terms;
    }
    
    /**
     * Enhanced filter to catch any query involving product categories
     */
    public function filter_taxonomy_queries($query) {
        // Skip admin
        if (is_admin()) {
            return;
        }
        
        // Check if this query involves product_cat taxonomy
        $tax_query = $query->get('tax_query');
        if (!empty($tax_query)) {
            foreach ($tax_query as $tax_clause) {
                if (isset($tax_clause['taxonomy']) && $tax_clause['taxonomy'] === 'product_cat') {
                    // This query involves product categories
                    error_log("SLS Category Filter: Found product_cat query");
                    // We don't modify the query itself, just let our term filters handle it
                    break;
                }
            }
        }
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

    private function get_all_locale_product_counts($category_ids, $locale) {
        global $wpdb;
        
        if (empty($category_ids)) {
            return [];
        }
        
        // Convert array to comma-separated string for SQL
        $category_ids_str = implode(',', array_map('intval', $category_ids));
        
        // Single SQL query to get ALL counts at once
        $sql = "
            SELECT 
                tt.term_id,
                COUNT(DISTINCT p.ID) as product_count
            FROM {$wpdb->term_taxonomy} tt
            INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'language_locale'
            WHERE tt.term_id IN ({$category_ids_str})
            AND tt.taxonomy = 'product_cat'
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (
                pm.meta_value = %s
                OR pm.meta_value = 'all'
                OR pm.meta_value IS NULL
            )
            GROUP BY tt.term_id
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $locale));
        
        // Convert to associative array
        $counts = [];
        foreach ($results as $result) {
            $counts[$result->term_id] = (int)$result->product_count;
        }
        
        // Fill in zero counts for categories with no products
        foreach ($category_ids as $category_id) {
            if (!isset($counts[$category_id])) {
                $counts[$category_id] = 0;
            }
        }
        
        return $counts;
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