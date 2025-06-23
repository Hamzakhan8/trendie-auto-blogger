<?php

class TAB_PexelsAPI {
    
    private $api_key;
    private $api_url = 'https://api.pexels.com/v1/search';
    
    public function __construct() {
        $this->api_key = get_option('tab_pexels_api_key');
    }
    
    /**
     * Search for images on Pexels with enhanced search
     * 
     * @param string $query Search query
     * @param int $per_page Number of results per page (default 15, max 80)
     * @param string $orientation Image orientation (landscape, portrait, square)
     * @param array $additional_params Additional search parameters
     * @return array|WP_Error Array of images or error
     */
    public function search_images($query, $per_page = 15, $orientation = 'landscape', $additional_params = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Pexels API key not configured', 'trendie-auto-blogger'));
        }
        
        // Clean and prepare search query
        $query = $this->prepare_search_query($query);
        
        $args = array(
            'query' => $query,
            'per_page' => min($per_page, 80),
            'orientation' => $orientation,
            'size' => 'medium'
        );
        
        // Add additional parameters
        if (!empty($additional_params['color'])) {
            $args['color'] = $additional_params['color'];
        }
        
        if (!empty($additional_params['locale'])) {
            $args['locale'] = $additional_params['locale'];
        }
        
        $url = add_query_arg($args, $this->api_url);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => $this->api_key,
                'User-Agent' => 'Trendie Auto Blogger Plugin/1.0'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('api_request_failed', __('Failed to connect to Pexels API: ', 'trendie-auto-blogger') . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', sprintf(__('Pexels API returned error %d: %s', 'trendie-auto-blogger'), $response_code, $response_body));
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', __('Failed to decode Pexels API response', 'trendie-auto-blogger'));
        }
        
        if (empty($data['photos'])) {
            return new WP_Error('no_images_found', __('No images found for the search query', 'trendie-auto-blogger'));
        }
        
        return $data['photos'];
    }
    
    /**
     * Process image suggestions from Gemini and set images for post
     * 
     * @param int $post_id WordPress post ID
     * @param array $image_suggestions Image suggestions from Gemini
     * @param string $post_content Post content for image insertion
     * @param string $post_title Post title for better image matching
     * @return array Result of image processing
     */
    public function process_image_suggestions($post_id, $image_suggestions, $post_content = '', $post_title = '') {
        if (!get_option('tab_enable_featured_images')) {
            return array(
                'success' => false,
                'message' => __('Featured images are disabled', 'trendie-auto-blogger')
            );
        }
        
        // Get post title if not provided
        if (empty($post_title)) {
            $post_title = get_the_title($post_id);
        }
        
        $results = array(
            'featured_set' => false,
            'content_images' => array(),
            'messages' => array(),
            'updated_content' => $post_content
        );
        
        if (empty($image_suggestions)) {
            // Fallback: use post title for image search
            $results['messages'][] = __('No AI suggestions provided, using post title for image search', 'trendie-auto-blogger');
            return $this->set_images_from_title($post_id, $post_title, $post_content);
        }
        
        foreach ($image_suggestions as $suggestion) {
            if (empty($suggestion['search_query']) || empty($suggestion['placement'])) {
                continue;
            }
            
            $placement = $suggestion['placement'];
            $search_query = $suggestion['search_query'];
            
            // Enhance search query with post title keywords for better relevance
            $enhanced_query = $this->enhance_search_query($search_query, $post_title);
            $alt_text = isset($suggestion['alt_text']) ? $suggestion['alt_text'] : $search_query;
            
            // Get image orientation preference
            $orientation = get_option('tab_image_orientation', 'landscape');
            
            // Search for images with enhanced query
            $images = $this->search_images($enhanced_query, 10, $orientation);
            
            // If enhanced query fails, try original query
            if (is_wp_error($images)) {
                $images = $this->search_images($search_query, 10, $orientation);
            }
            
            // If both fail, try using just post title keywords
            if (is_wp_error($images)) {
                $title_keywords = $this->extract_keywords_from_title($post_title);
                $images = $this->search_images($title_keywords, 10, $orientation);
            }
            
            if (is_wp_error($images)) {
                $results['messages'][] = sprintf(__('Failed to find images for "%s": %s', 'trendie-auto-blogger'), $search_query, $images->get_error_message());
                continue;
            }
            
            // Process based on placement
            if ($placement === 'featured' && !$results['featured_set']) {
                $featured_result = $this->set_featured_image_from_suggestions($post_id, $images, $alt_text);
                if ($featured_result['success']) {
                    $results['featured_set'] = true;
                    $results['messages'][] = sprintf(__('Featured image set for "%s"', 'trendie-auto-blogger'), $enhanced_query);
                } else {
                    $results['messages'][] = $featured_result['message'];
                }
            } elseif ($placement === 'content') {
                $content_result = $this->add_content_image($post_id, $images, $suggestion, $results['updated_content']);
                if ($content_result['success']) {
                    $results['content_images'][] = $content_result['attachment_id'];
                    $results['updated_content'] = $content_result['updated_content'];
                    $results['messages'][] = sprintf(__('Content image added for "%s"', 'trendie-auto-blogger'), $enhanced_query);
                } else {
                    $results['messages'][] = $content_result['message'];
                }
            }
        }
        
        // If no featured image was set, try using post title as fallback
        if (!$results['featured_set']) {
            $fallback_result = $this->set_featured_image($post_id, $post_title);
            if (!is_wp_error($fallback_result) && $fallback_result) {
                $results['featured_set'] = true;
                $results['messages'][] = __('Featured image set using post title fallback', 'trendie-auto-blogger');
            }
        }
        
        $results['success'] = $results['featured_set'] || !empty($results['content_images']);
        
        return $results;
    }
    
    /**
     * Set images based on post title when AI suggestions are not available
     * 
     * @param int $post_id WordPress post ID  
     * @param string $post_title Post title
     * @param string $post_content Post content
     * @return array Result of image processing
     */
    private function set_images_from_title($post_id, $post_title, $post_content) {
        $results = array(
            'featured_set' => false,
            'content_images' => array(),
            'messages' => array(),
            'updated_content' => $post_content
        );
        
        // Extract keywords from title for better search
        $search_query = $this->extract_keywords_from_title($post_title);
        $orientation = get_option('tab_image_orientation', 'landscape');
        
        // Search for images
        $images = $this->search_images($search_query, 15, $orientation);
        
        if (is_wp_error($images)) {
            // Try with simplified title
            $simplified_title = $this->simplify_title_for_search($post_title);
            $images = $this->search_images($simplified_title, 15, $orientation);
        }
        
        if (is_wp_error($images)) {
            $results['messages'][] = sprintf(__('Failed to find images for title "%s": %s', 'trendie-auto-blogger'), $post_title, $images->get_error_message());
            return $results;
        }
        
        // Set featured image
        $featured_result = $this->set_featured_image_from_suggestions($post_id, $images, $post_title);
        if ($featured_result['success']) {
            $results['featured_set'] = true;
            $results['messages'][] = __('Featured image set from post title', 'trendie-auto-blogger');
        }
        
        // Optionally add content image if enabled
        if (get_option('tab_enable_content_images')) {
            $content_suggestion = array(
                'search_query' => $search_query,
                'alt_text' => $post_title,
                'caption' => ''
            );
            
            $content_result = $this->add_content_image($post_id, $images, $content_suggestion, $post_content);
            if ($content_result['success']) {
                $results['content_images'][] = $content_result['attachment_id'];
                $results['updated_content'] = $content_result['updated_content'];
                $results['messages'][] = __('Content image added from post title', 'trendie-auto-blogger');
            }
        }
        
        $results['success'] = $results['featured_set'] || !empty($results['content_images']);
        return $results;
    }
    
    /**
     * Enhance search query by combining AI suggestion with post title keywords
     * 
     * @param string $ai_suggestion AI-generated search query
     * @param string $post_title Post title
     * @return string Enhanced search query
     */
    private function enhance_search_query($ai_suggestion, $post_title) {
        $title_keywords = $this->extract_keywords_from_title($post_title);
        
        // Combine AI suggestion with key title words
        $combined_keywords = array_unique(array_merge(
            explode(' ', $ai_suggestion),
            explode(' ', $title_keywords)
        ));
        
        // Limit to most relevant keywords (first 4-5 words)
        $relevant_keywords = array_slice($combined_keywords, 0, 5);
        
        return implode(' ', $relevant_keywords);
    }
    
    /**
     * Extract relevant keywords from post title for image search
     * 
     * @param string $title Post title
     * @return string Extracted keywords
     */
    private function extract_keywords_from_title($title) {
        // Remove common words and extract meaningful keywords
        $title = strtolower($title);
        
        // Remove punctuation and special characters
        $title = preg_replace('/[^\w\s]/', ' ', $title);
        
        // Split into words
        $words = explode(' ', $title);
        
        // Remove stop words and short words
        $stop_words = array(
            'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'how', 'what', 'why', 'when', 'where', 'who', 'which', 'this', 'that', 'these',
            'those', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have',
            'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may',
            'might', 'must', 'can', 'about', 'into', 'through', 'during', 'before', 'after',
            'above', 'below', 'up', 'down', 'out', 'off', 'over', 'under', 'again', 'further',
            'then', 'once', 'here', 'there', 'all', 'any', 'both', 'each', 'few', 'more',
            'most', 'other', 'some', 'such', 'only', 'own', 'same', 'so', 'than', 'too',
            'very', 'just', 'now'
        );
        
        $keywords = array_filter($words, function($word) use ($stop_words) {
            return !in_array($word, $stop_words) && strlen($word) > 2;
        });
        
        // Take the first 4 most relevant keywords
        $keywords = array_slice($keywords, 0, 4);
        
        return implode(' ', $keywords);
    }
    
    /**
     * Simplify title for broader image search
     * 
     * @param string $title Post title
     * @return string Simplified title
     */
    private function simplify_title_for_search($title) {
        // Extract just the main subject/topic
        $keywords = $this->extract_keywords_from_title($title);
        $words = explode(' ', $keywords);
        
        // Return just the first 2 most important words
        return implode(' ', array_slice($words, 0, 2));
    }
    
    /**
     * Set featured image from search results
     * 
     * @param int $post_id WordPress post ID
     * @param array $images Array of images from Pexels
     * @param string $alt_text Alt text for the image
     * @return array Result of setting featured image
     */
    private function set_featured_image_from_suggestions($post_id, $images, $alt_text) {
        foreach ($images as $image) {
            $attachment_id = $this->download_and_attach_image($image, $post_id, $alt_text);
            
            if (!is_wp_error($attachment_id)) {
                $result = set_post_thumbnail($post_id, $attachment_id);
                if ($result) {
                    return array(
                        'success' => true,
                        'attachment_id' => $attachment_id,
                        'message' => __('Featured image set successfully', 'trendie-auto-blogger')
                    );
                }
            }
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to set featured image', 'trendie-auto-blogger')
        );
    }
    
    /**
     * Add image to post content
     * 
     * @param int $post_id WordPress post ID
     * @param array $images Array of images from Pexels
     * @param array $suggestion Image suggestion with placement info
     * @param string $content Current post content
     * @return array Result of adding content image
     */
    private function add_content_image($post_id, $images, $suggestion, $content) {
        $best_image = $this->select_best_image($images);
        if (!$best_image) {
            return array(
                'success' => false,
                'message' => __('No suitable image found', 'trendie-auto-blogger')
            );
        }
        
        $alt_text = isset($suggestion['alt_text']) ? $suggestion['alt_text'] : $suggestion['search_query'];
        $attachment_id = $this->download_and_attach_image($best_image, $post_id, $alt_text);
        
        if (is_wp_error($attachment_id)) {
            return array(
                'success' => false,
                'message' => $attachment_id->get_error_message()
            );
        }
        
        // Get image URL and create img tag
        $image_url = wp_get_attachment_image_url($attachment_id, 'large');
        $caption = isset($suggestion['caption']) ? $suggestion['caption'] : '';
        
        $img_html = $this->create_content_image_html($image_url, $alt_text, $caption, $best_image);
        
        // Insert image into content
        $updated_content = $this->insert_image_into_content($content, $img_html);
        
        return array(
            'success' => true,
            'attachment_id' => $attachment_id,
            'updated_content' => $updated_content,
            'message' => __('Content image added successfully', 'trendie-auto-blogger')
        );
    }
    
    /**
     * Select the best image from search results
     * 
     * @param array $images Array of images
     * @return array|null Best image or null
     */
    private function select_best_image($images) {
        if (empty($images)) {
            return null;
        }
        
        // For now, return the first image
        // In the future, we could implement scoring based on quality, resolution, etc.
        return $images[0];
    }
    
    /**
     * Create HTML for content image
     * 
     * @param string $image_url Image URL
     * @param string $alt_text Alt text
     * @param string $caption Caption text
     * @param array $image_data Original image data from Pexels
     * @return string HTML for image
     */
    private function create_content_image_html($image_url, $alt_text, $caption, $image_data) {
        $credit = sprintf(__('Photo by %s on Pexels', 'trendie-auto-blogger'), $image_data['photographer']);
        
        $html = '<figure class="wp-block-image aligncenter size-large">';
        $html .= sprintf('<img src="%s" alt="%s" class="wp-image" loading="lazy" />', 
                        esc_url($image_url), 
                        esc_attr($alt_text));
        
        if (!empty($caption)) {
            $html .= sprintf('<figcaption class="wp-element-caption">%s <em>%s</em></figcaption>', 
                            esc_html($caption), 
                            esc_html($credit));
        } else {
            $html .= sprintf('<figcaption class="wp-element-caption"><em>%s</em></figcaption>', 
                            esc_html($credit));
        }
        
        $html .= '</figure>';
        
        return $html;
    }
    
    /**
     * Insert image into content at appropriate location
     * 
     * @param string $content Original content
     * @param string $img_html Image HTML
     * @return string Updated content
     */
    private function insert_image_into_content($content, $img_html) {
        // Find a good place to insert the image (after first or second paragraph)
        $paragraphs = explode("\n\n", $content);
        
        if (count($paragraphs) >= 3) {
            // Insert after second paragraph
            array_splice($paragraphs, 2, 0, $img_html);
        } elseif (count($paragraphs) >= 2) {
            // Insert after first paragraph
            array_splice($paragraphs, 1, 0, $img_html);
        } else {
            // Append at the end
            $paragraphs[] = $img_html;
        }
        
        return implode("\n\n", $paragraphs);
    }
    
    /**
     * Download and set featured image for a post (legacy method)
     * 
     * @param int $post_id WordPress post ID
     * @param string $trend_title Trend title for search
     * @return bool|WP_Error True on success, error on failure
     */
    public function set_featured_image($post_id, $trend_title) {
        if (!get_option('tab_enable_featured_images')) {
            return false;
        }
        
        $orientation = get_option('tab_image_orientation', 'landscape');
        
        // Search for images
        $images = $this->search_images($trend_title, 10, $orientation);
        
        if (is_wp_error($images)) {
            return $images;
        }
        
        // Try to download and set the first suitable image
        foreach ($images as $image) {
            $attachment_id = $this->download_and_attach_image($image, $post_id, $trend_title);
            
            if (!is_wp_error($attachment_id)) {
                // Set as featured image
                $result = set_post_thumbnail($post_id, $attachment_id);
                if ($result) {
                    return true;
                }
            }
        }
        
        return new WP_Error('failed_to_set_image', __('Failed to download and set featured image', 'trendie-auto-blogger'));
    }
    
    /**
     * Download image and attach to WordPress media library
     * 
     * @param array $image Image data from Pexels API
     * @param int $post_id WordPress post ID
     * @param string $alt_text Alt text for the image
     * @return int|WP_Error Attachment ID or error
     */
    private function download_and_attach_image($image, $post_id, $alt_text) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Use medium size image for better performance
        $image_url = $image['src']['medium'] ?? $image['src']['large'] ?? $image['src']['original'];
        
        if (empty($image_url)) {
            return new WP_Error('invalid_image_url', __('Invalid image URL from Pexels', 'trendie-auto-blogger'));
        }
        
        // Download image
        $temp_file = download_url($image_url);
        
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }
        
        // Prepare file array
        $file_array = array(
            'name' => $this->generate_filename($alt_text, $image['id']),
            'tmp_name' => $temp_file
        );
        
        // Upload to media library
        $attachment_id = media_handle_sideload($file_array, $post_id, $alt_text);
        
        // Clean up temp file
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // Set image metadata
        $this->set_image_metadata($attachment_id, $image, $alt_text);
        
        return $attachment_id;
    }
    
    /**
     * Set metadata for the uploaded image
     * 
     * @param int $attachment_id WordPress attachment ID
     * @param array $image Image data from Pexels
     * @param string $alt_text Alt text for the image
     */
    private function set_image_metadata($attachment_id, $image, $alt_text) {
        // Set alt text for SEO
        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
        
        // Set caption with photographer credit
        $caption = sprintf(__('Photo by %s on Pexels', 'trendie-auto-blogger'), $image['photographer']);
        wp_update_post(array(
            'ID' => $attachment_id,
            'post_excerpt' => $caption
        ));
        
        // Store Pexels metadata
        update_post_meta($attachment_id, '_pexels_id', $image['id']);
        update_post_meta($attachment_id, '_pexels_photographer', $image['photographer']);
        update_post_meta($attachment_id, '_pexels_url', $image['url']);
    }
    
    /**
     * Prepare search query for better results
     * 
     * @param string $query Original search query
     * @return string Cleaned search query
     */
    private function prepare_search_query($query) {
        // Remove special characters and clean up
        $query = preg_replace('/[^\w\s]/', '', $query);
        $query = trim($query);
        
        // Business/tech keyword mapping for better image results
        $keyword_mapping = array(
            'AI' => 'artificial intelligence technology',
            'crypto' => 'cryptocurrency bitcoin blockchain',
            'cryptocurrency' => 'bitcoin blockchain digital currency',
            'blockchain' => 'cryptocurrency bitcoin network',
            'stock' => 'stock market trading finance',
            'market' => 'financial market business',
            'startup' => 'business entrepreneur office',
            'tech' => 'technology computer software',
            'fintech' => 'financial technology mobile banking',
            'IPO' => 'stock market business finance',
            'merger' => 'business corporate handshake',
            'venture capital' => 'business investment startup',
            'economy' => 'business finance money market',
            'investment' => 'finance money business growth',
            'trading' => 'financial market computer charts'
        );
        
        // Check if query contains any mappable keywords
        $lower_query = strtolower($query);
        foreach ($keyword_mapping as $keyword => $replacement) {
            if (strpos($lower_query, strtolower($keyword)) !== false) {
                // Replace or enhance with more visual terms
                $query = str_ireplace($keyword, $replacement, $query);
                break;
            }
        }
        
        // If query is too specific, try to extract main keywords
        $words = explode(' ', $query);
        if (count($words) > 4) {
            // Take first 4 words for broader search
            $query = implode(' ', array_slice($words, 0, 4));
        }
        
        // Remove common stop words that don't help with image search
        $stop_words = array('the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'how', 'what', 'why', 'when', 'where', 'who', 'which', 'this', 'that', 'these', 'those', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can');
        
        $words = array_filter(explode(' ', $query), function($word) use ($stop_words) {
            return !in_array(strtolower($word), $stop_words) && strlen($word) > 2;
        });
        
        // Add generic business/professional terms for better results
        $enhanced_words = array_slice($words, 0, 3);
        $enhanced_words[] = 'business'; // Add business context for professional images
        
        return implode(' ', array_unique($enhanced_words));
    }
    
    /**
     * Generate filename for downloaded image
     * 
     * @param string $alt_text Alt text to base filename on
     * @param int $image_id Pexels image ID
     * @return string Generated filename
     */
    private function generate_filename($alt_text, $image_id) {
        $filename = sanitize_file_name($alt_text);
        $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $filename);
        $filename = preg_replace('/-+/', '-', $filename);
        $filename = trim($filename, '-');
        
        if (empty($filename)) {
            $filename = 'trendie-image';
        }
        
        return $filename . '-' . $image_id . '.jpg';
    }
    
    /**
     * Test connection to Pexels API
     * 
     * @return array Success or error response
     */
    public function test_connection() {
        $test_query = "nature";
        
        $result = $this->search_images($test_query, 1);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Pexels API connection successful!', 'trendie-auto-blogger'),
            'test_images_found' => count($result)
        );
    }
} 