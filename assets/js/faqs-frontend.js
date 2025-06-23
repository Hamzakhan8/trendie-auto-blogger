jQuery(document).ready(function($) {
    
    // FAQ Accordion functionality
    $(document).on('click', '.trendie-faq-question', function(e) {
        e.preventDefault();
        
        const $faqItem = $(this).closest('.trendie-faq-item');
        const $answer = $faqItem.find('.trendie-faq-answer');
        const $toggle = $(this).find('.trendie-faq-toggle');
        
        // Check if this FAQ is currently open
        const isOpen = $faqItem.hasClass('active');
        
        if (isOpen) {
            // Close this FAQ
            $answer.slideUp(300, function() {
                $faqItem.removeClass('active');
            });
            $toggle.text('+');
        } else {
            // Close all other FAQs in this container (optional - for accordion behavior)
            const $container = $faqItem.closest('.trendie-faqs-container');
            const $otherItems = $container.find('.trendie-faq-item.active');
            
            $otherItems.each(function() {
                const $otherAnswer = $(this).find('.trendie-faq-answer');
                const $otherToggle = $(this).find('.trendie-faq-toggle');
                
                $otherAnswer.slideUp(300);
                $otherToggle.text('+');
                $(this).removeClass('active');
            });
            
            // Open this FAQ
            $answer.slideDown(300);
            $toggle.text('−');
            $faqItem.addClass('active');
        }
    });
    
    // Load More FAQs functionality
    $(document).on('click', '.trendie-load-more-faqs', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $container = $('#' + $button.data('container'));
        const $faqsList = $container.find('.trendie-faqs-list');
        const $count = $container.find('.trendie-faqs-count');
        
        const page = parseInt($button.data('page'));
        const perPage = parseInt($container.data('per-page'));
        const order = $container.data('order') || 'DESC';
        
        // Show loading state
        $button.prop('disabled', true).text('Loading...');
        
        // AJAX request to load more FAQs
        $.ajax({
            url: trendie_faqs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tab_load_more_faqs',
                page: page,
                per_page: perPage,
                order: order,
                nonce: trendie_faqs_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    // Append new FAQs with animation
                    const $newFaqs = $(response.data.html);
                    $newFaqs.hide();
                    $faqsList.append($newFaqs);
                    $newFaqs.fadeIn(300);
                    
                    // Update button for next page
                    $button.data('page', page + 1);
                    
                    // Update count
                    const currentCount = $faqsList.find('.trendie-faq-item').length;
                    const totalMatch = $count.text().match(/of (\d+)/);
                    const total = totalMatch ? parseInt(totalMatch[1]) : currentCount;
                    
                    $count.text(`Showing ${currentCount} of ${total} FAQs`);
                    
                    // Hide button if no more FAQs
                    if (!response.data.has_more) {
                        $button.fadeOut(300);
                    }
                } else {
                    // No more FAQs or error
                    $button.fadeOut(300);
                    
                    if (response.data && response.data.message) {
                        showFAQMessage($container, response.data.message, 'info');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('FAQ Load Error:', error);
                showFAQMessage($container, 'Failed to load more FAQs. Please try again.', 'error');
            },
            complete: function() {
                // Reset button state
                $button.prop('disabled', false).text('Load More FAQs');
            }
        });
    });
    
    // Utility function to show messages
    function showFAQMessage($container, message, type) {
        const $message = $('<div class="trendie-faq-message trendie-faq-message-' + type + '">' + 
                         '<p>' + message + '</p></div>');
        
        $container.append($message);
        
        setTimeout(function() {
            $message.fadeOut(300, function() {
                $message.remove();
            });
        }, 3000);
    }
    
    // Keyboard accessibility
    $(document).on('keydown', '.trendie-faq-question', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).click();
        }
    });
    
    // Add ARIA attributes for accessibility
    $('.trendie-faq-question').each(function() {
        const $question = $(this);
        const $faqItem = $question.closest('.trendie-faq-item');
        const $answer = $faqItem.find('.trendie-faq-answer');
        
        const faqId = $faqItem.data('faq-id');
        const questionId = 'faq-question-' + faqId;
        const answerId = 'faq-answer-' + faqId;
        
        $question.attr({
            'id': questionId,
            'aria-controls': answerId,
            'aria-expanded': 'false',
            'role': 'button',
            'tabindex': '0'
        });
        
        $answer.attr({
            'id': answerId,
            'aria-labelledby': questionId,
            'role': 'region'
        });
    });
    
    // Update ARIA attributes when FAQ state changes
    $(document).on('click', '.trendie-faq-question', function() {
        setTimeout(function() {
            $('.trendie-faq-item').each(function() {
                const $item = $(this);
                const $question = $item.find('.trendie-faq-question');
                const isActive = $item.hasClass('active');
                
                $question.attr('aria-expanded', isActive ? 'true' : 'false');
            });
        }, 50);
    });
    
    // Search functionality (if search input exists)
    let searchTimeout;
    $(document).on('input', '.trendie-faq-search', function() {
        const $input = $(this);
        const $container = $input.closest('.trendie-faqs-container');
        const searchTerm = $input.val().toLowerCase().trim();
        
        clearTimeout(searchTimeout);
        
        searchTimeout = setTimeout(function() {
            $container.find('.trendie-faq-item').each(function() {
                const $item = $(this);
                const question = $item.find('.trendie-faq-question h4').text().toLowerCase();
                const answer = $item.find('.trendie-faq-answer-content').text().toLowerCase();
                
                if (searchTerm === '' || question.includes(searchTerm) || answer.includes(searchTerm)) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
            
            // Show/hide "no results" message
            const $visibleItems = $container.find('.trendie-faq-item:visible');
            let $noResults = $container.find('.trendie-faq-no-results');
            
            if ($visibleItems.length === 0 && searchTerm !== '') {
                if ($noResults.length === 0) {
                    $noResults = $('<div class="trendie-faq-no-results">' +
                                 '<p>No FAQs found matching your search.</p></div>');
                    $container.find('.trendie-faqs-list').after($noResults);
                }
                $noResults.show();
            } else {
                $noResults.hide();
            }
        }, 300);
    });
    
    // Auto-expand FAQ if URL has hash
    if (window.location.hash) {
        const hash = window.location.hash.substring(1);
        const $targetFaq = $('.trendie-faq-item[data-faq-id="' + hash + '"]');
        
        if ($targetFaq.length) {
            setTimeout(function() {
                $targetFaq.find('.trendie-faq-question').click();
                
                $('html, body').animate({
                    scrollTop: $targetFaq.offset().top - 100
                }, 500);
            }, 500);
        }
    }
});

// Add custom CSS for message styling
(function() {
    const style = document.createElement('style');
    style.textContent = `
        .trendie-faq-message {
            padding: 12px 16px;
            margin: 10px 0;
            border-radius: 4px;
            font-size: 14px;
        }
        .trendie-faq-message-info {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }
        .trendie-faq-message-error {
            background: #ffebee;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }
        .trendie-faq-message p {
            margin: 0;
        }
        .trendie-faq-no-results {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        .trendie-faq-search {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    `;
    document.head.appendChild(style);
})(); 