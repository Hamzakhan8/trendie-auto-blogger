jQuery(document).ready(function($) {
    
    // Manual post generation
    $('#tab-manual-generate').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $status = $('#tab-status-message');
        
        $button.prop('disabled', true).text('Generating...');
        $status.html('<div class="notice notice-info"><p>Generating posts, please wait...</p></div>');
        
        $.ajax({
            url: tab_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tab_manual_generate',
                nonce: tab_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const result = response.data;
                    let message = '<div class="notice notice-success"><p>';
                    message += 'Generation complete!<br>';
                    message += 'Successful: ' + result.success + '<br>';
                    message += 'Failed: ' + result.failed;
                    if (result.messages && result.messages.length > 0) {
                        message += '<br><br><strong>Details:</strong><br>';
                        result.messages.forEach(function(msg) {
                            message += '• ' + msg + '<br>';
                        });
                    }
                    message += '</p></div>';
                    $status.html(message);
                } else {
                    $status.html('<div class="notice notice-error"><p>Generation failed. Please check your settings and try again.</p></div>');
                }
            },
            error: function() {
                $status.html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Generate Posts Now');
            }
        });
    });
    
    // Test Gemini connection
    $('#tab-test-connection').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $status = $('#tab-status-message');
        
        $button.prop('disabled', true).text('Testing...');
        $status.html('<div class="notice notice-info"><p>Testing Gemini API connection...</p></div>');
        
        $.ajax({
            url: tab_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tab_test_connection',
                nonce: tab_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const result = response.data;
                    $status.html('<div class="notice notice-success"><p>' + result.message + '</p></div>');
                } else {
                    $status.html('<div class="notice notice-error"><p>Connection failed: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $status.html('<div class="notice notice-error"><p>An error occurred while testing the connection.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Gemini Connection');
            }
        });
    });
    
    // Test OpenAI connection
    $('#tab-test-openai').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $status = $('#tab-status-message');
        
        $button.prop('disabled', true).text('Testing...');
        $status.html('<div class="notice notice-info"><p>Testing OpenAI API connection...</p></div>');
        
        $.ajax({
            url: tab_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tab_test_openai',
                nonce: tab_ajax.nonce
            },
            success: function(response) {
                console.log('OpenAI Test Response:', response); // Debug log
                
                if (response.success) {
                    const result = response.data;
                    let message = '<div class="notice notice-success"><p>' + result.message;
                    if (result.test_content) {
                        message += '<br><br><strong>Sample output:</strong><br>' + result.test_content;
                    }
                    message += '</p></div>';
                    $status.html(message);
                } else {
                    console.error('OpenAI Test Failed:', response);
                    let errorMsg = response.data ? response.data : 'Unknown error';
                    $status.html('<div class="notice notice-error"><p>OpenAI connection failed: ' + errorMsg + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('OpenAI Test AJAX Error:', {xhr: xhr, status: status, error: error});
                $status.html('<div class="notice notice-error"><p>An error occurred while testing OpenAI connection. Please check the console for details.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test OpenAI Connection');
            }
        });
    });
    
    // Test Filter Keywords
    $('#tab-test-filter').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $status = $('#tab-status-message');
        const keywords = $('#tab_filter_keywords').val();
        
        $button.prop('disabled', true).text('Testing...');
        $status.html('<div class="notice notice-info"><p>Testing filter keywords...</p></div>');
        
        $.ajax({
            url: tab_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tab_test_filter',
                keywords: keywords,
                nonce: tab_ajax.nonce
            },
            success: function(response) {
                console.log('Filter Test Response:', response); // Debug log
                
                if (response.success) {
                    const result = response.data;
                    let message = '<div class="notice notice-success"><p>';
                    message += result.message + '<br><br>';
                    message += '<strong>Test Results:</strong><br>';
                    
                    if (result.results && result.results.length > 0) {
                        result.results.forEach(function(test) {
                            const status = test.would_match ? '✅ MATCH' : '❌ NO MATCH';
                            message += '• ' + test.title + ' - ' + status + '<br>';
                        });
                    }
                    message += '</p></div>';
                    $status.html(message);
                } else {
                    console.error('Filter Test Failed:', response);
                    let errorMsg = response.data ? response.data : 'Unknown error';
                    $status.html('<div class="notice notice-error"><p>Filter test failed: ' + errorMsg + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Filter Test AJAX Error:', {xhr: xhr, status: status, error: error});
                $status.html('<div class="notice notice-error"><p>An error occurred while testing the filter. Please check the console for details.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Filter Keywords');
            }
        });
    });
    
    // Generate Trending FAQs
    $('#tab-generate-faqs').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $status = $('#tab-status-message');
        
        $button.prop('disabled', true).text('Generating FAQs...');
        $status.html('<div class="notice notice-info"><p>Generating trending FAQs, please wait...</p></div>');
        
        $.ajax({
            url: tab_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tab_generate_faqs',
                max_trends: 5,
                nonce: tab_ajax.nonce
            },
            success: function(response) {
                console.log('FAQ Generation Response:', response); // Debug log
                
                if (response.success) {
                    const result = response.data;
                    let message = '<div class="notice notice-success"><p>';
                    message += 'FAQ Generation Complete!<br>';
                    message += 'Successful: ' + result.success + '<br>';
                    message += 'Failed: ' + result.failed + '<br>';
                    message += 'Total FAQs: ' + result.total_faqs;
                    
                    if (result.messages && result.messages.length > 0) {
                        message += '<br><br><strong>Details:</strong><br>';
                        result.messages.forEach(function(msg) {
                            message += '• ' + msg + '<br>';
                        });
                    }
                    message += '</p></div>';
                    $status.html(message);
                } else {
                    console.error('FAQ Generation Failed:', response);
                    let errorMsg = response.data ? response.data : 'Unknown error';
                    $status.html('<div class="notice notice-error"><p>FAQ generation failed: ' + errorMsg + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                $status.html('<div class="notice notice-error"><p>An error occurred during FAQ generation. Please check the console for details.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Generate Trending FAQs');
            }
        });
    });
    
    // Auto-hide notices after 10 seconds
    setTimeout(function() {
        $('.notice').fadeOut();
    }, 10000);
    
    // Form validation for settings
    $('form').on('submit', function() {
        var apiKey = $('#tab_gemini_api_key').val().trim();
        var rssUrl = $('#tab_rss_url').val().trim();
        
        if (!apiKey) {
            alert('Please enter your Gemini API key before saving.');
            $('#tab_gemini_api_key').focus();
            return false;
        }
        
        if (!rssUrl) {
            alert('Please enter a valid RSS URL.');
            $('#tab_rss_url').focus();
            return false;
        }
        
        return true;
    });
    
    // Show/hide API key
    $('#tab_gemini_api_key').after('<button type="button" class="button" id="toggle-api-key" style="margin-left: 10px;">Show</button>');
    
    $('#toggle-api-key').on('click', function() {
        var input = $('#tab_gemini_api_key');
        var button = $(this);
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            button.text('Hide');
        } else {
            input.attr('type', 'password');
            button.text('Show');
        }
    });
    
    // Tooltip functionality
    $('[title]').each(function() {
        $(this).tooltip({
            position: {
                my: "center bottom-20",
                at: "center top",
                using: function(position, feedback) {
                    $(this).css(position);
                    $("<div>")
                        .addClass("arrow")
                        .addClass(feedback.vertical)
                        .addClass(feedback.horizontal)
                        .appendTo(this);
                }
            }
        });
    });
}); 