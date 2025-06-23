<?php
/**
 * Plugin Name: Trendie Auto Blogger
 * Plugin URI: https://hamzawebdev.com
 * Description: Automatically generates SEO-optimized blog posts from Google Trends using Gemini AI
 * Version: 1.0.0
 * Author: hamza khan
 * License: GPL v2 or later
 * Text Domain: trendie-auto-blogger
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TAB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TAB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TAB_VERSION', '1.0.0');

// Main plugin class
class TrendieAutoBlogger {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load plugin textdomain
        load_plugin_textdomain('trendie-auto-blogger', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize components
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once TAB_PLUGIN_DIR . 'includes/class-rss-fetcher.php';
        require_once TAB_PLUGIN_DIR . 'includes/class-gemini-api.php';
        require_once TAB_PLUGIN_DIR . 'includes/class-openai-api.php';
        require_once TAB_PLUGIN_DIR . 'includes/class-pexels-api.php';
        require_once TAB_PLUGIN_DIR . 'includes/class-post-generator.php';
        require_once TAB_PLUGIN_DIR . 'includes/class-admin-panel.php';
        require_once TAB_PLUGIN_DIR . 'includes/class-cron-manager.php';
        require_once TAB_PLUGIN_DIR . 'includes/class-seo-optimizer.php';
        require_once TAB_PLUGIN_DIR . 'includes/class-faq-manager.php';
    }
    
    private function init_hooks() {
        // Admin panel
        if (is_admin()) {
            new TAB_AdminPanel();
        }
        
        // Cron manager
        new TAB_CronManager();
        
        // FAQ manager
        new TAB_FAQManager();
        
        // Enqueue styles and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'trendie-auto-blogger') !== false) {
            wp_enqueue_style('tab-admin-style', TAB_PLUGIN_URL . 'assets/css/admin-style.css', array(), TAB_VERSION);
            wp_enqueue_script('tab-admin-script', TAB_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery', 'jquery-ui-tooltip'), TAB_VERSION, true);
            
            // Localize script for AJAX
            wp_localize_script('tab-admin-script', 'tab_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tab_ajax_nonce')
            ));
        }
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_style('tab-frontend-style', TAB_PLUGIN_URL . 'assets/css/frontend-style.css', array(), TAB_VERSION);
    }
    
    public function activate() {
        // Create necessary database tables
        $this->create_tables();
        
        // Create FAQ table
        $this->create_faq_table();
        
        // Schedule cron job
        if (!wp_next_scheduled('tab_auto_generate_posts')) {
            wp_schedule_event(time(), 'tab_eight_hours', 'tab_auto_generate_posts');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clear scheduled cron
        wp_clear_scheduled_hook('tab_auto_generate_posts');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trendie_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            trend_title varchar(255) NOT NULL,
            post_id mediumint(9),
            status varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            error_message text,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_faq_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trendie_faqs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            trend_title varchar(255) NOT NULL,
            question text NOT NULL,
            answer text NOT NULL,
            trend_source varchar(255),
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY trend_title (trend_title),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Custom cron interval
add_filter('cron_schedules', function($schedules) {
    $schedules['tab_eight_hours'] = array(
        'interval' => 8 * 3600, // 8 hours in seconds
        'display' => __('Every 8 Hours', 'trendie-auto-blogger')
    );
    return $schedules;
});

// Initialize the plugin
new TrendieAutoBlogger(); 