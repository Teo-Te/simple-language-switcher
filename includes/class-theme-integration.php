<?php

class SLS_Theme_Integration {
    
    private $language_manager;

    public function __construct() {
        $this->language_manager = new SLS_Language_Manager();
        
        // FIXED: Use array syntax for class methods
        add_shortcode('subcategory_filter', [$this, 'show_child_product_categories_of_current']);
        add_shortcode('translatable_footer', [$this, 'translatable_footer_shortcode']);
        add_shortcode('blog_categories', [$this, 'blog_categories_shortcode']);
        add_shortcode('comment_form', [$this, 'comment_form_shortcode']);

        add_filter('wp_nav_menu_objects', [$this, 'translate_menu_items'], 10, 2);

        add_action('wp_footer', [$this, 'replace_comment_form_js']);
    }

    // FIXED: Add public keyword and proper method name
    public function show_child_product_categories_of_current() {
        if (is_product_category()) {
            $term = get_queried_object();
            $parent_id = ($term->parent) ? $term->parent : $term->term_id;

            $args = array(
                'taxonomy'     => 'product_cat',
                'parent'       => $parent_id,
                'hide_empty'   => false,
                'orderby'      => 'name',
                'order'        => 'ASC',
            );

            $categories = get_terms($args);

            if (!empty($categories)) {
                // The title will be translated by Loco Translate
                $output = '<h5 class="custom-subcategory-title">' . __('Product categories', 'woodmart') . '</h5>';
                $output .= '<ul class="custom-subcategory-list">';

                foreach ($categories as $category) {
                    $link = get_term_link($category);
                    // Category names will be automatically translated by the plugin filters
                    $output .= '<li><a href="' . esc_url($link) . '">' . esc_html($category->name) . '</a></li>';
                }
                $output .= '</ul>';
                return $output;
            }
        }
        return '';
    }

    public function replace_comment_form_js() {
        // Only on single blog posts
        if (!is_single() || get_post_type() !== 'post') {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Hide the default comment form
            $('#comments').hide();
            
            // Insert your custom form after the comments list
            var customForm = `<?php echo str_replace(["\n", "\r"], '', addslashes($this->comment_form_shortcode())); ?>`;
            
            // Try different selectors to find where to insert
            if ($('#comments').length) {
                $('#comments').append(customForm);
            }
            
        });
        </script>
        <?php
    }

    // FIXED: Add public keyword
    public function translatable_footer_shortcode() {
        $current_locale = $this->language_manager->get_current_locale();
        ob_start();
        ?>
        <div class="footer-container">
            <div class="footer-column">
                <img style="max-width: 150px; margin-bottom: 15px;" src="https://heuristic-tereshkova.164-90-161-219.plesk.page/wp-content/uploads/2025/06/Logo_Real2.png" alt="<?php echo __('Logo', 'woodmart'); ?>" />
                <p>📍 <?php echo __('Via della Libertà 21, Firenze, Toscana, Italia', 'woodmart'); ?></p>
                <p>📞 <?php echo __('+39 055 123 4567', 'woodmart'); ?></p>
                <p>✉️ <?php echo __('arbëria@gmail.com', 'woodmart'); ?></p>
            </div>
            
            <!-- Column 2: Pages -->
            <div class="footer-column">
                <h3><?php echo __('Pages', 'woodmart'); ?></h3>
                <ul>
                <li><a href="<?php echo $this->map_url_to_locale(home_url('/'), $current_locale); ?>"><?php echo __('Home', 'woodmart'); ?></a></li>
                    <li><a href="/shop"><?php echo __('Shop', 'woodmart'); ?></a></li>
                    <li><a href="/blog"><?php echo __('Blog', 'woodmart'); ?></a></li>
                    <li><a href="<?php echo $this->map_url_to_locale(home_url('/about-us'), $current_locale); ?>"><?php echo __('About Us', 'woodmart'); ?></a></li>
                    <li><a href="<?php echo $this->map_url_to_locale(home_url('/contact-us'), $current_locale); ?>"><?php echo __('Contact Us', 'woodmart'); ?></a></li>
                    <li><a href="<?php echo $this->map_url_to_locale(home_url('/privacy-policy'), $current_locale); ?>"><?php echo __('Privacy Policy', 'woodmart'); ?></a></li>
                </ul>
            </div>
            
            <!-- Column 3: Shopping -->
            <div class="footer-column">
                <h3><?php echo __('Shopping', 'woodmart'); ?></h3>
                <ul>
                    <li><a href="/wishlist"><?php echo __('Wishlist', 'woodmart'); ?></a></li>
                    <li><a href="/compare"><?php echo __('Compare', 'woodmart'); ?></a></li>
                    <li><a href="/cart"><?php echo __('Cart', 'woodmart'); ?></a></li>
                    <li><a href="/my-account"><?php echo __('My Account', 'woodmart'); ?></a></li>
                </ul>
            </div>
            
            <!-- Column 4: Categories -->
            <div class="footer-column">
                <h3><?php echo __('Categories', 'woodmart'); ?></h3>
                <ul>
                    <li><a href="/product-category/beers"><?php echo __('Beers', 'woodmart'); ?></a></li>
                    <li><a href="/product-category/wines"><?php echo __('Wines', 'woodmart'); ?></a></li>
                    <li><a href="/product-category/dairy-products"><?php echo __('Dairy Products', 'woodmart'); ?></a></li>
                    <li><a href="/product-category/others"><?php echo __('Others', 'woodmart'); ?></a></li>
                </ul>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // FIXED: Add public keyword
    public function translate_menu_items($items, $args) {
        if (is_admin()) return $items;
        
        // Get current locale and current page info
        $current_locale = $this->language_manager->get_current_locale();
        $current_url = $_SERVER['REQUEST_URI'];
        
        foreach ($items as $item) {
            // Handle category menu items
            if ($item->object === 'product_cat') {
                $term = get_term($item->object_id, 'product_cat');
                if ($term && !is_wp_error($term)) {
                    // Category names will be translated by your plugin filters
                    // Don't change the URL for categories
                }
            } else {
                // Handle regular page menu items
                $translated_titles = array(
                    'Home' => __('Home', 'woodmart'),
                    'Shop' => __('Shop', 'woodmart'),
                    'Blog' => __('Blog', 'woodmart'),
                    'About Us' => __('About Us', 'woodmart'),
                    'Contact Us' => __('Contact Us', 'woodmart'),
                    'Compare' => __('Compare', 'woodmart'),
                );
                
                // Translate the title
                if (isset($translated_titles[$item->title])) {
                    $item->title = $translated_titles[$item->title];
                }
                
                // Store original URL before mapping
                $original_path = parse_url($item->url, PHP_URL_PATH);
                
                // FIXED: Use $this-> for method call
                $item->url = $this->map_url_to_locale($item->url, $current_locale);
                
                // FIXED: Use $this-> for method call
                $item = $this->add_current_menu_item_class($item, $original_path, $current_url, $current_locale);
            }
        }
        
        return $items;
    }

    // FIXED: Add private keyword and $this-> reference
    private function add_current_menu_item_class($menu_item, $original_path, $current_url, $current_locale) {
        // Remove existing current classes first
        $menu_item->classes = array_filter($menu_item->classes, function($class) {
            return !in_array($class, ['current-menu-item', 'current_page_item', 'current-menu-parent', 'current_page_parent']);
        });
        
        // Check if this menu item should be marked as current
        $is_current = false;
        
        if ($current_locale === 'en_US') {
            // For English, check original path
            $is_current = ($original_path === $current_url || rtrim($original_path, '/') === rtrim($current_url, '/'));
        } else {
            // For other languages, check both original and translated paths
            $lang_code = substr($current_locale, 0, 2);
            
            // Map original path to expected translated path
            if ($original_path === '/') {
                $expected_path = '/' . $lang_code;
            } else {
                // Skip categories, shop, blog, compare
                if (strpos($original_path, '/product-category/') !== false || 
                    strpos($original_path, '/blog') !== false || 
                    strpos($original_path, '/shop') !== false ||
                    strpos($original_path, '/compare') !== false) {
                    $expected_path = $original_path;
                } else {
                    $expected_path = rtrim($original_path, '/') . '-' . $lang_code;
                }
            }
            
            // Check if current URL matches expected translated path
            $is_current = (rtrim($current_url, '/') === rtrim($expected_path, '/'));
            
            // Also check if we're on a translated page that corresponds to this menu item
            if (!$is_current && $original_path === '/') {
                // Special case for home - check if we're on any home page for this locale
                $is_current = (rtrim($current_url, '/') === '/' . $lang_code);
            }
        }
        
        // Add current classes if this is the current page
        if ($is_current) {
            $menu_item->classes[] = 'current-menu-item';
            $menu_item->classes[] = 'current_page_item';
        }
        
        return $menu_item;
    }

    // FIXED: Add private keyword
    private function map_url_to_locale($original_url, $locale) {
        // Skip if it's en_US (default language)
        if ($locale === 'en_US') {
            return $original_url;
        }
        
        // Get the path from the URL
        $path = parse_url($original_url, PHP_URL_PATH);
        
        // Skip category, blog and shop URLs - don't modify them
        if (strpos($path, '/product-category/') !== false ||
            strpos($path, '/blog/') !== false ||
            strpos($path, '/shop/') !== false ||
            strpos($path, '/compare/') !== false) {
            return $original_url;
        }
        
        // Extract language code from locale (e.g., it_IT -> it, sq_AL -> sq)
        $lang_code = substr($locale, 0, 2);
        
        // Apply pattern-based mapping
        if ($path === '/') {
            // Home page: / -> /it, /sq, etc.
            return home_url('/' . $lang_code);
        } else {
            // Other pages: /about-us -> /about-us-it, /contact-us -> /contact-us-sq, etc.
            $new_path = rtrim($path, '/') . '-' . $lang_code;
            return home_url($new_path);
        }
    }

    public function blog_categories_shortcode($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts([
            'show_count' => 'false',
            'hide_empty' => 'true',
            'orderby' => 'name',
            'order' => 'ASC',
            'number' => ''
        ], $atts, 'blog_categories');
        
        // Get blog categories (they're already filtered by language in class-content-filter.php)
        $args = [
            'taxonomy' => 'category',
            'hide_empty' => ($atts['hide_empty'] === 'true'),
            'orderby' => $atts['orderby'],
            'order' => $atts['order']
        ];
        
        // Add number limit if specified
        if (!empty($atts['number'])) {
            $args['number'] = intval($atts['number']);
        }
        
        $categories = get_terms($args);
        
        if (empty($categories) || is_wp_error($categories)) {
            return '<p class="sls-no-categories">' . __('No categories found.', 'woodmart') . '</p>';
        }
        
        // Generate simple sidebar output
        $output = '<div class="sls-blog-categories-sidebar">';
        $output .= '<h3 class="widget-title">' . __('Categories', 'woodmart') . '</h3>';
        $output .= '<ul>';
        
        foreach ($categories as $category) {
            $output .= '<li>';
            $output .= '<a href="' . esc_url(get_category_link($category->term_id)) . '">';
            $output .= esc_html($category->name);
            
            // Show count if enabled
            if ($atts['show_count'] === 'true') {
                $output .= ' <span class="post-count">(' . $category->count . ')</span>';
            }
            
            $output .= '</a></li>';
        }
        
        $output .= '</ul></div>';
        return $output;
    }

    public function comment_form_shortcode($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts([
            'post_id' => '',
        ], $atts, 'comment_form');
        
        // Get post ID
        $post_id = !empty($atts['post_id']) ? intval($atts['post_id']) : get_the_ID();
        
        if (!$post_id) {
            return '';
        }
        
        // Check if comments are open
        if (!comments_open($post_id)) {
            return '<p class="sls-comments-closed">' . __('Comments are closed.', 'woodmart') . '</p>';
        }
        
        // Check if user must be logged in
        if (get_option('comment_registration') && !is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink($post_id));
            return '<p class="sls-login-required">' . 
                   sprintf(__('You must be <a href="%s">logged in</a> to post a comment.', 'woodmart'), $login_url) . 
                   '</p>';
        }
        
        ob_start();
        ?>
        <div id="sls-respond" class="sls-comment-respond">
            <h3 id="sls-reply-title" class="sls-comment-reply-title">
                <?php _e('Leave a reply', 'woodmart'); ?>
            </h3>
            
            <form action="<?php echo esc_url(site_url('/wp-comments-post.php')); ?>" method="post" id="sls-commentform" class="sls-comment-form">
                
                <?php if (is_user_logged_in()) : ?>
                    <p class="sls-logged-in-as">
                        <?php 
                        $user = wp_get_current_user();
                        printf(
                            __('Logged in as <a href="%1$s">%2$s</a>. <a href="%3$s" title="Log out of this account">Log out?</a>', 'woodmart'),
                            esc_url(get_edit_user_link()),
                            esc_html($user->display_name),
                            esc_url(wp_logout_url(get_permalink($post_id)))
                        );
                        ?>
                    </p>
                <?php else : ?>
                    <div class="sls-comment-form-fields">
                        <p class="sls-comment-form-author">
                            <label for="author"><?php _e('Name', 'woodmart'); ?> <span class="required">*</span></label>
                            <input id="author" name="author" type="text" value="<?php echo esc_attr(wp_get_current_commenter()['comment_author']); ?>" size="30" maxlength="245" required />
                        </p>
                        
                        <p class="sls-comment-form-email">
                            <label for="email"><?php _e('Email', 'woodmart'); ?> <span class="required">*</span></label>
                            <input id="email" name="email" type="email" value="<?php echo esc_attr(wp_get_current_commenter()['comment_author_email']); ?>" size="30" maxlength="100" required />
                        </p>
                        
                        <p class="sls-comment-form-url">
                            <label for="url"><?php _e('Website', 'woodmart'); ?></label>
                            <input id="url" name="url" type="url" value="<?php echo esc_attr(wp_get_current_commenter()['comment_author_url']); ?>" size="30" maxlength="200" />
                        </p>
                    </div>
                <?php endif; ?>
                
                <p class="sls-comment-form-comment">
                    <label for="comment"><?php _e('Comment', 'woodmart'); ?> <span class="required">*</span></label>
                    <textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" required></textarea>
                </p>
                
                <?php if (get_option('show_comments_cookies_opt_in')) : ?>
                    <p class="sls-comment-form-cookies-consent">
                        <input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes" />
                        <label for="wp-comment-cookies-consent">
                            <?php _e('Save my name, email, and website in this browser for the next time I comment.', 'woodmart'); ?>
                        </label>
                    </p>
                <?php endif; ?>
                
                <p class="sls-form-submit">
                    <input name="submit" type="submit" id="sls-submit" class="sls-submit" value="<?php esc_attr_e('Post Comment', 'woodmart'); ?>" />
                    <input type="hidden" name="comment_post_ID" value="<?php echo esc_attr($post_id); ?>" id="comment_post_ID" />
                    <input type="hidden" name="comment_parent" id="comment_parent" value="0" />
                </p>
                
                <?php wp_nonce_field('comment_' . $post_id, '_wp_unfiltered_html_comment', false); ?>
            </form>
        </div>
        <?php
        
        return ob_get_clean();
    }
}
