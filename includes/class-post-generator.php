<?php

class TAB_PostGenerator {
    
    private $rss_fetcher;
    private $gemini_api;
    private $openai_api;
    private $seo_optimizer;
    private $pexels_api;
    private $faq_manager;
    
    public function __construct() {
        $this->rss_fetcher = new TAB_RSSFetcher();
        $this->gemini_api = new TAB_GeminiAPI();
        $this->openai_api = new TAB_OpenAIAPI();
        $this->seo_optimizer = new TAB_SEOOptimizer();
        $this->pexels_api = new TAB_PexelsAPI();
        $this->faq_manager = new TAB_FAQManager();
    }
    
    /**
     * Generate posts from trending topics
     * 
     * @return array Result of post generation
     */
    public function generate_posts() {
        $max_posts = intval(get_option('tab_max_posts_per_run', 5));
        $results = array(
            'success' => 0,
            'failed' => 0,
            'messages' => array()
        );
        
        // Fetch trending topics
        $trends = $this->rss_fetcher->fetch_trends();
        
        if (is_wp_error($trends)) {
            $results['messages'][] = __('Failed to fetch trends: ', 'trendie-auto-blogger') . $trends->get_error_message();
            return $results;
        }
        
        if (empty($trends)) {
            $results['messages'][] = __('No new trends found to process', 'trendie-auto-blogger');
            return $results;
        }
        
        $processed = 0;
        
        foreach ($trends as $trend) {
            if ($processed >= $max_posts) {
                break;
            }
            
            $result = $this->generate_single_post($trend);
            
            if ($result['success']) {
                $results['success']++;
                $results['messages'][] = sprintf(__('Successfully generated post for: %s', 'trendie-auto-blogger'), $trend['title']);
            } else {
                $results['failed']++;
                $results['messages'][] = sprintf(__('Failed to generate post for: %s - %s', 'trendie-auto-blogger'), $trend['title'], $result['message']);
            }
            
            $processed++;
        }
        
        return $results;
    }
    
    /**
     * Generate a single post from trend data
     * 
     * @param array $trend Trend data
     * @return array Result of single post generation
     */
    private function generate_single_post($trend) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trendie_logs';
        
        try {
            // Generate content using enhanced Gemini API with OpenAI fallback
            $content_result = $this->generate_content_with_fallback($trend['title'], $trend);
            
            if (is_wp_error($content_result)) {
                $this->log_error($trend['title'], $content_result->get_error_message());
                return array(
                    'success' => false,
                    'message' => $content_result->get_error_message()
                );
            }
            
            // Handle both structured and legacy responses
            if ($content_result['structured']) {
                $parsed_content = $content_result['content'];
            } else {
                // Fallback to legacy parsing for non-structured responses
            $parsed_content = $this->parse_generated_content($content_result['content']);
            $optimized_content = $this->seo_optimizer->optimize_content($parsed_content, $trend);
                $parsed_content = array_merge($parsed_content, $optimized_content);
            }
            
            // Validate required content fields
            if (empty($parsed_content['title']) || empty($parsed_content['content'])) {
                $this->log_error($trend['title'], 'Generated content missing required fields');
                return array(
                    'success' => false,
                    'message' => __('Generated content missing required fields', 'trendie-auto-blogger')
                );
            }
            
            // Create WordPress post
            $post_id = $this->create_wordpress_post($parsed_content, $trend);
            
            if (is_wp_error($post_id)) {
                $this->log_error($trend['title'], $post_id->get_error_message());
                return array(
                    'success' => false,
                    'message' => $post_id->get_error_message()
                );
            }
            
            // Process images with enhanced integration
            $image_result = $this->process_post_images($post_id, $parsed_content, $trend, $parsed_content['title']);
            
            // Update post content if images were added
            if (!empty($image_result['updated_content'])) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $image_result['updated_content']
                ));
            }
            
            // Generate FAQs for this trend automatically
            $faq_generated = false;
            if (get_option('tab_enable_auto_faqs', true)) {
                try {
                    $faq_result = $this->faq_manager->generate_faqs_for_trend($trend);
                    $faq_generated = $faq_result['success'];
                } catch (Exception $e) {
                    // FAQ generation failed, but don't fail the main process
                    error_log('FAQ generation failed: ' . $e->getMessage());
                }
            }
            
            // Determine which AI was used
            $ai_used = isset($content_result['ai_used']) ? $content_result['ai_used'] : 'unknown';
            $ai_display = $ai_used === 'openai' ? 'OpenAI (Fallback)' : 'Gemini';
            
            // Log success with additional details
            $success_message = sprintf(
                __('Post created successfully. AI: %s, Images: %s, FAQs: %s', 'trendie-auto-blogger'),
                $ai_display,
                $image_result['success'] ? __('Added', 'trendie-auto-blogger') : __('Failed', 'trendie-auto-blogger'),
                $faq_generated ? __('Generated', 'trendie-auto-blogger') : __('Skipped', 'trendie-auto-blogger')
            );
            
            $wpdb->insert(
                $table_name,
                array(
                    'trend_title' => $trend['title'],
                    'post_id' => $post_id,
                    'status' => 'success',
                    'created_at' => current_time('mysql'),
                    'error_message' => $success_message
                )
            );
            
            return array(
                'success' => true,
                'message' => $success_message,
                'post_id' => $post_id,
                'image_results' => $image_result,
                'faq_generated' => $faq_generated
            );
            
        } catch (Exception $e) {
            $this->log_error($trend['title'], $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Process images for the post using enhanced integration
     * 
     * @param int $post_id WordPress post ID
     * @param array $content Generated content with image suggestions
     * @param array $trend Original trend data
     * @param string $post_title Post title for better image matching
     * @return array Image processing results
     */
    private function process_post_images($post_id, $content, $trend, $post_title = '') {
        if (!get_option('tab_enable_featured_images')) {
            return array(
                'success' => false,
                'message' => __('Image processing disabled', 'trendie-auto-blogger'),
                'updated_content' => ''
            );
        }
        
        // Check if we have image suggestions from Gemini
        if (!empty($content['image_suggestions'])) {
            // Use Gemini's image suggestions with post title for better matching
            $image_result = $this->pexels_api->process_image_suggestions(
                $post_id, 
                $content['image_suggestions'], 
                $content['content'],
                $post_title ?: $trend['title']
            );
            
            if ($image_result['success']) {
                return array(
                    'success' => true,
                    'message' => implode(', ', $image_result['messages']),
                    'updated_content' => $image_result['updated_content'],
                    'featured_set' => $image_result['featured_set'],
                    'content_images' => $image_result['content_images']
                );
            }
        }
        
        // Fallback to legacy image setting if no suggestions or suggestions failed
        $legacy_result = $this->pexels_api->set_featured_image($post_id, $post_title ?: $trend['title']);
        
        if (!is_wp_error($legacy_result) && $legacy_result) {
            return array(
                'success' => true,
                'message' => __('Featured image set using legacy method', 'trendie-auto-blogger'),
                'updated_content' => '',
                'featured_set' => true,
                'content_images' => array()
            );
        }
        
        return array(
            'success' => false,
            'message' => is_wp_error($legacy_result) ? $legacy_result->get_error_message() : __('Failed to set any images', 'trendie-auto-blogger'),
            'updated_content' => '',
            'featured_set' => false,
            'content_images' => array()
        );
    }
    
    /**
     * Parse generated content into structured format
     * 
     * @param string $content Raw generated content
     * @return array Parsed content structure
     */
    private function parse_generated_content($content) {
        $parsed = array(
            'title' => '',
            'meta_description' => '',
            'content' => $content,
            'tags' => array(),
            'excerpt' => ''
        );
        
        // Split content into lines for better parsing
        $lines = explode("\n", $content);
        $content_lines = array();
        $found_title = false;
        
        // Process each line to extract title and content
        foreach ($lines as $index => $line) {
            $line = trim($line);
            
            // Skip empty lines at the beginning
            if (empty($line) && !$found_title) {
                continue;
            }
            
            // Extract title - look for the first meaningful line that's not an H2/H3 heading
            if (!$found_title && !empty($line)) {
                // Skip H2, H3 headings when looking for title
                if (!preg_match('/^H[2-6]:\s*/', $line) && 
                    !preg_match('/^#{2,}\s*/', $line) &&
                    !preg_match('/^\*\*H[2-6]/', $line)) {
                    
                    // Clean up potential title formatting
                    $potential_title = $line;
                    $potential_title = preg_replace('/^\*\*(.+?)\*\*$/', '$1', $potential_title); // Remove ** wrapping
                    $potential_title = preg_replace('/^#+\s*/', '', $potential_title); // Remove # headers
                    $potential_title = trim($potential_title);
                    
                    // Check if this looks like a title (reasonable length, not too short)
                    if (strlen($potential_title) > 10 && strlen($potential_title) < 200) {
                        $parsed['title'] = $potential_title;
                        $found_title = true;
                        continue; // Don't add this to content
                    }
                }
            }
            
            // Add to content if it's not the title
            if ($found_title || !empty($line)) {
                $content_lines[] = $line;
            }
        }
        
        // Rejoin content without the title
        $cleaned_content = implode("\n", $content_lines);
        
        // Extract meta description from first paragraph
        if (preg_match('/^([^\n]+(?:\n[^\n]+)*?)\n\n/', $cleaned_content, $matches)) {
            $first_paragraph = trim($matches[1]);
            // Clean up and use as meta description if reasonable length
            $first_paragraph = strip_tags($first_paragraph);
            $first_paragraph = preg_replace('/^H[2-6]:\s*/', '', $first_paragraph);
            if (strlen($first_paragraph) > 50 && strlen($first_paragraph) < 300) {
                $parsed['meta_description'] = substr($first_paragraph, 0, 157) . '...';
            }
        }
        
        // Extract tags from end of content (if in parentheses)
        if (preg_match('/\(([^)]+)\)\s*$/', $cleaned_content, $matches)) {
            $tags_string = trim($matches[1]);
            $parsed['tags'] = array_map('trim', explode(',', $tags_string));
            $parsed['tags'] = array_filter($parsed['tags']); // Remove empty tags
            // Remove tags from content
            $cleaned_content = preg_replace('/\s*\([^)]+\)\s*$/', '', $cleaned_content);
        }
        
        // Clean up content formatting
        $cleaned_content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $cleaned_content);
        $cleaned_content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $cleaned_content);
        $cleaned_content = preg_replace('/\n{3,}/', "\n\n", $cleaned_content);
        $cleaned_content = trim($cleaned_content);
        
        $parsed['content'] = $cleaned_content;
        
        // Fallback title extraction if still empty
        if (empty($parsed['title'])) {
            // Try to get from first H1 in content
            if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $cleaned_content, $matches)) {
                $parsed['title'] = trim(strip_tags($matches[1]));
            }
            // Note: If still empty, it will be set to trend title in create_wordpress_post function
        }
        
        // Generate meta description if still empty
        if (empty($parsed['meta_description']) && !empty($cleaned_content)) {
            $clean_text = strip_tags($cleaned_content);
            $clean_text = preg_replace('/^H[2-6]:\s*[^\n]+\n/', '', $clean_text); // Remove H2 lines
            $parsed['meta_description'] = substr(trim($clean_text), 0, 157) . '...';
        }
        
        // Generate excerpt
        if (!empty($parsed['meta_description'])) {
            $parsed['excerpt'] = $parsed['meta_description'];
        } else {
            $clean_content = strip_tags($cleaned_content);
            $parsed['excerpt'] = substr($clean_content, 0, 155) . '...';
        }
        
        return $parsed;
    }
    
    /**
     * Create WordPress post from optimized content
     * 
     * @param array $content Optimized content
     * @param array $trend Original trend data
     * @return int|WP_Error Post ID or error
     */
    private function create_wordpress_post($content, $trend) {
        $post_data = array(
            'post_title' => $content['title'] ?: $trend['title'],
            'post_content' => $content['content'],
            'post_status' => get_option('tab_post_status', 'draft'),
            'post_author' => get_current_user_id() ?: 1,
            'post_category' => array(get_option('tab_post_category', 1)),
            'post_excerpt' => $content['excerpt'],
            'meta_input' => array(
                '_yoast_wpseo_metadesc' => $content['meta_description'],
                '_yoast_wpseo_focuskw' => isset($content['focus_keyword']) ? $content['focus_keyword'] : '',
                'trendie_source_url' => $trend['source_url'],
                'trendie_trend_id' => $trend['id'],
                'trendie_seo_score' => isset($content['seo_score']) ? $content['seo_score'] : 0,
                'trendie_focus_keyword' => isset($content['focus_keyword']) ? $content['focus_keyword'] : ''
            )
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
                    // Add tags
            if (!empty($content['tags'])) {
                wp_set_post_tags($post_id, $content['tags']);
            }
            
            return $post_id;
    }
    
    /**
     * Create prompt for Gemini API (legacy method - kept for backward compatibility)
     * 
     * @param array $trend Trend data
     * @return string Generated prompt
     */
    private function create_prompt($trend) {
        $custom_prompt = get_option('tab_custom_prompt', '');
        
        if (empty($custom_prompt)) {
            $custom_prompt = "Write a comprehensive, SEO-optimized blog post about '{trend_title}'. Make it engaging, informative, and at least 1000 words long with proper headings and structure.";
        }
        
        // Replace placeholders
        $prompt = str_replace('{trend_title}', $trend['title'], $custom_prompt);
        
        // Add additional context if available
        if (!empty($trend['description'])) {
            $prompt .= "\n\nAdditional context: " . $trend['description'];
        }
        
        if (!empty($trend['related_topics'])) {
            $prompt .= "\n\nRelated topics to consider: " . implode(', ', $trend['related_topics']);
        }
        
        return $prompt;
    }
    
    /**
     * Log error to database
     * 
     * @param string $trend_title Trend title
     * @param string $error_message Error message
     */
    private function log_error($trend_title, $error_message) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trendie_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'trend_title' => $trend_title,
                'status' => 'failed',
                'error_message' => $error_message,
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Generate content with AI fallback support
     * 
     * @param string $trend_title Trend title
     * @param array $trend Trend data
     * @return array|WP_Error Generated content or error
     */
    private function generate_content_with_fallback($trend_title, $trend) {
        $ai_used = 'none';
        
        // Try Gemini API first
        $content_result = $this->gemini_api->generate_structured_content($trend_title, $trend);
        
        if (!is_wp_error($content_result)) {
            // Gemini succeeded
            $content_result['ai_used'] = 'gemini';
            return $content_result;
        }
        
        // Store Gemini error for logging
        $gemini_error = $content_result->get_error_message();
        
        // Check if OpenAI fallback is enabled
        $fallback_enabled = get_option('tab_ai_fallback_enabled', true);
        $openai_key = get_option('tab_openai_api_key', '');
        
        if ($fallback_enabled && !empty($openai_key)) {
            // Try OpenAI as fallback
            $content_result = $this->openai_api->generate_structured_content($trend_title, $trend);
            
            if (!is_wp_error($content_result)) {
                // OpenAI succeeded
                $content_result['ai_used'] = 'openai';
                $content_result['gemini_error'] = $gemini_error; // Store original error for logging
                return $content_result;
            }
            
            // Both APIs failed
            $openai_error = $content_result->get_error_message();
            return new WP_Error(
                'both_apis_failed', 
                sprintf(
                    __('Both AI services failed. Gemini: %s | OpenAI: %s', 'trendie-auto-blogger'),
                    $gemini_error,
                    $openai_error
                )
            );
        }
        
        // Fallback not enabled or no OpenAI key, return original Gemini error
        return new WP_Error('gemini_failed', $gemini_error);
    }
} 