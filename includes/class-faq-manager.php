<?php

class TAB_FAQManager {
    
    private $rss_fetcher;
    private $gemini_api;
    private $openai_api;
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'trendie_faqs';
        $this->rss_fetcher = new TAB_RSSFetcher();
        $this->gemini_api = new TAB_GeminiAPI();
        $this->openai_api = new TAB_OpenAIAPI();
        
        // Register shortcode
        add_shortcode('trending_faqs', array($this, 'display_faqs_shortcode'));
        
        // Add AJAX handlers
        add_action('wp_ajax_tab_generate_faqs', array($this, 'ajax_generate_faqs'));
        add_action('wp_ajax_nopriv_tab_load_more_faqs', array($this, 'ajax_load_more_faqs'));
        add_action('wp_ajax_tab_load_more_faqs', array($this, 'ajax_load_more_faqs'));
        
        // Enqueue scripts for frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * Create FAQ database table
     */
    public function create_faq_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
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
    
    /**
     * Generate FAQs from trending topics
     * 
     * @param int $max_trends Maximum number of trends to process
     * @return array Result of FAQ generation
     */
    public function generate_trending_faqs($max_trends = 5) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'messages' => array(),
            'total_faqs' => 0
        );
        
        // Fetch trending topics
        $trends = $this->rss_fetcher->fetch_trends();
        
        if (is_wp_error($trends)) {
            $results['messages'][] = __('Failed to fetch trends: ', 'trendie-auto-blogger') . $trends->get_error_message();
            return $results;
        }
        
        if (empty($trends)) {
            $results['messages'][] = __('No new trends found to process for FAQs', 'trendie-auto-blogger');
            return $results;
        }
        
        $processed = 0;
        
        foreach ($trends as $trend) {
            if ($processed >= $max_trends) {
                break;
            }
            
            // Check if we already have FAQs for this trend (recent ones)
            if ($this->has_recent_faqs($trend['title'])) {
                $results['messages'][] = sprintf(__('Skipping "%s" - Recent FAQs already exist', 'trendie-auto-blogger'), $trend['title']);
                $processed++;
                continue;
            }
            
            $faq_result = $this->generate_faqs_for_trend($trend);
            
            if ($faq_result['success']) {
                $results['success']++;
                $results['total_faqs'] += $faq_result['faq_count'];
                $results['messages'][] = sprintf(
                    __('Generated %d FAQs for: %s', 'trendie-auto-blogger'), 
                    $faq_result['faq_count'], 
                    $trend['title']
                );
            } else {
                $results['failed']++;
                $results['messages'][] = sprintf(
                    __('Failed to generate FAQs for: %s - %s', 'trendie-auto-blogger'), 
                    $trend['title'], 
                    $faq_result['message']
                );
            }
            
            $processed++;
        }
        
        return $results;
    }
    
    /**
     * Generate FAQs for a specific trend
     * 
     * @param array $trend Trend data
     * @return array Result of FAQ generation
     */
    public function generate_faqs_for_trend($trend) {
        try {
            // Create prompt for FAQ generation
            $prompt = $this->create_faq_prompt($trend['title'], $trend);
            
            // Generate FAQs using AI with fallback support
            $faq_result = $this->generate_faq_content_with_fallback($prompt);
            
            if (is_wp_error($faq_result)) {
                return array(
                    'success' => false,
                    'message' => $faq_result->get_error_message(),
                    'faq_count' => 0
                );
            }
            
            // Parse FAQ response
            $faqs = $this->parse_faq_response($faq_result['content']);
            
            if (empty($faqs)) {
                return array(
                    'success' => false,
                    'message' => __('No valid FAQs found in response', 'trendie-auto-blogger'),
                    'faq_count' => 0
                );
            }
            
            // Save FAQs to database
            $saved_count = $this->save_faqs_to_database($faqs, $trend);
            
            return array(
                'success' => true,
                'message' => sprintf(__('%d FAQs saved successfully', 'trendie-auto-blogger'), $saved_count),
                'faq_count' => $saved_count
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'faq_count' => 0
            );
        }
    }
    
    /**
     * Create prompt for FAQ generation
     * 
     * @param string $trend_title Trend title
     * @param array $trend_data Trend data
     * @return string FAQ generation prompt
     */
    private function create_faq_prompt($trend_title, $trend_data = array()) {
        $context = !empty($trend_data['description']) ? $trend_data['description'] : '';
        
        return "You are an expert content creator. Generate a comprehensive list of frequently asked questions (FAQs) about the trending topic: '{$trend_title}'.

**CONTEXT:** {$context}

**REQUIREMENTS:**
1. Generate 8-12 diverse and relevant questions
2. Provide detailed, informative answers (100-200 words each)
3. Cover different aspects of the topic (basics, advanced, practical, etc.)
4. Make questions natural and commonly asked
5. Ensure answers are accurate and helpful
6. Use simple, accessible language

**FORMAT:** Return your response as a JSON array with this exact structure:

{
    \"faqs\": [
        {
            \"question\": \"Clear, specific question about {$trend_title}?\",
            \"answer\": \"Detailed, informative answer with practical information and insights.\"
        }
    ]
}

**QUESTION CATEGORIES TO INCLUDE:**
- What is/are questions (basic understanding)
- How to questions (practical guidance)  
- Why questions (reasoning and benefits)
- When questions (timing and context)
- Where questions (location and availability)
- Common misconceptions or concerns
- Latest developments or trends
- Practical tips and best practices

Generate comprehensive FAQs about '{$trend_title}' now:";
    }
    
    /**
     * Parse FAQ response from Gemini
     * 
     * @param string $response Raw response from Gemini
     * @return array Parsed FAQs
     */
    private function parse_faq_response($response) {
        // Try to extract JSON from response
        $json_content = $this->extract_json_from_response($response);
        
        if (empty($json_content)) {
            // Fallback: try to parse simple Q&A format
            return $this->parse_simple_faq_format($response);
        }
        
        $parsed = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || empty($parsed['faqs'])) {
            return $this->parse_simple_faq_format($response);
        }
        
        return $parsed['faqs'];
    }
    
    /**
     * Extract JSON from response
     * 
     * @param string $content Response content
     * @return string|null JSON string or null
     */
    private function extract_json_from_response($content) {
        // Try to find JSON wrapped in code blocks
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $content, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/```\s*(\{.*?\})\s*```/s', $content, $matches)) {
            return $matches[1];
        }
        
        // Try to find raw JSON
        if (preg_match('/\{[^{}]*"faqs"[^{}]*\[.*?\]\s*\}/s', $content, $matches)) {
            return $matches[0];
        }
        
        return null;
    }
    
    /**
     * Parse simple Q&A format as fallback
     * 
     * @param string $content Response content
     * @return array Parsed FAQs
     */
    private function parse_simple_faq_format($content) {
        $faqs = array();
        
        // Split by common question patterns
        $sections = preg_split('/\n(?=Q:|Question:|[0-9]+\.|\*\*Q:|What|How|Why|When|Where|Is|Are|Can|Do|Does)/i', $content);
        
        foreach ($sections as $section) {
            $section = trim($section);
            if (empty($section)) continue;
            
            // Try to split question and answer
            if (preg_match('/^(.*?)\?\s*\n+(.*?)$/s', $section, $matches)) {
                $question = trim($matches[1]) . '?';
                $answer = trim($matches[2]);
                
                // Clean up formatting
                $question = preg_replace('/^(Q:|Question:|\d+\.\s*|\*\*Q:\*\*\s*)/i', '', $question);
                $answer = preg_replace('/^(A:|Answer:|\*\*A:\*\*\s*)/i', '', $answer);
                
                if (strlen($question) > 10 && strlen($answer) > 20) {
                    $faqs[] = array(
                        'question' => trim($question),
                        'answer' => trim($answer)
                    );
                }
            }
        }
        
        return $faqs;
    }
    
    /**
     * Save FAQs to database
     * 
     * @param array $faqs Array of FAQ data
     * @param array $trend Trend data
     * @return int Number of FAQs saved
     */
    private function save_faqs_to_database($faqs, $trend) {
        global $wpdb;
        
        $saved_count = 0;
        
        foreach ($faqs as $faq) {
            if (empty($faq['question']) || empty($faq['answer'])) {
                continue;
            }
            
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'trend_title' => $trend['title'],
                    'question' => sanitize_text_field($faq['question']),
                    'answer' => wp_kses_post($faq['answer']),
                    'trend_source' => isset($trend['source_url']) ? $trend['source_url'] : '',
                    'status' => 'active',
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result !== false) {
                $saved_count++;
            }
        }
        
        return $saved_count;
    }
    
    /**
     * Check if recent FAQs exist for a trend
     * 
     * @param string $trend_title Trend title
     * @param int $days_threshold Days to consider as recent
     * @return bool Whether recent FAQs exist
     */
    private function has_recent_faqs($trend_title, $days_threshold = 7) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE trend_title = %s 
             AND status = 'active' 
             AND created_at > DATE_SUB(NOW(), INTERVAL %d DAY)",
            $trend_title,
            $days_threshold
        ));
        
        return $count > 0;
    }
    
    /**
     * Display FAQs shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function display_faqs_shortcode($atts) {
        $atts = shortcode_atts(array(
            'per_page' => 10,
            'show_title' => 'yes',
            'show_trend' => 'yes',
            'style' => 'accordion',
            'order' => 'DESC'
        ), $atts);
        
        // Get FAQs
        $faqs = $this->get_faqs_for_display(1, $atts['per_page'], $atts['order']);
        $total_faqs = $this->get_total_faqs_count();
        
        if (empty($faqs)) {
            return '<div class="trendie-faqs-empty"><p>' . __('No trending FAQs available at the moment.', 'trendie-auto-blogger') . '</p></div>';
        }
        
        // Generate unique ID for this shortcode instance
        $shortcode_id = 'trendie-faqs-' . uniqid();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($shortcode_id); ?>" class="trendie-faqs-container" data-per-page="<?php echo esc_attr($atts['per_page']); ?>">
            <?php if ($atts['show_title'] === 'yes'): ?>
                <h3 class="trendie-faqs-title"><?php _e('Trending FAQs', 'trendie-auto-blogger'); ?></h3>
            <?php endif; ?>
            
            <div class="trendie-faqs-list" data-style="<?php echo esc_attr($atts['style']); ?>">
                <?php foreach ($faqs as $faq): ?>
                    <div class="trendie-faq-item" data-faq-id="<?php echo esc_attr($faq->id); ?>">
                        <?php if ($atts['show_trend'] === 'yes'): ?>
                            <div class="trendie-faq-trend">
                                <span class="trendie-trend-label"><?php _e('Trending:', 'trendie-auto-blogger'); ?></span>
                                <span class="trendie-trend-title"><?php echo esc_html($faq->trend_title); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="trendie-faq-question" data-toggle="answer">
                            <h4><?php echo esc_html($faq->question); ?></h4>
                            <span class="trendie-faq-toggle">+</span>
                        </div>
                        
                        <div class="trendie-faq-answer" style="display: none;">
                            <div class="trendie-faq-answer-content">
                                <?php echo wp_kses_post($faq->answer); ?>
                            </div>
                            <div class="trendie-faq-meta">
                                <small><?php echo sprintf(__('Updated: %s', 'trendie-auto-blogger'), date_i18n(get_option('date_format'), strtotime($faq->updated_at))); ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($total_faqs > $atts['per_page']): ?>
                <div class="trendie-faqs-pagination">
                    <button class="trendie-load-more-faqs" data-page="2" data-container="<?php echo esc_attr($shortcode_id); ?>">
                        <?php _e('Load More FAQs', 'trendie-auto-blogger'); ?>
                    </button>
                    <div class="trendie-faqs-count">
                        <?php echo sprintf(__('Showing %d of %d FAQs', 'trendie-auto-blogger'), count($faqs), $total_faqs); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get FAQs for display
     * 
     * @param int $page Page number
     * @param int $per_page FAQs per page
     * @param string $order Sort order
     * @return array FAQs data
     */
    public function get_faqs_for_display($page = 1, $per_page = 10, $order = 'DESC') {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        $order_by = $order === 'ASC' ? 'ASC' : 'DESC';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'active' 
             ORDER BY created_at {$order_by} 
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
    }
    
    /**
     * Get total FAQs count
     * 
     * @return int Total count
     */
    public function get_total_faqs_count() {
        global $wpdb;
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'active'"
        );
    }
    
    /**
     * AJAX handler for generating FAQs
     */
    public function ajax_generate_faqs() {
        check_ajax_referer('tab_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'trendie-auto-blogger'));
        }
        
        $max_trends = intval($_POST['max_trends'] ?? 5);
        $result = $this->generate_trending_faqs($max_trends);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX handler for loading more FAQs
     */
    public function ajax_load_more_faqs() {
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 10);
        $order = sanitize_text_field($_POST['order'] ?? 'DESC');
        
        $faqs = $this->get_faqs_for_display($page, $per_page, $order);
        
        if (empty($faqs)) {
            wp_send_json_error(__('No more FAQs to load', 'trendie-auto-blogger'));
        }
        
        ob_start();
        foreach ($faqs as $faq):
        ?>
            <div class="trendie-faq-item" data-faq-id="<?php echo esc_attr($faq->id); ?>">
                <div class="trendie-faq-trend">
                    <span class="trendie-trend-label"><?php _e('Trending:', 'trendie-auto-blogger'); ?></span>
                    <span class="trendie-trend-title"><?php echo esc_html($faq->trend_title); ?></span>
                </div>
                
                <div class="trendie-faq-question" data-toggle="answer">
                    <h4><?php echo esc_html($faq->question); ?></h4>
                    <span class="trendie-faq-toggle">+</span>
                </div>
                
                <div class="trendie-faq-answer" style="display: none;">
                    <div class="trendie-faq-answer-content">
                        <?php echo wp_kses_post($faq->answer); ?>
                    </div>
                    <div class="trendie-faq-meta">
                        <small><?php echo sprintf(__('Updated: %s', 'trendie-auto-blogger'), date_i18n(get_option('date_format'), strtotime($faq->updated_at))); ?></small>
                    </div>
                </div>
            </div>
        <?php
        endforeach;
        
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'has_more' => count($faqs) === $per_page
        ));
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        if (has_shortcode(get_post()->post_content ?? '', 'trending_faqs')) {
            wp_enqueue_script('trendie-faqs-js', TAB_PLUGIN_URL . 'assets/js/faqs-frontend.js', array('jquery'), TAB_VERSION, true);
            wp_enqueue_style('trendie-faqs-css', TAB_PLUGIN_URL . 'assets/css/faqs-frontend.css', array(), TAB_VERSION);
            
            wp_localize_script('trendie-faqs-js', 'trendie_faqs_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tab_ajax_nonce')
            ));
        }
    }
    
    /**
     * Generate FAQ content with AI fallback support
     * 
     * @param string $prompt FAQ generation prompt
     * @return array|WP_Error Generated content or error
     */
    private function generate_faq_content_with_fallback($prompt) {
        // Try Gemini API first
        $faq_result = $this->gemini_api->generate_content($prompt);
        
        if (!is_wp_error($faq_result)) {
            // Gemini succeeded
            return $faq_result;
        }
        
        // Store Gemini error for logging
        $gemini_error = $faq_result->get_error_message();
        
        // Check if OpenAI fallback is enabled
        $fallback_enabled = get_option('tab_ai_fallback_enabled', true);
        $openai_key = get_option('tab_openai_api_key', '');
        
        if ($fallback_enabled && !empty($openai_key)) {
            // Try OpenAI as fallback
            $faq_result = $this->openai_api->generate_content($prompt);
            
            if (!is_wp_error($faq_result)) {
                // OpenAI succeeded
                return $faq_result;
            }
            
            // Both APIs failed
            $openai_error = $faq_result->get_error_message();
            return new WP_Error(
                'both_apis_failed_faq', 
                sprintf(
                    __('Both AI services failed for FAQ generation. Gemini: %s | OpenAI: %s', 'trendie-auto-blogger'),
                    $gemini_error,
                    $openai_error
                )
            );
        }
        
        // Fallback not enabled or no OpenAI key, return original Gemini error
        return new WP_Error('gemini_failed_faq', $gemini_error);
    }
} 