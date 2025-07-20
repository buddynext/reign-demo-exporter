<?php
/**
 * Content Scanner Class
 * 
 * @package Reign_Demo_Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Content_Scanner {
    
    private $content_summary = array();
    
    public function scan_all_content() {
        $content = array(
            'posts' => $this->get_posts_data(),
            'pages' => $this->get_pages_data(),
            'media' => $this->get_media_data(),
            'menus' => $this->get_menus_data(),
            'widgets' => $this->get_widgets_data(),
            'users' => $this->get_demo_users(),
            'custom_post_types' => $this->get_cpt_data(),
            'taxonomies' => $this->get_taxonomies_data(),
            'theme_mods' => $this->get_theme_mods(),
            'options' => $this->get_relevant_options(),
            'comments' => $this->get_comments_data(),
            'custom_tables' => $this->get_all_custom_tables()
        );
        
        // Set content summary
        $this->set_content_summary($content);
        
        return $content;
    }
    
    private function get_demo_users() {
        global $wpdb;
        
        // Get settings
        $settings_obj = new Reign_Demo_Exporter_Settings();
        $settings = $settings_obj->get_settings();
        
        if (!$settings['export_users']) {
            return array();
        }
        
        $min_user_id = absint($settings['min_user_id']) ?: 100;
        
        // Get users with ID >= min_user_id
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE ID >= %d",
            $min_user_id
        ));
        
        if (empty($user_ids)) {
            return array();
        }
        
        $users = get_users(array(
            'include' => $user_ids,
            'fields' => 'all_with_meta'
        ));
        
        $users_data = array();
        
        foreach ($users as $user) {
            // Skip users with ID < 100
            if ($user->ID < 100) {
                continue;
            }
            
            $user_data = array(
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'user_pass' => $user->user_pass, // Already hashed
                'user_nicename' => $user->user_nicename,
                'display_name' => $user->display_name,
                'user_url' => $user->user_url,
                'user_registered' => $user->user_registered,
                'user_status' => $user->user_status,
                'roles' => $user->roles,
                'meta' => $this->get_user_meta($user->ID)
            );
            
            $users_data[] = $user_data;
        }
        
        return $users_data;
    }
    
    private function get_user_meta($user_id) {
        $meta = get_user_meta($user_id);
        $clean_meta = array();
        
        // Only export relevant meta
        $allowed_meta = array(
            'description',
            'first_name',
            'last_name',
            'nickname',
            '_reign_demo_user',
            'reign_avatar_url',
            'bp_*', // All BuddyPress meta
            'wc_*', // WooCommerce meta
        );
        
        foreach ($meta as $key => $value) {
            foreach ($allowed_meta as $allowed) {
                if ($allowed === $key || (strpos($allowed, '*') && strpos($key, rtrim($allowed, '*')) === 0)) {
                    $clean_meta[$key] = maybe_unserialize($value[0]);
                    break;
                }
            }
        }
        
        return $clean_meta;
    }
    
    private function get_posts_data() {
        $posts = array();
        $post_types = get_post_types(array('public' => true), 'names');
        
        // Exclude certain post types
        $excluded_types = array('attachment', 'revision', 'nav_menu_item');
        $post_types = array_diff($post_types, $excluded_types);
        
        foreach ($post_types as $post_type) {
            $all_posts = get_posts(array(
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => array('publish', 'private', 'draft'),
                'orderby' => 'ID',
                'order' => 'ASC'
            ));
            
            foreach ($all_posts as $post) {
                $post_data = $post->to_array();
                $post_data['meta'] = $this->get_post_meta($post->ID);
                $post_data['terms'] = $this->get_post_terms($post->ID);
                $post_data['comments'] = $this->get_post_comments($post->ID);
                
                $posts[] = $post_data;
            }
        }
        
        return $posts;
    }
    
    private function get_pages_data() {
        $pages = get_pages(array(
            'post_status' => array('publish', 'private', 'draft')
        ));
        
        $pages_data = array();
        
        foreach ($pages as $page) {
            $page_data = $page->to_array();
            $page_data['meta'] = $this->get_post_meta($page->ID);
            $page_data['template'] = get_page_template_slug($page->ID);
            
            $pages_data[] = $page_data;
        }
        
        return $pages_data;
    }
    
    private function get_media_data() {
        $media_items = get_posts(array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        $media_data = array();
        
        foreach ($media_items as $media) {
            $media_array = $media->to_array();
            $media_array['meta'] = $this->get_post_meta($media->ID);
            $media_array['file_url'] = wp_get_attachment_url($media->ID);
            $media_array['metadata'] = wp_get_attachment_metadata($media->ID);
            
            $media_data[] = $media_array;
        }
        
        return $media_data;
    }
    
    private function get_post_meta($post_id) {
        $meta = get_post_meta($post_id);
        $clean_meta = array();
        
        // Exclude certain meta keys
        $excluded_keys = array('_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date');
        
        foreach ($meta as $key => $value) {
            if (!in_array($key, $excluded_keys)) {
                $clean_meta[$key] = maybe_unserialize($value[0]);
            }
        }
        
        return $clean_meta;
    }
    
    private function get_post_terms($post_id) {
        $taxonomies = get_object_taxonomies(get_post_type($post_id));
        $terms = array();
        
        foreach ($taxonomies as $taxonomy) {
            $post_terms = wp_get_object_terms($post_id, $taxonomy);
            if (!empty($post_terms) && !is_wp_error($post_terms)) {
                $terms[$taxonomy] = wp_list_pluck($post_terms, 'slug');
            }
        }
        
        return $terms;
    }
    
    private function get_post_comments($post_id) {
        $comments = get_comments(array(
            'post_id' => $post_id,
            'status' => 'approve'
        ));
        
        $comments_data = array();
        
        foreach ($comments as $comment) {
            $comment_data = $comment->to_array();
            unset($comment_data['comment_author_IP']); // Remove IP for privacy
            $comments_data[] = $comment_data;
        }
        
        return $comments_data;
    }
    
    private function get_menus_data() {
        $menus = wp_get_nav_menus();
        $menus_data = array();
        
        foreach ($menus as $menu) {
            $menu_data = array(
                'term_id' => $menu->term_id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'items' => array()
            );
            
            $menu_items = wp_get_nav_menu_items($menu->term_id);
            
            foreach ($menu_items as $item) {
                $menu_data['items'][] = array(
                    'ID' => $item->ID,
                    'menu_item_parent' => $item->menu_item_parent,
                    'title' => $item->title,
                    'url' => $item->url,
                    'object' => $item->object,
                    'object_id' => $item->object_id,
                    'type' => $item->type,
                    'type_label' => $item->type_label,
                    'target' => $item->target,
                    'attr_title' => $item->attr_title,
                    'description' => $item->description,
                    'classes' => $item->classes,
                    'xfn' => $item->xfn,
                    'menu_order' => $item->menu_order
                );
            }
            
            $menus_data[] = $menu_data;
        }
        
        // Get menu locations
        $locations = get_nav_menu_locations();
        
        return array(
            'menus' => $menus_data,
            'locations' => $locations
        );
    }
    
    private function get_widgets_data() {
        global $wp_registered_widgets;
        
        $sidebars_widgets = wp_get_sidebars_widgets();
        $widget_instances = array();
        
        foreach ($wp_registered_widgets as $widget_id => $widget) {
            $widget_base = _get_widget_id_base($widget_id);
            if (!isset($widget_instances[$widget_base])) {
                $widget_instances[$widget_base] = get_option('widget_' . $widget_base);
            }
        }
        
        return array(
            'sidebars' => $sidebars_widgets,
            'instances' => $widget_instances
        );
    }
    
    private function get_cpt_data() {
        $custom_post_types = get_post_types(array(
            '_builtin' => false,
            'public' => true
        ), 'objects');
        
        $cpt_data = array();
        
        foreach ($custom_post_types as $cpt) {
            $count = wp_count_posts($cpt->name);
            $cpt_data[$cpt->name] = array(
                'label' => $cpt->label,
                'count' => $count->publish + $count->private + $count->draft
            );
        }
        
        return $cpt_data;
    }
    
    private function get_taxonomies_data() {
        $taxonomies = get_taxonomies(array('public' => true), 'objects');
        $tax_data = array();
        
        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms(array(
                'taxonomy' => $taxonomy->name,
                'hide_empty' => false
            ));
            
            if (!is_wp_error($terms)) {
                $tax_data[$taxonomy->name] = array(
                    'label' => $taxonomy->label,
                    'terms' => $terms
                );
            }
        }
        
        return $tax_data;
    }
    
    private function get_theme_mods() {
        $theme_slug = get_option('stylesheet');
        $mods = get_option("theme_mods_{$theme_slug}");
        
        return $mods ? $mods : array();
    }
    
    private function get_relevant_options() {
        // List of options to export
        $option_keys = array(
            'blogname',
            'blogdescription',
            'start_of_week',
            'timezone_string',
            'date_format',
            'time_format',
            'default_comment_status',
            'default_ping_status',
            'default_pingback_flag',
            'posts_per_page',
            'default_category',
            'default_post_format',
            'page_on_front',
            'page_for_posts',
            'show_on_front',
            'sticky_posts'
        );
        
        // Add Reign theme options
        $option_keys[] = 'reign_theme_options';
        
        // Add plugin-specific options
        if (is_plugin_active('buddypress/bp-loader.php')) {
            $option_keys[] = 'bp-active-components';
            $option_keys[] = 'bp-pages';
            $option_keys[] = 'hide-loggedout-adminbar';
        }
        
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            $option_keys[] = 'woocommerce_shop_page_id';
            $option_keys[] = 'woocommerce_cart_page_id';
            $option_keys[] = 'woocommerce_checkout_page_id';
            $option_keys[] = 'woocommerce_myaccount_page_id';
        }
        
        $options = array();
        
        foreach ($option_keys as $key) {
            $value = get_option($key);
            if ($value !== false) {
                $options[$key] = $value;
            }
        }
        
        return $options;
    }
    
    private function get_comments_data() {
        $comments = get_comments(array(
            'status' => 'approve',
            'number' => 100 // Limit to recent 100 comments
        ));
        
        $comments_data = array();
        
        foreach ($comments as $comment) {
            $comment_array = $comment->to_array();
            unset($comment_array['comment_author_IP']); // Privacy
            $comments_data[] = $comment_array;
        }
        
        return $comments_data;
    }
    
    private function get_buddypress_data() {
        return $this->get_plugin_tables_data('bp_');
    }
    
    private function get_plugin_tables_data($prefix) {
        global $wpdb;
        $plugin_data = array();
        
        // Get all tables with the specified prefix
        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}{$prefix}%'", ARRAY_N);
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            $clean_name = str_replace($wpdb->prefix, '', $table_name);
            
            // Export entire table
            $plugin_data[$clean_name] = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
        }
        
        return $plugin_data;
    }
    
    public function get_all_custom_tables() {
        global $wpdb;
        $all_tables = array();
        
        // Get all tables
        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        
        // WordPress core tables to exclude
        $core_tables = array(
            'commentmeta', 'comments', 'links', 'options', 'postmeta', 
            'posts', 'term_relationships', 'term_taxonomy', 'termmeta', 'terms',
            'usermeta', 'users'
        );
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            
            // Skip if not a WordPress table
            if (strpos($table_name, $wpdb->prefix) !== 0) {
                continue;
            }
            
            // Get table name without prefix
            $clean_name = str_replace($wpdb->prefix, '', $table_name);
            
            // Skip core tables
            if (in_array($clean_name, $core_tables)) {
                continue;
            }
            
            // Export the entire table
            $all_tables[$clean_name] = array(
                'structure' => $this->get_table_structure($table_name),
                'data' => $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A)
            );
        }
        
        return $all_tables;
    }
    
    private function get_table_structure($table_name) {
        global $wpdb;
        
        // Get CREATE TABLE statement
        $create_table = $wpdb->get_row("SHOW CREATE TABLE {$table_name}", ARRAY_A);
        
        return array(
            'create_statement' => $create_table['Create Table'],
            'columns' => $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A),
            'indexes' => $wpdb->get_results("SHOW INDEX FROM {$table_name}", ARRAY_A)
        );
    }
    
    private function get_woocommerce_data() {
        $wc_data = array();
        
        // Get sample orders (limit to 50)
        $orders = wc_get_orders(array(
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $wc_data['orders'] = array();
        foreach ($orders as $order) {
            $wc_data['orders'][] = $order->get_data();
        }
        
        // Get product categories
        $product_cats = get_terms('product_cat', array('hide_empty' => false));
        $wc_data['product_categories'] = $product_cats;
        
        // Get product tags
        $product_tags = get_terms('product_tag', array('hide_empty' => false));
        $wc_data['product_tags'] = $product_tags;
        
        return $wc_data;
    }
    
    private function get_lms_data() {
        $lms_data = array();
        
        // LearnDash
        if (is_plugin_active('learndash/learndash.php')) {
            $courses = get_posts(array(
                'post_type' => 'sfwd-courses',
                'posts_per_page' => -1
            ));
            
            $lms_data['courses'] = array();
            foreach ($courses as $course) {
                $lms_data['courses'][] = array(
                    'id' => $course->ID,
                    'title' => $course->post_title,
                    'content' => $course->post_content,
                    'meta' => get_post_meta($course->ID)
                );
            }
        }
        
        return $lms_data;
    }
    
    private function set_content_summary($content) {
        $this->content_summary = array(
            'posts' => count($content['posts']),
            'pages' => count($content['pages']),
            'media_items' => count($content['media']),
            'menus' => count($content['menus']['menus']),
            'widgets' => count($content['widgets']['sidebars']),
            'users' => count($content['users']),
            'custom_post_types' => $content['custom_post_types']
        );
    }
    
    public function get_content_summary() {
        return $this->content_summary;
    }
}