<?php

class TAB_GeminiAPI {
    
    private $api_key;
    private $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    
    public function __construct() {
        $this->api_key = get_option('tab_gemini_api_key');
    }
    
    /**
     * Generate structured content using Gemini API
     * 
     * @param string $trend_title The trending topic title
     * @param array $trend_data Additional trend data
     * @return array|WP_Error Generated content or error
     */
    public function generate_structured_content($trend_title, $trend_data = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'trendie-auto-blogger'));
        }
        
        $prompt = $this->create_enhanced_prompt($trend_title, $trend_data);
        
        $request_data = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.8,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
                'stopSequences' => array()
            ),
            'safetySettings' => array(
                array(
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                )
            )
        );
        
        $response = wp_remote_post($this->api_url . '?key=' . $this->api_key, array(
            'body' => json_encode($request_data),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 90
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('api_request_failed', __('Failed to connect to Gemini API: ', 'trendie-auto-blogger') . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', sprintf(__('Gemini API returned error %d: %s', 'trendie-auto-blogger'), $response_code, $response_body));
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', __('Failed to decode API response', 'trendie-auto-blogger'));
        }
        
        if (empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            return new WP_Error('empty_response', __('Empty response from Gemini API', 'trendie-auto-blogger'));
        }
        
        $content = $data['candidates'][0]['content']['parts'][0]['text'];
        
        // Parse the structured JSON response
        $parsed_content = $this->parse_structured_response($content);
        
        if (is_wp_error($parsed_content)) {
            // Fallback to legacy parsing if structured response fails
            return array(
                'content' => $content,
                'raw_response' => $data,
                'structured' => false
            );
        }
        
        return array(
            'content' => $parsed_content,
            'raw_response' => $data,
            'structured' => true
        );
    }
    
    /**
     * Create enhanced prompt for structured content generation
     * 
     * @param string $trend_title The trending topic title
     * @param array $trend_data Additional trend data
     * @return string Enhanced prompt
     */
    private function create_enhanced_prompt($trend_title, $trend_data = array()) {
        $custom_prompt = get_option('tab_custom_prompt', '');
        
        if (!empty($custom_prompt)) {
            // Use custom prompt but ensure it includes JSON structure requirement
            $prompt = str_replace('{trend_title}', $trend_title, $custom_prompt);
            $prompt .= "\n\n" . $this->get_json_structure_instruction();
        } else {
            $prompt = $this->get_default_enhanced_prompt($trend_title, $trend_data);
        }
        
        return $prompt;
    }
    
    /**
     * Get default enhanced prompt with structured output
     * 
     * @param string $trend_title The trending topic title
     * @param array $trend_data Additional trend data
     * @return string Default enhanced prompt
     */
    private function get_default_enhanced_prompt($trend_title, $trend_data = array()) {
        $context = !empty($trend_data['description']) ? $trend_data['description'] : '';
        $related_topics = !empty($trend_data['related_topics']) ? implode(', ', $trend_data['related_topics']) : '';
        
        return "You are an expert SEO content writer and blogger. Create a comprehensive, engaging blog post about '{$trend_title}' that will rank well in search engines and provide real value to readers.

**CONTEXT:** {$context}
**RELATED TOPICS:** {$related_topics}

**REQUIREMENTS:**
1. Write a minimum of 1200 words
2. Use conversational, human-like tone
3. Include actionable insights and practical tips
4. Follow E-A-T guidelines (Expertise, Authoritativeness, Trustworthiness)
5. Optimize for SEO without keyword stuffing
6. Include relevant statistics and facts when possible
7. Structure content with clear headings (H2, H3)
8. Write engaging introduction and conclusion
9. Suggest relevant images for better engagement

**FORMATTING REQUIREMENTS:**
- Use ONLY proper HTML formatting (NOT markdown)
- Use <h2> and <h3> tags for headings
- Use <p> tags for paragraphs
- Use <strong> for bold text (NOT ** or *)
- Use <em> for italic text (NOT * or _)
- Use <ul> and <li> tags for bullet points (NOT * or -)
- Use proper HTML structure throughout

**IMPORTANT:** You must respond with a valid JSON object containing the following structure:

```json
{
    \"title\": \"SEO-optimized title (50-60 characters)\",
    \"meta_description\": \"Compelling meta description (150-160 characters)\",
    \"focus_keyword\": \"Primary keyword for SEO\",
    \"content\": \"Full blog post content with proper HTML formatting (NO MARKDOWN)\",
    \"excerpt\": \"Brief excerpt (150-160 characters)\",
    \"tags\": [\"tag1\", \"tag2\", \"tag3\", \"tag4\", \"tag5\"],
    \"image_suggestions\": [
        {
            \"placement\": \"featured\",
            \"search_query\": \"specific search terms for featured image\",
            \"alt_text\": \"SEO-optimized alt text\"
        },
        {
            \"placement\": \"content\",
            \"search_query\": \"specific search terms for content image\",
            \"alt_text\": \"SEO-optimized alt text\",
            \"caption\": \"Image caption text\"
        }
    ],
    \"seo_score\": 85,
    \"readability_score\": 78
}
```

**CONTENT STRUCTURE:**
- Engaging introduction (hook + preview of what readers will learn)
- 3-5 main sections with <h2> headings
- Subsections with <h3> headings where appropriate
- Bullet points using <ul><li> HTML tags (NOT asterisks)
- Strong conclusion with call-to-action
- Natural keyword integration throughout

**SEO OPTIMIZATION:**
- Primary keyword in title, first paragraph, and naturally throughout
- LSI keywords and semantic variations
- Internal linking opportunities (mention relevant topics)
- Proper heading hierarchy
- Meta description that encourages clicks

**CRITICAL FORMATTING RULE:** 
The content field must contain ONLY clean HTML. Do NOT use any markdown formatting like *, **, _, __, #, ##, or - for lists. Use proper HTML tags: <h2>, <h3>, <p>, <strong>, <em>, <ul>, <li>.

Generate the JSON response now:";
    }
    
    /**
     * Get JSON structure instruction for custom prompts
     * 
     * @return string JSON structure instruction
     */
    private function get_json_structure_instruction() {
        return "IMPORTANT: Respond with a valid JSON object containing: title, meta_description, focus_keyword, content, excerpt, tags, image_suggestions (with placement, search_query, alt_text), seo_score, and readability_score.";
    }
    
    /**
     * Parse structured JSON response from Gemini
     * 
     * @param string $content Raw response content
     * @return array|WP_Error Parsed content or error
     */
    private function parse_structured_response($content) {
        // Extract JSON from the response (handle cases where it's wrapped in markdown)
        $json_content = $this->extract_json_from_response($content);
        
        if (empty($json_content)) {
            return new WP_Error('no_json_found', __('No valid JSON found in response', 'trendie-auto-blogger'));
        }
        
        $parsed = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_parse_error', __('Failed to parse JSON response: ', 'trendie-auto-blogger') . json_last_error_msg());
        }
        
        // Validate required fields
        $required_fields = array('title', 'content', 'meta_description', 'focus_keyword');
        foreach ($required_fields as $field) {
            if (empty($parsed[$field])) {
                return new WP_Error('missing_field', sprintf(__('Missing required field: %s', 'trendie-auto-blogger'), $field));
            }
        }
        
        // Clean and convert content from markdown to HTML
        $parsed['content'] = $this->convert_markdown_to_html($parsed['content']);
        $parsed['title'] = $this->clean_markdown_formatting($parsed['title']);
        $parsed['meta_description'] = $this->clean_markdown_formatting($parsed['meta_description']);
        
        // Set defaults for optional fields
        $parsed['tags'] = isset($parsed['tags']) ? $parsed['tags'] : array();
        $parsed['excerpt'] = isset($parsed['excerpt']) ? $this->clean_markdown_formatting($parsed['excerpt']) : substr(strip_tags($parsed['content']), 0, 155) . '...';
        $parsed['image_suggestions'] = isset($parsed['image_suggestions']) ? $parsed['image_suggestions'] : array();
        $parsed['seo_score'] = isset($parsed['seo_score']) ? intval($parsed['seo_score']) : 0;
        $parsed['readability_score'] = isset($parsed['readability_score']) ? intval($parsed['readability_score']) : 0;
        
        return $parsed;
    }
    
    /**
     * Convert markdown formatting to clean HTML
     * 
     * @param string $content Content with markdown formatting
     * @return string Clean HTML content
     */
    private function convert_markdown_to_html($content) {
        // Remove extra whitespace and normalize line breaks
        $content = trim($content);
        $content = preg_replace('/\r\n|\r/', "\n", $content);
        
        // Convert markdown headers
        $content = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $content);
        $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $content);
        
        // Convert ALL CAPS headers to proper H2 tags
        $content = preg_replace('/^([A-Z][A-Z\s:?]+)$/m', '<h2>$1</h2>', $content);
        
        // Convert bold text - both **text** and *text* to <strong>
        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/(?<!\*)\*([^*]+?)\*(?!\*)/', '<em>$1</em>', $content);
        
        // Convert italic text in book titles and special cases
        $content = preg_replace('/\*([^*]+?)\*/', '<em>$1</em>', $content);
        
        // Convert markdown lists to HTML
        $content = $this->convert_markdown_lists($content);
        
        // Convert line breaks to paragraphs
        $content = $this->convert_line_breaks_to_paragraphs($content);
        
        // Clean up any remaining formatting issues
        $content = $this->clean_up_html($content);
        
        return $content;
    }
    
    /**
     * Convert markdown lists to HTML lists
     * 
     * @param string $content Content with markdown lists
     * @return string Content with HTML lists
     */
    private function convert_markdown_lists($content) {
        // Split content into lines
        $lines = explode("\n", $content);
        $result = array();
        $in_list = false;
        $list_items = array();
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Check if this is a list item (starts with * or -)
            if (preg_match('/^\*\s+(.+)$/', $trimmed, $matches) || preg_match('/^-\s+(.+)$/', $trimmed, $matches)) {
                if (!$in_list) {
                    $in_list = true;
                    $list_items = array();
                }
                $list_items[] = trim($matches[1]);
            } else {
                // Not a list item
                if ($in_list) {
                    // End the current list
                    $result[] = '<ul>';
                    foreach ($list_items as $item) {
                        $result[] = '<li>' . $item . '</li>';
                    }
                    $result[] = '</ul>';
                    $in_list = false;
                    $list_items = array();
                }
                
                // Add the non-list line
                if (!empty($trimmed)) {
                    $result[] = $line;
                }
            }
        }
        
        // Handle case where content ends with a list
        if ($in_list) {
            $result[] = '<ul>';
            foreach ($list_items as $item) {
                $result[] = '<li>' . $item . '</li>';
            }
            $result[] = '</ul>';
        }
        
        return implode("\n", $result);
    }
    
    /**
     * Convert line breaks to proper paragraphs
     * 
     * @param string $content Content with line breaks
     * @return string Content with proper paragraphs
     */
    private function convert_line_breaks_to_paragraphs($content) {
        // Split content by double line breaks (paragraph breaks)
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $result = array();
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            
            if (empty($paragraph)) {
                continue;
            }
            
            // Skip if it's already an HTML tag (headers, lists, etc.)
            if (preg_match('/^<(h[1-6]|ul|ol|li|div|figure)/', $paragraph)) {
                $result[] = $paragraph;
            } else {
                // Wrap in paragraph tags
                $result[] = '<p>' . $paragraph . '</p>';
            }
        }
        
        return implode("\n\n", $result);
    }
    
    /**
     * Clean up HTML formatting issues
     * 
     * @param string $content HTML content
     * @return string Cleaned HTML content
     */
    private function clean_up_html($content) {
        // Remove empty paragraphs
        $content = preg_replace('/<p>\s*<\/p>/', '', $content);
        
        // Remove paragraphs that only contain HTML tags
        $content = preg_replace('/<p>(\s*<[^>]+>\s*)<\/p>/', '$1', $content);
        
        // Fix nested formatting issues
        $content = preg_replace('/<p>\s*(<h[1-6][^>]*>.*?<\/h[1-6]>)\s*<\/p>/', '$1', $content);
        $content = preg_replace('/<p>\s*(<ul>.*?<\/ul>)\s*<\/p>/s', '$1', $content);
        $content = preg_replace('/<p>\s*(<ol>.*?<\/ol>)\s*<\/p>/s', '$1', $content);
        
        // Clean up extra whitespace
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Clean markdown formatting from text (for titles, meta descriptions, etc.)
     * 
     * @param string $text Text with potential markdown
     * @return string Clean text
     */
    private function clean_markdown_formatting($text) {
        // Remove markdown formatting
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/\*(.+?)\*/', '$1', $text);
        $text = preg_replace('/_{2}(.+?)_{2}/', '$1', $text);
        $text = preg_replace('/_(.+?)_/', '$1', $text);
        $text = preg_replace('/#{1,6}\s*/', '', $text);
        
        return trim($text);
    }
    
    /**
     * Extract JSON from response content
     * 
     * @param string $content Response content
     * @return string|null JSON string or null if not found
     */
    private function extract_json_from_response($content) {
        // Try to find JSON wrapped in code blocks
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $content, $matches)) {
            return $matches[1];
        }
        
        // Try to find JSON wrapped in regular code blocks
        if (preg_match('/```\s*(\{.*?\})\s*```/s', $content, $matches)) {
            return $matches[1];
        }
        
        // Try to find raw JSON
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $content, $matches)) {
            return $matches[0];
        }
        
        return null;
    }
    
    /**
     * Generate content using legacy method (for backward compatibility)
     * 
     * @param string $prompt The prompt to send to Gemini
     * @return array|WP_Error Generated content or error
     */
    public function generate_content($prompt) {
        // For backward compatibility, use the trend title if it looks like a trend
        if (strlen($prompt) < 200 && !strpos($prompt, 'JSON')) {
            return $this->generate_structured_content($prompt);
        }
        
        // Legacy implementation for custom prompts
        return $this->generate_legacy_content($prompt);
    }
    
    /**
     * Generate content using legacy method
     * 
     * @param string $prompt The prompt to send to Gemini
     * @return array|WP_Error Generated content or error
     */
    private function generate_legacy_content($prompt) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'trendie-auto-blogger'));
        }
        
        $request_data = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
                'stopSequences' => array()
            ),
            'safetySettings' => array(
                array(
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                )
            )
        );
        
        $response = wp_remote_post($this->api_url . '?key=' . $this->api_key, array(
            'body' => json_encode($request_data),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('api_request_failed', __('Failed to connect to Gemini API: ', 'trendie-auto-blogger') . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', sprintf(__('Gemini API returned error %d: %s', 'trendie-auto-blogger'), $response_code, $response_body));
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', __('Failed to decode API response', 'trendie-auto-blogger'));
        }
        
        if (empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            return new WP_Error('empty_response', __('Empty response from Gemini API', 'trendie-auto-blogger'));
        }
        
        return array(
            'content' => $data['candidates'][0]['content']['parts'][0]['text'],
            'raw_response' => $data
        );
    }
    
    /**
     * Test connection to Gemini API
     * 
     * @return array Success or error response
     */
    public function test_connection() {
        $test_prompt = "Write a brief test response to confirm API connectivity.";
        
        $result = $this->generate_legacy_content($test_prompt);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Gemini API connection successful!', 'trendie-auto-blogger'),
            'test_response' => substr($result['content'], 0, 100) . '...'
        );
    }
} 