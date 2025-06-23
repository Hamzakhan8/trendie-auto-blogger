<?php

class TAB_CronManager {
    
    public function __construct() {
        add_action('tab_auto_generate_posts', array($this, 'run_auto_generation'));
    }
    
    /**
     * Run automatic post generation
     */
    public function run_auto_generation() {
        $post_generator = new TAB_PostGenerator();
        $result = $post_generator->generate_posts();
        
        // Log the result
        error_log('Trendie Auto Blogger: Automatic generation completed. Success: ' . $result['success'] . ', Failed: ' . $result['failed']);
    }
} 