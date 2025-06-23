<?php

class TAB_SEOOptimizer {
    
    /**
     * Optimize content for SEO
     * 
     * @param array $content Parsed content
     * @param array $trend Trend data
     * @return array Optimized content
     */
    public function optimize_content($content, $trend) {
        $optimized = $content;
        
        // Extract focus keyword from trend title
        $focus_keyword = $this->extract_focus_keyword($trend['title']);
        
        // Optimize title with focus keyword
        $optimized['title'] = $this->optimize_title($content['title'] ?: $trend['title'], $focus_keyword);
        
        // Optimize meta description with focus keyword
        $optimized['meta_description'] = $this->optimize_meta_description($content['meta_description'], $optimized['title'], $focus_keyword);
        
        // Optimize content structure with focus keyword
        $optimized['content'] = $this->optimize_content_structure($content['content'], $optimized['title'], $focus_keyword);
        
        // Optimize tags
        $optimized['tags'] = $this->optimize_tags($content['tags'], $trend, $focus_keyword);
        
        // Add SEO metadata
        $optimized['focus_keyword'] = $focus_keyword;
        $optimized['seo_score'] = $this->calculate_seo_score($optimized, $focus_keyword);
        
        return $optimized;
    }
    
    /**
     * Extract focus keyword from trend title
     * 
     * @param string $title Trend title
     * @return string Focus keyword
     */
    private function extract_focus_keyword($title) {
        // Remove common words and extract main keyword
        $stop_words = array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should');
        
        $words = explode(' ', strtolower($title));
        $keywords = array();
        
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z0-9]/', '', $word);
            if (strlen($word) > 3 && !in_array($word, $stop_words)) {
                $keywords[] = $word;
            }
        }
        
        // Return first 2-3 words as focus keyword phrase
        return implode(' ', array_slice($keywords, 0, 2));
    }
    
    /**
     * Optimize title for SEO
     * 
     * @param string $title Original title
     * @param string $focus_keyword Focus keyword
     * @return string Optimized title
     */
    private function optimize_title($title, $focus_keyword = '') {
        // Remove extra spaces and clean
        $title = trim(preg_replace('/\s+/', ' ', $title));
        
        // Ensure focus keyword is in title (if not already present)
        if (!empty($focus_keyword) && stripos($title, $focus_keyword) === false) {
            // Try to naturally incorporate focus keyword
            $title = $focus_keyword . ': ' . $title;
        }
        
        // Ensure title is not too long (60 characters max for SEO)
        if (strlen($title) > 60) {
            $title = substr($title, 0, 57) . '...';
        }
        
        // Proper case formatting
        $title = $this->format_title_case($title);
        
        return $title;
    }
    
    /**
     * Format title with proper case
     * 
     * @param string $title Title to format
     * @return string Formatted title
     */
    private function format_title_case($title) {
        // Words that should remain lowercase (unless at start)
        $lowercase_words = array('a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'if', 'in', 'of', 'on', 'or', 'the', 'to', 'up', 'via');
        
        $words = explode(' ', strtolower($title));
        $formatted_words = array();
        
        foreach ($words as $index => $word) {
            if ($index === 0 || !in_array($word, $lowercase_words)) {
                $formatted_words[] = ucfirst($word);
            } else {
                $formatted_words[] = $word;
            }
        }
        
        return implode(' ', $formatted_words);
    }
    
    /**
     * Optimize meta description
     * 
     * @param string $meta_description Original meta description
     * @param string $title Post title
     * @param string $focus_keyword Focus keyword
     * @return string Optimized meta description
     */
    private function optimize_meta_description($meta_description, $title, $focus_keyword = '') {
        if (empty($meta_description)) {
            if (!empty($focus_keyword)) {
                $meta_description = "Discover everything about " . $focus_keyword . ". Get comprehensive insights, latest updates, and expert analysis on " . strtolower($title) . ".";
            } else {
                $meta_description = "Learn about " . $title . " and discover the latest trends and insights.";
            }
        }
        
        // Ensure focus keyword is in meta description
        if (!empty($focus_keyword) && stripos($meta_description, $focus_keyword) === false) {
            $meta_description = $focus_keyword . " - " . $meta_description;
        }
        
        // Ensure meta description is within 150-160 characters
        if (strlen($meta_description) > 160) {
            $meta_description = substr($meta_description, 0, 157) . '...';
        } elseif (strlen($meta_description) < 120) {
            $meta_description .= " Get the latest information and expert insights.";
            if (strlen($meta_description) > 160) {
                $meta_description = substr($meta_description, 0, 157) . '...';
            }
        }
        
        return $meta_description;
    }
    
    /**
     * Optimize content structure for SEO
     * 
     * @param string $content Original content
     * @param string $title Post title
     * @param string $focus_keyword Focus keyword
     * @return string Optimized content
     */
    private function optimize_content_structure($content, $title, $focus_keyword = '') {
        // Ensure focus keyword appears in first paragraph
        if (!empty($focus_keyword)) {
            $content = $this->ensure_keyword_in_content($content, $focus_keyword);
        }
        
        // Add H1 if not present
        if (!preg_match('/<h1/i', $content) && !preg_match('/^#\s/', $content)) {
            $content = "# " . $title . "\n\n" . $content;
        }
        
        // Convert markdown headings to HTML
        $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $content);
        
        // Convert H2: format to proper HTML
        $content = preg_replace('/^H2:\s*(.+)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^H3:\s*(.+)$/m', '<h3>$1</h3>', $content);
        
        // Add paragraph tags (simple implementation)
        $content = $this->add_paragraph_tags($content);
        
        // Add internal linking opportunities
        $content = $this->add_internal_links($content);
        
        // Improve readability
        $content = $this->improve_readability($content);
        
        return $content;
    }
    
    /**
     * Ensure focus keyword appears in content
     * 
     * @param string $content Content
     * @param string $focus_keyword Focus keyword
     * @return string Content with keyword
     */
    private function ensure_keyword_in_content($content, $focus_keyword) {
        // Check if keyword already exists
        if (stripos($content, $focus_keyword) !== false) {
            return $content;
        }
        
        // Add keyword to first paragraph
        $paragraphs = explode("\n\n", $content);
        if (!empty($paragraphs[0])) {
            $first_paragraph = $paragraphs[0];
            // Try to naturally incorporate the keyword
            $first_paragraph = $focus_keyword . " has become increasingly important. " . $first_paragraph;
            $paragraphs[0] = $first_paragraph;
            $content = implode("\n\n", $paragraphs);
        }
        
        return $content;
    }
    
    /**
     * Add paragraph tags (simple implementation)
     * 
     * @param string $content Content
     * @return string Content with paragraph tags
     */
    private function add_paragraph_tags($content) {
        // Split by double newlines and wrap in <p> tags
        $paragraphs = explode("\n\n", $content);
        $formatted_paragraphs = array();
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                // Don't wrap headings in <p> tags
                if (!preg_match('/^<h[1-6]/', $paragraph)) {
                    $paragraph = '<p>' . $paragraph . '</p>';
                }
                $formatted_paragraphs[] = $paragraph;
            }
        }
        
        return implode("\n\n", $formatted_paragraphs);
    }
    
    /**
     * Improve content readability
     * 
     * @param string $content Content
     * @return string Improved content
     */
    private function improve_readability($content) {
        // Add transition words and phrases for better flow
        $transitions = array(
            'Furthermore,', 'Additionally,', 'Moreover,', 'In addition,', 
            'However,', 'Nevertheless,', 'On the other hand,', 'Meanwhile,',
            'As a result,', 'Consequently,', 'Therefore,', 'Thus,'
        );
        
        // Split into paragraphs
        $paragraphs = explode('</p>', $content);
        $improved_paragraphs = array();
        
        foreach ($paragraphs as $index => $paragraph) {
            if (!empty(trim($paragraph))) {
                // Occasionally add transition words (not too frequently)
                if ($index > 0 && $index % 3 === 0 && !preg_match('/^<h[1-6]/', $paragraph)) {
                    $transition = $transitions[array_rand($transitions)];
                    $paragraph = str_replace('<p>', '<p>' . $transition . ' ', $paragraph);
                }
                $improved_paragraphs[] = $paragraph;
            }
        }
        
        return implode('</p>', $improved_paragraphs);
    }
    
    /**
     * Optimize tags for SEO
     * 
     * @param array $tags Original tags
     * @param array $trend Trend data
     * @param string $focus_keyword Focus keyword
     * @return array Optimized tags
     */
    private function optimize_tags($tags, $trend, $focus_keyword = '') {
        $optimized_tags = array();
        
        // Add focus keyword as primary tag
        if (!empty($focus_keyword)) {
            $optimized_tags[] = $focus_keyword;
            // Add variations of focus keyword
            $keyword_parts = explode(' ', $focus_keyword);
            $optimized_tags = array_merge($optimized_tags, $keyword_parts);
        }
        
        // Add trend-related tags
        if (!empty($trend['related_topics'])) {
            $optimized_tags = array_merge($optimized_tags, $trend['related_topics']);
        }
        
        // Add original tags
        if (!empty($tags)) {
            $optimized_tags = array_merge($optimized_tags, $tags);
        }
        
        // Add contextual tags based on content type
        $contextual_tags = array('trending', 'latest', 'news', 'analysis', 'guide', 'tips');
        $optimized_tags = array_merge($optimized_tags, $contextual_tags);
        
        // Clean and deduplicate
        $optimized_tags = array_unique(array_map('trim', array_map('strtolower', $optimized_tags)));
        
        // Remove empty tags
        $optimized_tags = array_filter($optimized_tags);
        
        // Limit to 10 tags for optimal SEO
        return array_slice($optimized_tags, 0, 10);
    }
    
    /**
     * Calculate SEO score based on optimization factors
     * 
     * @param array $content Optimized content
     * @param string $focus_keyword Focus keyword
     * @return int SEO score (0-100)
     */
    private function calculate_seo_score($content, $focus_keyword) {
        $score = 0;
        $max_score = 100;
        
        // Title optimization (20 points)
        if (!empty($content['title'])) {
            $score += 10; // Has title
            if (!empty($focus_keyword) && stripos($content['title'], $focus_keyword) !== false) {
                $score += 10; // Focus keyword in title
            }
        }
        
        // Meta description optimization (20 points)
        if (!empty($content['meta_description'])) {
            $score += 10; // Has meta description
            if (!empty($focus_keyword) && stripos($content['meta_description'], $focus_keyword) !== false) {
                $score += 10; // Focus keyword in meta description
            }
        }
        
        // Content optimization (30 points)
        if (!empty($content['content'])) {
            $word_count = str_word_count(strip_tags($content['content']));
            if ($word_count >= 300) {
                $score += 10; // Adequate word count
            }
            if ($word_count >= 1000) {
                $score += 5; // Good word count
            }
            
            if (!empty($focus_keyword)) {
                $keyword_density = substr_count(strtolower($content['content']), strtolower($focus_keyword)) / $word_count * 100;
                if ($keyword_density >= 0.5 && $keyword_density <= 2.5) {
                    $score += 15; // Good keyword density
                }
            }
        }
        
        // Heading structure (15 points)
        if (!empty($content['content'])) {
            if (preg_match('/<h1/', $content['content'])) {
                $score += 5; // Has H1
            }
            if (preg_match('/<h2/', $content['content'])) {
                $score += 5; // Has H2
            }
            if (preg_match('/<h3/', $content['content'])) {
                $score += 5; // Has H3
            }
        }
        
        // Tags optimization (15 points)
        if (!empty($content['tags'])) {
            $score += 10; // Has tags
            if (!empty($focus_keyword) && in_array(strtolower($focus_keyword), array_map('strtolower', $content['tags']))) {
                $score += 5; // Focus keyword in tags
            }
        }
        
        return min($score, $max_score);
    }
    
    /**
     * Add internal links to content (basic implementation)
     * 
     * @param string $content Content to add links to
     * @return string Content with internal links
     */
    private function add_internal_links($content) {
        // This is a basic implementation
        // In a real scenario, you'd want to search for related posts and add contextual links
        
        return $content;
    }
} 