<?php

class TAB_AdminPanel {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('wp_ajax_tab_manual_generate', array($this, 'handle_manual_generate'));
        add_action('wp_ajax_tab_test_connection', array($this, 'test_gemini_connection'));
        add_action('wp_ajax_tab_test_openai', array($this, 'test_openai_connection'));
        add_action('wp_ajax_tab_test_filter', array($this, 'test_filter_keywords'));
        add_action('wp_ajax_tab_generate_faqs', array($this, 'handle_faq_generation'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Trendie Auto Blogger', 'trendie-auto-blogger'),
            __('Trendie Blogger', 'trendie-auto-blogger'),
            'manage_options',
            'trendie-auto-blogger',
            array($this, 'admin_page'),
            'dashicons-rss',
            30
        );
        
        add_submenu_page(
            'trendie-auto-blogger',
            __('Settings', 'trendie-auto-blogger'),
            __('Settings', 'trendie-auto-blogger'),
            'manage_options',
            'trendie-auto-blogger-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'trendie-auto-blogger',
            __('Logs', 'trendie-auto-blogger'),
            __('Logs', 'trendie-auto-blogger'),
            'manage_options',
            'trendie-auto-blogger-logs',
            array($this, 'logs_page')
        );
        
        add_submenu_page(
            'trendie-auto-blogger',
            __('Trending FAQs', 'trendie-auto-blogger'),
            __('Trending FAQs', 'trendie-auto-blogger'),
            'manage_options',
            'trendie-auto-blogger-faqs',
            array($this, 'faqs_page')
        );
    }
    
    public function init_settings() {
        register_setting('tab_settings_group', 'tab_gemini_api_key');
        register_setting('tab_settings_group', 'tab_openai_api_key');
        register_setting('tab_settings_group', 'tab_openai_model');
        register_setting('tab_settings_group', 'tab_ai_fallback_enabled');
        register_setting('tab_settings_group', 'tab_rss_url');
        register_setting('tab_settings_group', 'tab_filter_keywords');
        register_setting('tab_settings_group', 'tab_post_category');
        register_setting('tab_settings_group', 'tab_post_status');
        register_setting('tab_settings_group', 'tab_max_posts_per_run');
        register_setting('tab_settings_group', 'tab_custom_prompt');
        register_setting('tab_settings_group', 'tab_pexels_api_key');
        register_setting('tab_settings_group', 'tab_enable_featured_images');
        register_setting('tab_settings_group', 'tab_image_orientation');
        register_setting('tab_settings_group', 'tab_enable_content_images');
        register_setting('tab_settings_group', 'tab_use_structured_generation');
        register_setting('tab_settings_group', 'tab_include_statistics');
        register_setting('tab_settings_group', 'tab_include_actionable_tips');
        register_setting('tab_settings_group', 'tab_optimize_for_eat');
        register_setting('tab_settings_group', 'tab_enable_auto_faqs');
    }
    
    public function admin_page() {
        ?>
        <div class="wrap tab-admin-wrap">
            <h1><?php _e('Trendie Auto Blogger Dashboard', 'trendie-auto-blogger'); ?></h1>
            
            <div class="tab-dashboard">
                <div class="tab-card">
                    <h2><?php _e('Quick Actions', 'trendie-auto-blogger'); ?></h2>
                    <div class="tab-actions">
                        <button id="tab-manual-generate" class="button button-primary tab-btn-primary">
                            <?php _e('Generate Posts Now', 'trendie-auto-blogger'); ?>
                        </button>
                        <button id="tab-test-connection" class="button tab-btn-secondary">
                            <?php _e('Test Gemini Connection', 'trendie-auto-blogger'); ?>
                        </button>
                        <button id="tab-test-openai" class="button tab-btn-secondary">
                            <?php _e('Test OpenAI Connection', 'trendie-auto-blogger'); ?>
                        </button>
                        <button id="tab-test-filter" class="button tab-btn-secondary">
                            <?php _e('Test Filter Keywords', 'trendie-auto-blogger'); ?>
                        </button>
                        <button id="tab-generate-faqs" class="button tab-btn-secondary">
                            <?php _e('Generate Trending FAQs', 'trendie-auto-blogger'); ?>
                        </button>
                    </div>
                    <div id="tab-status-message" class="tab-status-message"></div>
                </div>
                
                <div class="tab-card">
                    <h2><?php _e('Statistics', 'trendie-auto-blogger'); ?></h2>
                    <div class="tab-stats">
                        <?php $this->display_stats(); ?>
                    </div>
                </div>
                
                <div class="tab-card">
                    <h2><?php _e('Next Scheduled Run', 'trendie-auto-blogger'); ?></h2>
                    <div class="tab-schedule">
                        <?php $this->display_next_run(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function settings_page() {
        ?>
        <div class="wrap tab-admin-wrap">
            <h1><?php _e('Trendie Auto Blogger Settings', 'trendie-auto-blogger'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('tab_settings_group');
                do_settings_sections('tab_settings_group');
                ?>
                
                <div class="tab-settings-grid">
                    <div class="tab-card">
                        <h2><?php _e('AI Configuration', 'trendie-auto-blogger'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Gemini API Key (Primary)', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <input type="password" id="tab_gemini_api_key" name="tab_gemini_api_key" 
                                           value="<?php echo esc_attr(get_option('tab_gemini_api_key')); ?>" 
                                           class="regular-text" />
                                    <p class="description">
                                        <?php _e('Enter your Google Gemini API key. Get it from Google AI Studio.', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('OpenAI API Key (Fallback)', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <input type="password" id="tab_openai_api_key" name="tab_openai_api_key" 
                                           value="<?php echo esc_attr(get_option('tab_openai_api_key')); ?>" 
                                           class="regular-text" />
                                    <p class="description">
                                        <?php _e('Enter your OpenAI API key. Used as fallback if Gemini fails. Get it from OpenAI Platform.', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('OpenAI Model', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <select name="tab_openai_model">
                                        <option value="gpt-4-turbo-preview" <?php selected(get_option('tab_openai_model', 'gpt-4-turbo-preview'), 'gpt-4-turbo-preview'); ?>>
                                            GPT-4 Turbo (Recommended)
                                        </option>
                                        <option value="gpt-4" <?php selected(get_option('tab_openai_model'), 'gpt-4'); ?>>
                                            GPT-4
                                        </option>
                                        <option value="gpt-3.5-turbo" <?php selected(get_option('tab_openai_model'), 'gpt-3.5-turbo'); ?>>
                                            GPT-3.5 Turbo (Faster, Lower Cost)
                                        </option>
                                    </select>
                                    <p class="description">
                                        <?php _e('Select the OpenAI model to use for content generation.', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Enable AI Fallback', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="tab_ai_fallback_enabled" value="1" 
                                               <?php checked(get_option('tab_ai_fallback_enabled', 1), 1); ?> />
                                        <?php _e('Use OpenAI as fallback when Gemini fails', 'trendie-auto-blogger'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('If enabled, OpenAI API will be used automatically when Gemini API fails or returns errors.', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="tab-card">
                        <h2><?php _e('RSS Feed Configuration', 'trendie-auto-blogger'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Google Trends RSS URL', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <input type="url" id="tab_rss_url" name="tab_rss_url" 
                                           value="<?php echo esc_attr(get_option('tab_rss_url', 'https://trends.google.com/trends/trendingsearches/daily/rss?geo=US')); ?>" 
                                           class="regular-text" />
                                    <p class="description">
                                        <?php _e('Google Trends RSS feed URL. Default is for US trending searches.', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Filter Keywords', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <textarea id="tab_filter_keywords" name="tab_filter_keywords" 
                                              rows="4" cols="50" class="large-text"><?php echo esc_textarea(get_option('tab_filter_keywords', '')); ?></textarea>
                                    <p class="description">
                                        <?php _e('Comma-separated keywords to filter trends. Only trends containing these keywords will be processed. Leave empty to use default business/tech keywords: business, finance, stock, market, AI, crypto, technology, tech, startup, economy', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="tab-card">
                        <h2><?php _e('Post Configuration', 'trendie-auto-blogger'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Default Category', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <?php
                                    wp_dropdown_categories(array(
                                        'name' => 'tab_post_category',
                                        'selected' => get_option('tab_post_category', 1),
                                        'show_option_none' => __('Select Category', 'trendie-auto-blogger')
                                    ));
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Post Status', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <select name="tab_post_status">
                                        <option value="draft" <?php selected(get_option('tab_post_status', 'draft'), 'draft'); ?>>
                                            <?php _e('Draft', 'trendie-auto-blogger'); ?>
                                        </option>
                                        <option value="publish" <?php selected(get_option('tab_post_status'), 'publish'); ?>>
                                            <?php _e('Publish', 'trendie-auto-blogger'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Max Posts Per Run', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <input type="number" name="tab_max_posts_per_run" 
                                           value="<?php echo esc_attr(get_option('tab_max_posts_per_run', 5)); ?>" 
                                           min="1" max="20" class="small-text" />
                                    <p class="description">
                                        <?php _e('Maximum number of posts to generate in one run (1-20).', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="tab-card">
                        <h2><?php _e('Featured Images (Pexels Integration)', 'trendie-auto-blogger'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Enable Featured Images', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <input type="checkbox" id="tab_enable_featured_images" name="tab_enable_featured_images" 
                                           value="1" <?php checked(get_option('tab_enable_featured_images'), 1); ?> />
                                    <label for="tab_enable_featured_images">
                                        <?php _e('Automatically add featured images from Pexels', 'trendie-auto-blogger'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('When enabled, the plugin will automatically fetch and set featured images for generated posts.', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Pexels API Key', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <input type="password" id="tab_pexels_api_key" name="tab_pexels_api_key" 
                                           value="<?php echo esc_attr(get_option('tab_pexels_api_key')); ?>" 
                                           class="regular-text" />
                                    <p class="description">
                                        <?php _e('Enter your Pexels API key. Get it from Pexels.com API section.', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Image Orientation', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <select name="tab_image_orientation">
                                        <option value="landscape" <?php selected(get_option('tab_image_orientation', 'landscape'), 'landscape'); ?>>
                                            <?php _e('Landscape', 'trendie-auto-blogger'); ?>
                                        </option>
                                        <option value="portrait" <?php selected(get_option('tab_image_orientation'), 'portrait'); ?>>
                                            <?php _e('Portrait', 'trendie-auto-blogger'); ?>
                                        </option>
                                        <option value="square" <?php selected(get_option('tab_image_orientation'), 'square'); ?>>
                                            <?php _e('Square', 'trendie-auto-blogger'); ?>
                                        </option>
                                    </select>
                                    <p class="description">
                                        <?php _e('Preferred orientation for downloaded images.', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Content Images', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <input type="checkbox" id="tab_enable_content_images" name="tab_enable_content_images" 
                                           value="1" <?php checked(get_option('tab_enable_content_images', 1), 1); ?> />
                                    <label for="tab_enable_content_images">
                                        <?php _e('Add images within post content (recommended)', 'trendie-auto-blogger'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('When enabled, Gemini will suggest relevant images to be placed within the article content for better engagement.', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="tab-card">
                        <h2><?php _e('Advanced Content Generation', 'trendie-auto-blogger'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Use Structured Generation', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <input type="checkbox" id="tab_use_structured_generation" name="tab_use_structured_generation" 
                                           value="1" <?php checked(get_option('tab_use_structured_generation', 1), 1); ?> />
                                    <label for="tab_use_structured_generation">
                                        <?php _e('Enable enhanced content generation (recommended)', 'trendie-auto-blogger'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('Uses advanced prompt engineering for better SEO optimization, image suggestions, and content structure.', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Content Quality Settings', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="tab_include_statistics" value="1" 
                                                   <?php checked(get_option('tab_include_statistics', 1), 1); ?> />
                                            <?php _e('Include relevant statistics and facts', 'trendie-auto-blogger'); ?>
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="tab_include_actionable_tips" value="1" 
                                                   <?php checked(get_option('tab_include_actionable_tips', 1), 1); ?> />
                                            <?php _e('Include actionable insights and tips', 'trendie-auto-blogger'); ?>
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="tab_optimize_for_eat" value="1" 
                                                   <?php checked(get_option('tab_optimize_for_eat', 1), 1); ?> />
                                            <?php _e('Optimize for E-A-T guidelines', 'trendie-auto-blogger'); ?>
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="tab_enable_auto_faqs" value="1" 
                                                   <?php checked(get_option('tab_enable_auto_faqs', 1), 1); ?> />
                                            <?php _e('Auto-generate FAQs for each blog post', 'trendie-auto-blogger'); ?>
                                        </label>
                                    </fieldset>
                                    <p class="description">
                                        <?php _e('These settings improve content quality, SEO performance, and user engagement.', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="tab-card">
                        <h2><?php _e('Content Customization', 'trendie-auto-blogger'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Custom Prompt Template', 'trendie-auto-blogger'); ?></th>
                                <td>
                                    <textarea name="tab_custom_prompt" rows="6" cols="50" class="large-text"><?php 
                                        echo esc_textarea(get_option('tab_custom_prompt', $this->get_default_prompt())); 
                                    ?></textarea>
                                    <p class="description">
                                        <?php _e('Use {trend_title} as placeholder for the trending topic. This prompt will be sent to Gemini AI.', 'trendie-auto-blogger'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php submit_button(__('Save Settings', 'trendie-auto-blogger'), 'primary tab-btn-primary'); ?>
            </form>
        </div>
        <?php
    }
    
    public function logs_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trendie_logs';
        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 50");
        
        ?>
        <div class="wrap tab-admin-wrap">
            <h1><?php _e('Generation Logs', 'trendie-auto-blogger'); ?></h1>
            
            <div class="tab-card">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'trendie-auto-blogger'); ?></th>
                            <th><?php _e('Trend Title', 'trendie-auto-blogger'); ?></th>
                            <th><?php _e('Status', 'trendie-auto-blogger'); ?></th>
                            <th><?php _e('Post ID', 'trendie-auto-blogger'); ?></th>
                            <th><?php _e('Error Message', 'trendie-auto-blogger'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5"><?php _e('No logs found.', 'trendie-auto-blogger'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html($log->created_at); ?></td>
                                    <td><?php echo esc_html($log->trend_title); ?></td>
                                    <td>
                                        <span class="tab-status tab-status-<?php echo esc_attr($log->status); ?>">
                                            <?php echo esc_html(ucfirst($log->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($log->post_id): ?>
                                            <a href="<?php echo get_edit_post_link($log->post_id); ?>" target="_blank">
                                                <?php echo esc_html($log->post_id); ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($log->error_message ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    public function handle_manual_generate() {
        check_ajax_referer('tab_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'trendie-auto-blogger'));
        }
        
        $post_generator = new TAB_PostGenerator();
        $result = $post_generator->generate_posts();
        
        wp_send_json($result);
    }
    
    public function test_gemini_connection() {
        check_ajax_referer('tab_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'trendie-auto-blogger'));
        }
        
        $gemini = new TAB_GeminiAPI();
        $result = $gemini->test_connection();
        
        wp_send_json($result);
    }
    
    public function test_openai_connection() {
        check_ajax_referer('tab_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'trendie-auto-blogger'));
        }
        
        $openai = new TAB_OpenAIAPI();
        $result = $openai->test_connection();
        
        wp_send_json($result);
    }
    
    public function test_filter_keywords() {
        check_ajax_referer('tab_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'trendie-auto-blogger'));
        }
        
        $filter_keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';
        $result = $this->test_filter_functionality($filter_keywords);
        
        wp_send_json($result);
    }
    
    public function handle_faq_generation() {
        check_ajax_referer('tab_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'trendie-auto-blogger'));
        }
        
        $max_trends = isset($_POST['max_trends']) ? intval($_POST['max_trends']) : 5;
        
        $faq_manager = new TAB_FAQManager();
        $result = $faq_manager->generate_trending_faqs($max_trends);
        
        wp_send_json($result);
    }
    
    private function display_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trendie_logs';
        
        // Get total posts generated
        $total_posts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'success'");
        
        // Get posts generated today
        $today_posts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = 'success' AND DATE(created_at) = %s",
            current_time('Y-m-d')
        ));
        
        // Get failed attempts
        $failed_posts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'failed'");
        
        echo '<div class="tab-stats-grid">';
        echo '<div class="tab-stat-item"><span class="tab-stat-number">' . intval($total_posts) . '</span><span class="tab-stat-label">' . __('Total Posts Generated', 'trendie-auto-blogger') . '</span></div>';
        echo '<div class="tab-stat-item"><span class="tab-stat-number">' . intval($today_posts) . '</span><span class="tab-stat-label">' . __('Posts Today', 'trendie-auto-blogger') . '</span></div>';
        echo '<div class="tab-stat-item"><span class="tab-stat-number">' . intval($failed_posts) . '</span><span class="tab-stat-label">' . __('Failed Attempts', 'trendie-auto-blogger') . '</span></div>';
        echo '</div>';
    }
    
    /**
     * Test filter keywords functionality
     * 
     * @param string $keywords Comma-separated keywords to test
     * @return array Test results
     */
    private function test_filter_functionality($keywords) {
        try {
            // Temporarily update the filter keywords option
            $original_keywords = get_option('tab_filter_keywords', '');
            
            if (!empty($keywords)) {
                update_option('tab_filter_keywords', $keywords);
            }
            
            // Create RSS fetcher instance to test keywords
            $rss_fetcher = new TAB_RSSFetcher();
            $test_results = $rss_fetcher->test_filter_keywords();
            
            // Restore original keywords
            update_option('tab_filter_keywords', $original_keywords);
            
            return array(
                'success' => true,
                'message' => __('Filter keywords tested successfully', 'trendie-auto-blogger'),
                'results' => $test_results
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => __('Filter test failed: ', 'trendie-auto-blogger') . $e->getMessage()
            );
        }
    }
    
    private function display_next_run() {
        $next_run = wp_next_scheduled('tab_auto_generate_posts');
        
        if ($next_run) {
            echo '<p class="tab-next-run">' . 
                 sprintf(__('Next automatic run: %s', 'trendie-auto-blogger'), 
                         date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run)) . 
                 '</p>';
        } else {
            echo '<p class="tab-next-run tab-error">' . __('No scheduled runs found. Please deactivate and reactivate the plugin.', 'trendie-auto-blogger') . '</p>';
        }
    }
    
    private function get_default_prompt() {
        return "Write a comprehensive, SEO-optimized blog post about '{trend_title}' that will rank #1 on Google. Format your response EXACTLY as follows:

**Title:** [Write a compelling, click-worthy title under 60 characters with primary keyword at the beginning]

**Meta Description:** [Write a compelling meta description 150-160 characters that includes primary keyword and entices clicks with benefit/solution]

**Content:**
[Follow this exact SEO-optimized structure below]

**Tags:** [List 10-12 SEO-focused tags including primary keyword, long-tail variations, and semantic keywords]

**Primary Keyword:** {trend_title}
**Focus Keywords:** Extract 3-5 main keywords from the trend title and use them strategically throughout

CONTENT STRUCTURE FOR MAXIMUM SEO RANKING:

<h1>{trend_title}: Complete Guide for 2024 [Include year for freshness]</h1>

<p><strong>Quick Answer:</strong> [Provide immediate value - answer the main question in 2-3 sentences with primary keyword. This satisfies featured snippet requirements.]</p>

<p>[Hook paragraph with compelling statistics, data, or surprising fact. Include primary keyword naturally in first 100 words. Use power words like 'discover', 'ultimate', 'proven', 'essential'.]</p>

<h2>What Is {trend_title}? [Definition/Overview targeting 'what is' searches]</h2>
<p>[Clear, comprehensive definition that includes semantic keywords and answers user intent. Use bullet points for key characteristics.]</p>
<ul>
<li>[Key point 1 with related keyword]</li>
<li>[Key point 2 with semantic keyword]</li>
<li>[Key point 3 with long-tail variation]</li>
</ul>

<h2>Why {trend_title} Matters in 2024 [Targeting 'why' and current year searches]</h2>
<p>[Explain current relevance, impact, and importance. Include statistics, market data, or recent developments. Use semantic keywords naturally.]</p>

<h2>How {trend_title} Works: Complete Breakdown [Targeting 'how' searches]</h2>
<h3>Step-by-Step Process</h3>
<p>[Detailed explanation with numbered steps or process. Include related keywords and long-tail variations.]</p>

<h3>Key Components You Need to Know</h3>
<p>[Break down complex aspects into digestible sections. Use semantic keywords and answer potential questions.]</p>

<h2>{trend_title} Benefits and Advantages [Targeting benefit-focused searches]</h2>
<p>[List concrete benefits with supporting evidence. Use 'benefit' keywords and emotional triggers.]</p>
<ol>
<li><strong>[Benefit 1]:</strong> [Explanation with supporting data]</li>
<li><strong>[Benefit 2]:</strong> [Explanation with examples]</li>
<li><strong>[Benefit 3]:</strong> [Explanation with real-world applications]</li>
</ol>

<h2>Common {trend_title} Challenges and Solutions [Targeting problem/solution searches]</h2>
<h3>Challenge 1: [Specific problem with semantic keyword]</h3>
<p>[Problem description and actionable solution]</p>

<h3>Challenge 2: [Another common issue]</h3>
<p>[Problem description and practical solution]</p>

<h2>Expert Tips for {trend_title} Success [Targeting 'tips' and 'best practices' searches]</h2>
<p>[Actionable, specific advice that provides real value. Use authority-building language.]</p>
<ul>
<li><strong>Pro Tip 1:</strong> [Specific actionable advice]</li>
<li><strong>Pro Tip 2:</strong> [Advanced strategy or insight]</li>
<li><strong>Pro Tip 3:</strong> [Lesser-known but valuable tip]</li>
</ul>

<h2>{trend_title} vs Alternatives: Comparison Guide [Targeting comparison searches]</h2>
<p>[Compare with related topics, alternatives, or competing solutions. Use comparison keywords.]</p>

<h2>Latest {trend_title} Trends and Predictions for 2024-2025 [Targeting future/trend searches]</h2>
<p>[Discuss emerging trends, predictions, and future outlook. Include current year and next year for freshness.]</p>

<h2>Frequently Asked Questions About {trend_title}</h2>
<h3>Q: [Common question with long-tail keyword]?</h3>
<p><strong>A:</strong> [Concise, helpful answer with related keywords]</p>

<h3>Q: [Another frequent question]?</h3>
<p><strong>A:</strong> [Direct answer with semantic keywords]</p>

<h3>Q: [Third common question]?</h3>
<p><strong>A:</strong> [Comprehensive answer with long-tail variations]</p>

<h2>Conclusion: Key Takeaways About {trend_title}</h2>
<p>[Summarize main points, reinforce primary keyword, and provide clear next steps or call-to-action. End with forward-looking statement.]</p>

ADVANCED SEO REQUIREMENTS:
1. MINIMUM 1500 words for competitive ranking
2. Primary keyword density: 1.5-2% (natural placement)
3. Include 15-20 semantic keywords and LSI terms
4. Use long-tail keyword variations (3-4 word phrases)
5. Answer search intent for: what, why, how, benefits, comparison, tips
6. Include current year (2024) for freshness signals
7. Use schema-friendly FAQ section
8. Include numbered/bulleted lists for featured snippets
9. Use power words: ultimate, complete, proven, essential, expert, advanced
10. Include transition words: furthermore, additionally, however, consequently
11. Write for 8th-grade reading level for better accessibility
12. Use active voice (80%+ of sentences)
13. Include specific numbers, statistics, and data points
14. Answer potential follow-up questions users might have
15. Use topical authority keywords from the same niche

READABILITY & ACCESSIBILITY REQUIREMENTS:
16. Keep sentences under 20 words for better readability
17. Use simple, clear language - avoid jargon without explanation
18. Break up long paragraphs (max 3-4 sentences per paragraph)
19. Use subheadings every 200-300 words for better scanning
20. Include transition sentences between sections for flow
21. Use parallel structure in lists and bullet points
22. Vary sentence length for engaging rhythm
23. Use concrete examples and analogies for complex concepts

INCLUSIVE LANGUAGE GUIDELINES:
24. Use gender-neutral language (they/them, people, individuals)
25. Avoid assumptions about reader's background, location, or circumstances
26. Use 'people with disabilities' rather than 'disabled people'
27. Choose inclusive terms: 'everyone' instead of 'guys', 'team' instead of 'manpower'
28. Avoid idioms that may not translate across cultures
29. Use plain language that's accessible to non-native speakers
30. Include diverse examples and perspectives when relevant
31. Avoid ableist language (crazy, insane, blind to) - use specific alternatives
32. Use person-first language when discussing groups of people

KEYWORD INTEGRATION STRATEGY:
- H1: Primary keyword + modifier (guide, complete, 2024)
- H2s: Include semantic keywords and search intent modifiers
- H3s: Use long-tail variations and related terms
- First paragraph: Primary keyword in first 100 words
- Throughout content: Natural keyword placement every 150-200 words
- Conclusion: Reinforce primary keyword and related terms

SEARCH INTENT OPTIMIZATION:
- Informational: What, why, how sections
- Commercial: Benefits, comparison, tips sections  
- Navigational: Clear structure with table of contents feel
- Transactional: Call-to-action and next steps

Make sure to follow this exact format for maximum SEO impact and Google ranking potential.";
    }
    
    public function faqs_page() {
        global $wpdb;
        
        $faq_manager = new TAB_FAQManager();
        $table_name = $wpdb->prefix . 'trendie_faqs';
        
        // Handle actions
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'delete_faq' && isset($_POST['faq_id'])) {
                $faq_id = intval($_POST['faq_id']);
                $wpdb->update(
                    $table_name,
                    array('status' => 'deleted'),
                    array('id' => $faq_id),
                    array('%s'),
                    array('%d')
                );
                echo '<div class="notice notice-success"><p>' . __('FAQ deleted successfully.', 'trendie-auto-blogger') . '</p></div>';
            }
        }
        
        // Get FAQs for display
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $faqs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE status = 'active' ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        $total_faqs = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'active'");
        $total_pages = ceil($total_faqs / $per_page);
        
        ?>
        <div class="wrap tab-admin-wrap">
            <h1><?php _e('Trending FAQs Management', 'trendie-auto-blogger'); ?></h1>
            
            <div class="tab-faqs-actions" style="margin-bottom: 20px;">
                <button id="tab-generate-faqs-page" class="button button-primary">
                    <?php _e('Generate New FAQs', 'trendie-auto-blogger'); ?>
                </button>
                <div id="tab-faq-status" class="tab-status-message"></div>
            </div>
            
            <div class="tab-card">
                <h2><?php _e('Shortcode Usage', 'trendie-auto-blogger'); ?></h2>
                <p><?php _e('Use this shortcode to display trending FAQs on any page or post:', 'trendie-auto-blogger'); ?></p>
                <code style="background: #f1f1f1; padding: 8px 12px; border-radius: 4px; display: inline-block; margin: 10px 0;">
                    [trending_faqs per_page="10" show_title="yes" show_trend="yes" style="accordion" order="DESC"]
                </code>
                <p><?php _e('Parameters:', 'trendie-auto-blogger'); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><strong>per_page</strong>: <?php _e('Number of FAQs to show per page (default: 10)', 'trendie-auto-blogger'); ?></li>
                    <li><strong>show_title</strong>: <?php _e('Show "Trending FAQs" title (yes/no, default: yes)', 'trendie-auto-blogger'); ?></li>
                    <li><strong>show_trend</strong>: <?php _e('Show trend titles (yes/no, default: yes)', 'trendie-auto-blogger'); ?></li>
                    <li><strong>style</strong>: <?php _e('Display style (accordion, default: accordion)', 'trendie-auto-blogger'); ?></li>
                    <li><strong>order</strong>: <?php _e('Sort order (ASC/DESC, default: DESC)', 'trendie-auto-blogger'); ?></li>
                </ul>
            </div>
            
            <div class="tab-card">
                <h2><?php _e('FAQ Statistics', 'trendie-auto-blogger'); ?></h2>
                <div class="tab-stats">
                    <div class="tab-stat-item">
                        <span class="tab-stat-number"><?php echo $total_faqs; ?></span>
                        <span class="tab-stat-label"><?php _e('Total FAQs', 'trendie-auto-blogger'); ?></span>
                    </div>
                    <div class="tab-stat-item">
                        <?php
                        $recent_faqs = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'active' AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
                        ?>
                        <span class="tab-stat-number"><?php echo $recent_faqs; ?></span>
                        <span class="tab-stat-label"><?php _e('Added This Week', 'trendie-auto-blogger'); ?></span>
                    </div>
                    <div class="tab-stat-item">
                        <?php
                        $trend_count = $wpdb->get_var("SELECT COUNT(DISTINCT trend_title) FROM {$table_name} WHERE status = 'active'");
                        ?>
                        <span class="tab-stat-number"><?php echo $trend_count; ?></span>
                        <span class="tab-stat-label"><?php _e('Topics Covered', 'trendie-auto-blogger'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="tab-card">
                <h2><?php _e('Manage FAQs', 'trendie-auto-blogger'); ?></h2>
                
                <?php if (empty($faqs)): ?>
                    <p><?php _e('No FAQs found. Generate some FAQs to get started.', 'trendie-auto-blogger'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Question', 'trendie-auto-blogger'); ?></th>
                                <th><?php _e('Trend Topic', 'trendie-auto-blogger'); ?></th>
                                <th><?php _e('Created', 'trendie-auto-blogger'); ?></th>
                                <th><?php _e('Actions', 'trendie-auto-blogger'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faqs as $faq): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($faq->question); ?></strong>
                                        <div style="margin-top: 5px; color: #666; font-size: 12px;">
                                            <?php echo esc_html(wp_trim_words(strip_tags($faq->answer), 15)); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="tab-trend-badge"><?php echo esc_html($faq->trend_title); ?></span>
                                    </td>
                                    <td><?php echo date_i18n(get_option('date_format'), strtotime($faq->created_at)); ?></td>
                                    <td>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('<?php _e('Are you sure you want to delete this FAQ?', 'trendie-auto-blogger'); ?>');">
                                            <input type="hidden" name="action" value="delete_faq">
                                            <input type="hidden" name="faq_id" value="<?php echo $faq->id; ?>">
                                            <button type="submit" class="button button-small button-link-delete">
                                                <?php _e('Delete', 'trendie-auto-blogger'); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav">
                            <div class="tablenav-pages">
                                <?php
                                $page_links = paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => __('&laquo;'),
                                    'next_text' => __('&raquo;'),
                                    'total' => $total_pages,
                                    'current' => $page
                                ));
                                
                                if ($page_links) {
                                    echo '<span class="displaying-num">' . sprintf(__('%s items'), $total_faqs) . '</span>';
                                    echo $page_links;
                                }
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .tab-trend-badge {
            background: linear-gradient(135deg, #4e7465, #8db2a1);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#tab-generate-faqs-page').on('click', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const $status = $('#tab-faq-status');
                
                $button.prop('disabled', true).text('<?php _e('Generating...', 'trendie-auto-blogger'); ?>');
                $status.html('<div class="notice notice-info"><p><?php _e('Generating trending FAQs, please wait...', 'trendie-auto-blogger'); ?></p></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tab_generate_faqs',
                        max_trends: 5,
                        nonce: '<?php echo wp_create_nonce('tab_ajax_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            const result = response.data;
                            let message = '<div class="notice notice-success"><p>';
                            message += '<?php _e('FAQ Generation Complete!', 'trendie-auto-blogger'); ?><br>';
                            message += '<?php _e('Successful:', 'trendie-auto-blogger'); ?> ' + result.success + '<br>';
                            message += '<?php _e('Failed:', 'trendie-auto-blogger'); ?> ' + result.failed + '<br>';
                            message += '<?php _e('Total FAQs:', 'trendie-auto-blogger'); ?> ' + result.total_faqs;
                            message += '</p></div>';
                            
                            $status.html(message);
                            
                            // Reload page after 2 seconds
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $status.html('<div class="notice notice-error"><p><?php _e('Failed to generate FAQs. Please try again.', 'trendie-auto-blogger'); ?></p></div>');
                        }
                    },
                    error: function() {
                        $status.html('<div class="notice notice-error"><p><?php _e('An error occurred. Please try again.', 'trendie-auto-blogger'); ?></p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php _e('Generate New FAQs', 'trendie-auto-blogger'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
} 