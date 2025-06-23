<?php

class TAB_OpenAIAPI {
    
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    
    public function __construct() {
        $this->api_key = get_option('tab_openai_api_key');
    }
    
    /**
     * Generate structured content using OpenAI API
     * 
     * @param string $trend_title The trending topic title
     * @param array $trend_data Additional trend data
     * @return array|WP_Error Generated content or error
     */
    public function generate_structured_content($trend_title, $trend_data = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured', 'trendie-auto-blogger'));
        }
        
        $prompt = $this->create_enhanced_prompt($trend_title, $trend_data);
        
        $request_data = array(
            'model' => get_option('tab_openai_model', 'gpt-4-turbo-preview'),
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are an expert SEO content writer and blogger. Always respond with valid JSON format as specified in the user prompt.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 4000,
            'temperature' => 0.8,
            'top_p' => 0.95,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0
        );
        
        $response = wp_remote_post($this->api_url, array(
            'body' => json_encode($request_data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'User-Agent' => 'Trendie Auto Blogger Plugin/1.0'
            ),
            'timeout' => 90
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('api_request_failed', __('Failed to connect to OpenAI API: ', 'trendie-auto-blogger') . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', sprintf(__('OpenAI API returned error %d: %s', 'trendie-auto-blogger'), $response_code, $response_body));
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', __('Failed to decode OpenAI API response', 'trendie-auto-blogger'));
        }
        
        if (empty($data['choices'][0]['message']['content'])) {
            return new WP_Error('empty_response', __('Empty response from OpenAI API', 'trendie-auto-blogger'));
        }
        
        $content = $data['choices'][0]['message']['content'];
        
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
        
        return "Create a comprehensive, engaging blog post about '{$trend_title}' that will rank well in search engines and provide real value to readers.

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

RESPOND ONLY WITH THE JSON OBJECT. NO OTHER TEXT.";
    }
    
    /**
     * Get JSON structure instruction
     * 
     * @return string JSON structure instruction
     */
    private function get_json_structure_instruction() {
        return "IMPORTANT: You must respond with a valid JSON object. Do not include any text before or after the JSON. Start your response with { and end with }.";
    }
    
    /**
     * Parse structured response from OpenAI
     * 
     * @param string $content Response content
     * @return array|WP_Error Parsed content or error
     */
    private function parse_structured_response($content) {
        // Extract JSON from response
        $json_content = $this->extract_json_from_response($content);
        
        if (empty($json_content)) {
            return new WP_Error('no_json_found', __('No JSON structure found in OpenAI response', 'trendie-auto-blogger'));
        }
        
        $decoded = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_parse_error', __('Failed to parse JSON response from OpenAI: ', 'trendie-auto-blogger') . json_last_error_msg());
        }
        
        // Validate required fields
        $required_fields = array('title', 'content');
        foreach ($required_fields as $field) {
            if (empty($decoded[$field])) {
                return new WP_Error('missing_field', sprintf(__('Missing required field "%s" in OpenAI response', 'trendie-auto-blogger'), $field));
            }
        }
        
        // Clean and process content
        if (!empty($decoded['content'])) {
            $decoded['content'] = $this->clean_up_html($decoded['content']);
        }
        
        return $decoded;
    }
    
    /**
     * Clean up HTML content
     * 
     * @param string $content HTML content
     * @return string Cleaned content
     */
    private function clean_up_html($content) {
        // Remove any markdown-style formatting that might have slipped through
        $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
        
        // Ensure proper paragraph structure
        $content = $this->convert_line_breaks_to_paragraphs($content);
        
        // Clean up extra whitespace
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Convert line breaks to paragraphs
     * 
     * @param string $content Content with line breaks
     * @return string Content with proper paragraph tags
     */
    private function convert_line_breaks_to_paragraphs($content) {
        // Don't process if already has HTML structure
        if (strpos($content, '<p>') !== false) {
            return $content;
        }
        
        // Split by double line breaks
        $paragraphs = explode("\n\n", $content);
        $formatted_paragraphs = array();
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                // Skip if it's already an HTML element
                if (preg_match('/^<(h[1-6]|ul|ol|div|blockquote)/', $paragraph)) {
                    $formatted_paragraphs[] = $paragraph;
                } else {
                    $formatted_paragraphs[] = '<p>' . $paragraph . '</p>';
                }
            }
        }
        
        return implode("\n\n", $formatted_paragraphs);
    }
    
    /**
     * Extract JSON from response content
     * 
     * @param string $content Response content
     * @return string|null JSON string or null
     */
    private function extract_json_from_response($content) {
        // Remove any markdown code blocks
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);
        
        // Find JSON object boundaries
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        
        if ($start !== false && $end !== false && $end > $start) {
            return substr($content, $start, $end - $start + 1);
        }
        
        return null;
    }
    
    /**
     * Generate content using legacy method (for compatibility)
     * 
     * @param string $prompt Content generation prompt
     * @return array|WP_Error Generated content or error
     */
    public function generate_content($prompt) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured', 'trendie-auto-blogger'));
        }
        
        $request_data = array(
            'model' => get_option('tab_openai_model', 'gpt-4-turbo-preview'),
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are an expert SEO content writer and blogger.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 4000,
            'temperature' => 0.8
        );
        
        $response = wp_remote_post($this->api_url, array(
            'body' => json_encode($request_data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'timeout' => 90
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', sprintf(__('OpenAI API returned error %d: %s', 'trendie-auto-blogger'), $response_code, $response_body));
        }
        
        $data = json_decode($response_body, true);
        
        if (empty($data['choices'][0]['message']['content'])) {
            return new WP_Error('empty_response', __('Empty response from OpenAI API', 'trendie-auto-blogger'));
        }
        
        return array(
            'content' => $data['choices'][0]['message']['content'],
            'raw_response' => $data
        );
    }
    
    /**
     * Test connection to OpenAI API
     * 
     * @return array Success or error response
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('OpenAI API key not configured', 'trendie-auto-blogger')
            );
        }
        
        $test_prompt = "Write a short paragraph about artificial intelligence in business. Keep it under 100 words.";
        
        $result = $this->generate_content($test_prompt);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'message' => __('OpenAI API connection successful!', 'trendie-auto-blogger'),
            'test_content' => substr($result['content'], 0, 200) . '...'
        );
    }
} 