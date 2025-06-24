<?php

class TAB_RSSFetcher {
    
    private $rss_url;
    private $filter_keywords;
    
    public function __construct() {
        $this->rss_url = get_option('tab_rss_url', 'https://trends.google.com/trending/rss?geo=US&hl=en-US');
        
        // Get filter keywords from options or use defaults
        $this->filter_keywords = $this->get_filter_keywords();
    }
    
    /**
     * Get filter keywords from options or return defaults
     * 
     * @return array Filter keywords
     */
    private function get_filter_keywords() {
        $saved_keywords = get_option('tab_filter_keywords', '');
        
        if (!empty($saved_keywords)) {
            // Split saved keywords by comma and clean them
            $keywords = array_map('trim', explode(',', $saved_keywords));
            return array_filter($keywords); // Remove empty values
        }
        
        // Default keywords to filter trends - expanded to include more topics for better coverage
        return array(
            // Business & Finance
            'business', 'finance', 'financial', 'stock', 'stocks', 'market', 'markets', 
            'trading', 'investment', 'investing', 'investor', 'economy', 'economic',
            'revenue', 'profit', 'earnings', 'nasdaq', 'dow jones', 'sp500', 'forex',
            'banking', 'bank', 'payment', 'payments', 'ecommerce', 'IPO', 'merger', 'acquisition',
            
            // Technology & Innovation
            'AI', 'artificial intelligence', 'machine learning', 'crypto', 'cryptocurrency', 
            'bitcoin', 'blockchain', 'ethereum', 'technology', 'tech', 'startup', 
            'startups', 'entrepreneur', 'entrepreneurship', 'venture capital', 'VC',
            'fintech', 'digital', 'innovation', 'software', 'SaaS', 'app', 'platform',
            
            // Sports & Entertainment (popular on Google Trends)
            'NBA', 'basketball', 'football', 'sports', 'athlete', 'team', 'player', 'game',
            'entertainment', 'movie', 'film', 'TV', 'series', 'streaming', 'music', 'artist',
            'celebrity', 'actor', 'actress', 'singer', 'Hollywood', 'Netflix', 'Disney',
            
            // Health & Science
            'health', 'medical', 'healthcare', 'science', 'research', 'study', 'breakthrough',
            
            // News & Politics
            'news', 'politics', 'election', 'government', 'policy', 'law', 'court'
        );
    }
    
    /**
     * Fetch and parse RSS feed from Google Trends
     * 
     * @return array|WP_Error Array of trends or WP_Error on failure
     */
    public function fetch_trends() {
        try {
            // Fetch RSS feed
            $response = wp_remote_get($this->rss_url, array(
                'timeout' => 30,
                'user-agent' => 'Trendie Auto Blogger Plugin/1.0'
            ));
            
            if (is_wp_error($response)) {
                return new WP_Error('fetch_failed', __('Failed to fetch RSS feed: ', 'trendie-auto-blogger') . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return new WP_Error('fetch_failed', sprintf(__('RSS fetch returned HTTP %d', 'trendie-auto-blogger'), $response_code));
            }
            
            $rss_content = wp_remote_retrieve_body($response);
            
            if (empty($rss_content)) {
                return new WP_Error('empty_response', __('Empty RSS response received', 'trendie-auto-blogger'));
            }
            
            // Parse RSS XML
            $trends = $this->parse_rss_xml($rss_content);
            
            if (is_wp_error($trends)) {
                return $trends;
            }
            
            // Convert to JSON format
            return $this->convert_to_json_format($trends);
            
        } catch (Exception $e) {
            return new WP_Error('exception_error', __('Exception occurred: ', 'trendie-auto-blogger') . $e->getMessage());
        }
    }
    
    /**
     * Parse RSS XML content
     * 
     * @param string $xml_content RSS XML content
     * @return array|WP_Error Parsed trends array or WP_Error
     */
    private function parse_rss_xml($xml_content) {
        // Disable libxml errors to handle them manually
        libxml_use_internal_errors(true);
        
        $xml = simplexml_load_string($xml_content);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_message = 'XML parsing failed';
            
            if (!empty($errors)) {
                $error_message .= ': ' . $errors[0]->message;
            }
            
            return new WP_Error('xml_parse_error', $error_message);
        }
        
        $trends = array();
        
        // Get configurable time cutoff (default 24 hours, but can be extended)
        $cutoff_hours = intval(get_option('tab_trend_cutoff_hours', 72)); // Changed default to 72 hours
        $cutoff = strtotime("-{$cutoff_hours} hours");
        
        // Parse RSS items (Google Trends format)
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                // Check if item is within the specified time cutoff
                $pub_date = strtotime($item->pubDate);
                
                if ($pub_date >= $cutoff) {
                    // Google Trends uses a custom format with ht: namespace
                    $title = (string) $item->title;
                    $description = (string) $item->description;
                    
                    // Extract traffic data from ht:approx_traffic if available
                    $traffic = '';
                    if (isset($item->children('https://trends.google.com/trending/rss')->approx_traffic)) {
                        $traffic = (string) $item->children('https://trends.google.com/trending/rss')->approx_traffic;
                    }
                    
                    // Build description from news items if description is empty
                    if (empty($description)) {
                        $news_descriptions = array();
                        $ht_namespace = $item->children('https://trends.google.com/trending/rss');
                        
                        if (isset($ht_namespace->news_item)) {
                            foreach ($ht_namespace->news_item as $news_item) {
                                $news_title = (string) $news_item->news_item_title;
                                if (!empty($news_title)) {
                                    $news_descriptions[] = $news_title;
                                }
                            }
                        }
                        
                        if (!empty($news_descriptions)) {
                            $description = implode('. ', array_slice($news_descriptions, 0, 3));
                        } else {
                            $description = 'Trending topic: ' . $title;
                        }
                    }
                    
                    $trend = array(
                        'title' => $title,
                        'description' => $description,
                        'link' => (string) $item->link,
                        'pub_date' => (string) $item->pubDate,
                        'guid' => isset($item->guid) ? (string) $item->guid : md5($title . $pub_date),
                        'pub_timestamp' => $pub_date,
                        'traffic' => $traffic
                    );
                    
                    // Extract additional data from description if available
                    $description_data = $this->extract_description_data($description);
                    $trend = array_merge($trend, $description_data);
                    
                    $trends[] = $trend;
                }
            }
        }
        
        return $trends;
    }
    
    /**
     * Extract additional data from RSS item description
     * 
     * @param string $description HTML description content
     * @return array Extracted data
     */
    private function extract_description_data($description) {
        $data = array(
            'search_volume' => '',
            'related_topics' => array(),
            'clean_description' => ''
        );
        
        // Remove HTML tags and get clean text
        $clean_text = wp_strip_all_tags($description);
        $data['clean_description'] = trim($clean_text);
        
        // Try to extract search volume if present
        if (preg_match('/(\d+[\d,]*)\s*searches?/i', $clean_text, $matches)) {
            $data['search_volume'] = $matches[1];
        }
        
        // Extract related topics if present (basic extraction)
        if (preg_match_all('/related[:\s]*([^.]+)/i', $clean_text, $matches)) {
            $data['related_topics'] = array_map('trim', explode(',', $matches[1][0] ?? ''));
        }
        
        return $data;
    }
    
    /**
     * Convert trends to standardized JSON format
     * 
     * @param array $trends Raw trends array
     * @return array Filtered and formatted trends array
     */
    private function convert_to_json_format($trends) {
        $formatted_trends = array();
        
        foreach ($trends as $trend) {
            // Skip if already processed recently
            if ($this->is_recently_processed($trend['title'])) {
                continue;
            }
            
            // Apply keyword filter - only process relevant topics
            if (!$this->matches_filter_keywords($trend)) {
                continue;
            }
            
            $formatted_trend = array(
                'id' => md5($trend['title'] . $trend['pub_date']),
                'title' => $this->clean_title($trend['title']),
                'description' => $trend['clean_description'] ?? $trend['description'],
                'search_volume' => $trend['search_volume'] ?? '',
                'related_topics' => $trend['related_topics'] ?? array(),
                'source_url' => $trend['link'],
                'published_date' => $this->format_date($trend['pub_date']),
                'raw_data' => $trend
            );
            
            $formatted_trends[] = $formatted_trend;
        }
        
        return $formatted_trends;
    }
    
    /**
     * Check if trend matches filter keywords
     * 
     * @param array $trend Trend data to check
     * @return bool True if trend matches filter criteria
     */
    private function matches_filter_keywords($trend) {
        // Get text to search (title + description)
        $search_text = strtolower($trend['title'] . ' ' . ($trend['clean_description'] ?? $trend['description']));
        
        // Check if any of our filter keywords appear in the trend
        foreach ($this->filter_keywords as $keyword) {
            if (strpos($search_text, strtolower($keyword)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Clean and format trend title
     * 
     * @param string $title Raw title
     * @return string Cleaned title
     */
    private function clean_title($title) {
        // Remove extra whitespace
        $title = trim($title);
        
        // Remove any HTML entities
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        
        // Remove special characters that might cause issues
        $title = preg_replace('/[^\w\s\-\.]/u', '', $title);
        
        // Capitalize properly
        $title = ucwords(strtolower($title));
        
        return $title;
    }
    
    /**
     * Format date to WordPress standard
     * 
     * @param string $date_string Original date string
     * @return string Formatted date
     */
    private function format_date($date_string) {
        $timestamp = strtotime($date_string);
        
        if ($timestamp === false) {
            // Use current_time if available, otherwise use regular date
            if (function_exists('current_time')) {
                return current_time('mysql');
            } else {
                return date('Y-m-d H:i:s');
            }
        }
        
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * Check if trend was recently processed
     * 
     * @param string $title Trend title
     * @return bool True if recently processed
     */
    private function is_recently_processed($title) {
        global $wpdb;
        
        // If $wpdb is not available (e.g., running outside WordPress), skip check
        if (!$wpdb || !is_object($wpdb)) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'trendie_logs';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return false;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE trend_title = %s 
             AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $title
        ));
        
        return intval($count) > 0;
    }
    
    /**
     * Get sample trend for testing
     * 
     * @return array Sample trend data
     */
    public function get_sample_trend() {
        return array(
            'id' => 'sample_' . time(),
            'title' => 'Sample Trending Topic',
            'description' => 'This is a sample trending topic for testing purposes.',
            'search_volume' => '10,000',
            'related_topics' => array('technology', 'innovation', 'trends'),
            'source_url' => 'https://example.com',
            'published_date' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
            'raw_data' => array()
        );
    }
    
    /**
     * Get filtering statistics for the last fetch
     * 
     * @return array Filtering statistics
     */
    public function get_filter_stats() {
        // This would be called after fetch_trends() to get stats
        // For now, return sample data - in production, you'd track these during filtering
        return array(
            'total_trends_found' => 0,
            'trends_after_filtering' => 0,
            'trends_already_processed' => 0,
            'filter_keywords_used' => $this->filter_keywords
        );
    }
    
    /**
     * Test if current filter keywords would match a sample business/tech trend
     * 
     * @return array Test results
     */
    public function test_filter_keywords() {
        $test_trends = array(
            array('title' => 'Apple Stock Hits New High', 'description' => 'Apple Inc shares reached record levels today'),
            array('title' => 'Celebrity Fashion News', 'description' => 'Latest celebrity outfit trends and fashion updates'),
            array('title' => 'Bitcoin Price Surge', 'description' => 'Cryptocurrency markets see major gains'),
            array('title' => 'AI Technology Breakthrough', 'description' => 'New artificial intelligence development announced'),
            array('title' => 'Sports Game Results', 'description' => 'Latest football match scores and highlights')
        );
        
        $results = array();
        foreach ($test_trends as $trend) {
            $results[] = array(
                'title' => $trend['title'],
                'would_match' => $this->matches_filter_keywords($trend),
                'description' => $trend['description']
            );
        }
        
        return $results;
    }
} 