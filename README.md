# Trendie Auto Blogger WordPress Plugin

A powerful WordPress plugin that automatically generates SEO-optimized blog posts from Google Trends using Gemini AI with intelligent keyword filtering. The plugin fetches trending topics from RSS feeds and creates engaging, human-like content that follows Google's best SEO practices.

## Features

- **Enhanced AI Content Generation**: Uses structured JSON responses from Gemini AI for better content quality
- **Automatic Post Generation**: Runs every 8 hours to generate fresh content
- **Manual Generation**: Click a button to generate posts on-demand
- **Google Trends Integration**: Fetches trending topics from Google Trends RSS
- **Advanced Prompt Engineering**: Sophisticated prompts for better SEO and content structure
- **Intelligent Image Integration**: AI-suggested images placed within content for better engagement
- **SEO Optimized**: Follows Google's E-A-T guidelines and SEO best practices
- **Humanized Content**: Generates natural, conversational content with actionable insights
- **Clean Admin Interface**: Modern, responsive admin dashboard with comprehensive settings
- **Comprehensive Logging**: Track successful and failed generation attempts with detailed feedback
- **Content Images**: Automatically places relevant images within article content
- **Enhanced Image Matching**: Better image search queries based on AI suggestions
- **Trending FAQs**: Generate and display trending FAQs using the `[trending_faqs]` shortcode
- **Intelligent RSS Feed Filtering**: Only processes trending topics relevant to business, finance, AI, crypto, and technology

## What's New in Version 2.0

### Enhanced Gemini AI Integration
- **Structured JSON Responses**: Gemini now returns structured data including title, content, meta description, tags, and image suggestions
- **Advanced Prompt Engineering**: More sophisticated prompts that request specific content structure and SEO optimization
- **Better Content Quality**: Includes statistics, facts, actionable tips, and E-A-T optimization
- **Focus Keywords**: Automatic extraction and optimization of primary keywords

### Improved Image Integration
- **AI-Suggested Images**: Gemini suggests specific images with search terms, alt text, and captions
- **Content Image Placement**: Images are automatically placed within article content, not just as featured images
- **Better Search Queries**: Enhanced image search with stop-word removal and keyword optimization
- **Multiple Image Support**: Support for both featured images and content images in the same post

### Advanced Content Features
- **SEO Scoring**: Each generated post includes SEO and readability scores
- **Enhanced Meta Data**: Better meta descriptions, focus keywords, and tag generation
- **Content Structure**: Proper H1, H2, H3 hierarchy with engaging introductions and conclusions
- **Fallback System**: Graceful degradation to legacy methods if structured generation fails

## New Filtering System

The plugin now includes an advanced filtering system that only processes RSS trends containing relevant keywords. This ensures that only business, technology, and finance-related content is generated.

### Default Filter Keywords

The system filters for trends containing these keywords:
- **Business & Finance**: business, finance, financial, stock, stocks, market, markets, trading, investment, investing, investor, economy, economic, banking, bank, revenue, profit, earnings
- **Technology**: AI, artificial intelligence, machine learning, technology, tech, software, SaaS, digital, innovation
- **Cryptocurrency**: crypto, cryptocurrency, bitcoin, blockchain, ethereum
- **Startups & Business**: startup, startups, entrepreneur, entrepreneurship, venture capital, VC, fintech, IPO, merger, acquisition
- **Finance Markets**: nasdaq, dow jones, sp500, forex, payment, payments, ecommerce

### Customizing Filter Keywords

1. Go to **WordPress Admin > Trendie Blogger > Settings**
2. Find the **RSS Feed Configuration** section
3. In the **Filter Keywords** field, enter your custom keywords (comma-separated)
4. Click **Test Filter Keywords** to see which sample trends would match
5. Save your settings

### Testing Your Keywords

Use the **Test Filter Keywords** button in the dashboard to see how your keywords perform against sample trending topics:
- ✅ MATCH: Trends that would be processed
- ❌ NO MATCH: Trends that would be filtered out

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Google Gemini API key
- Pexels API key (for image integration)
- Active internet connection for RSS fetching and API calls

## Installation

1. Download the plugin files
2. Upload the `trendie-auto-blogger` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure the plugin settings in the admin dashboard

## Configuration

### 1. Get API Keys

#### Google Gemini API Key
1. Visit [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Sign in with your Google account
3. Create a new API key
4. Copy the API key for use in the plugin

#### Pexels API Key (for images)
1. Visit [Pexels API](https://www.pexels.com/api/)
2. Create a free account
3. Generate an API key
4. Copy the API key for image integration

### 2. Plugin Settings

Navigate to **Trendie Blogger > Settings** in your WordPress admin:

#### Gemini AI Configuration
- **API Key**: Enter your Google Gemini API key

#### RSS Feed Configuration
- **Google Trends RSS URL**: Default is US trends, customize as needed
  - US: `https://trends.google.com/trends/trendingsearches/daily/rss?geo=US`
  - UK: `https://trends.google.com/trends/trendingsearches/daily/rss?geo=GB`
  - Global: `https://trends.google.com/trends/trendingsearches/daily/rss?geo=`

#### Post Configuration
- **Default Category**: Choose the category for generated posts
- **Post Status**: Set posts to Draft or Publish automatically
- **Max Posts Per Run**: Limit the number of posts generated (1-20)

#### Advanced Content Generation
- **Use Structured Generation**: Enable enhanced AI content generation (recommended)
- **Include Statistics**: Add relevant statistics and facts to posts
- **Include Actionable Tips**: Add practical insights and tips
- **Optimize for E-A-T**: Follow Google's E-A-T guidelines

#### Image Integration
- **Enable Featured Images**: Automatically add featured images from Pexels
- **Pexels API Key**: Enter your Pexels API key
- **Image Orientation**: Choose preferred image orientation (landscape, portrait, square)
- **Content Images**: Add images within post content for better engagement

#### Content Customization
- **Custom Prompt Template**: Customize the AI prompt (use `{trend_title}` placeholder)

## Usage

### Automatic Generation
- Posts are automatically generated every 8 hours
- Check the dashboard for next scheduled run time
- View generation logs for detailed information

### Manual Generation
1. Go to **Trendie Blogger** dashboard
2. Click **Generate Posts Now**
3. Monitor the progress and results
4. Check the logs for detailed information

### Testing Connections
- Use the **Test Gemini Connection** button to verify your API key works
- Test Pexels connection to ensure image integration works
- This helps troubleshoot connection issues before running generation

## Enhanced Features Overview

### AI Content Generation
- **Structured Responses**: JSON format with title, content, meta description, tags, and image suggestions
- **Advanced SEO**: Focus keyword extraction, proper heading structure, and meta optimization
- **Content Quality**: Minimum 1200 words with engaging structure and actionable insights
- **E-A-T Compliance**: Follows Expertise, Authoritativeness, and Trustworthiness guidelines

### Image Integration
- **AI-Suggested Images**: Gemini suggests specific images with search terms and alt text
- **Content Placement**: Images placed strategically within article content
- **SEO Optimization**: Proper alt text, captions, and photographer credits
- **Quality Selection**: Automatic selection of best images from search results

### Content Structure
- **Engaging Introductions**: Hook readers with compelling openings
- **Proper Hierarchy**: H1, H2, H3 heading structure for better SEO
- **Actionable Content**: Practical tips and insights readers can implement
- **Strong Conclusions**: Call-to-action and summarization

### Technical Improvements
- **Error Handling**: Graceful fallbacks and comprehensive error logging
- **Performance**: Optimized API calls and image processing
- **Backward Compatibility**: Legacy methods maintained for existing installations
- **Modular Architecture**: Clean, well-documented code structure

## File Structure

```
trendie-auto-blogger/
├── trendie-auto-blogger.php    # Main plugin file
├── assets/
│   ├── css/
│   │   ├── admin-style.css     # Admin dashboard styles
│   │   └── frontend-style.css  # Frontend styles
│   └── js/
│       └── admin-script.js     # Admin JavaScript
├── includes/
│   ├── class-admin-panel.php   # Enhanced admin interface
│   ├── class-rss-fetcher.php   # RSS feed handling
│   ├── class-gemini-api.php    # Enhanced Gemini AI integration
│   ├── class-post-generator.php # Main generation logic with image integration
│   ├── class-cron-manager.php  # Scheduling
│   ├── class-pexels-api.php    # Enhanced image integration
│   └── class-seo-optimizer.php # SEO optimization
└── README.md                   # Documentation
```

## Troubleshooting

### Common Issues

1. **API Connection Failed**
   - Verify your Gemini API key is correct
   - Check internet connection
   - Ensure API key has proper permissions

2. **Structured Generation Not Working**
   - Ensure "Use Structured Generation" is enabled in settings
   - Check if custom prompts include JSON structure requirements
   - View logs for detailed error messages

3. **Images Not Loading**
   - Verify Pexels API key is correct
   - Check if "Enable Featured Images" is enabled
   - Ensure proper image orientation is selected

4. **Content Quality Issues**
   - Enable "Include Statistics" and "Include Actionable Tips"
   - Activate "Optimize for E-A-T" setting
   - Use structured generation for better results

5. **No Trends Found**
   - Check RSS URL is accessible
   - Verify Google Trends RSS is working
   - Try different geographic regions

6. **Posts Not Generated**
   - Check WordPress cron is functioning
   - Verify post category exists
   - Check error logs for detailed information

### Debug Mode

Enable WordPress debug mode by adding these lines to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for detailed error information.

## Performance Considerations

- **API Rate Limits**: Both Gemini and Pexels have rate limits; the plugin respects these
- **Image Processing**: Images are optimized for web performance
- **Caching**: Generated content is cached to improve performance
- **Timeout Handling**: Extended timeouts for AI content generation

## Support

For support and bug reports:
1. Check the generation logs in the admin dashboard
2. Review WordPress debug logs
3. Verify all configuration settings
4. Test both Gemini and Pexels API connections

## Contributing

Contributions are welcome! Please ensure:
- Clean, well-documented code
- Follow WordPress coding standards
- Test thoroughly before submitting
- Maintain backward compatibility

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 2.0.0
- **Enhanced Gemini Integration**: Structured JSON responses with better content quality
- **Advanced Image Integration**: AI-suggested images with content placement
- **Improved SEO**: Focus keywords, better meta descriptions, and E-A-T optimization
- **Better Content Structure**: Advanced prompt engineering for higher quality posts
- **Content Images**: Images placed within article content, not just featured images
- **Enhanced Admin Interface**: New settings for advanced features
- **Performance Improvements**: Better error handling and fallback systems
- **Comprehensive Logging**: Detailed feedback on generation and image processing

### Version 1.0.0
- Initial release
- Google Trends RSS integration
- Basic Gemini AI content generation
- SEO optimization features
- Admin dashboard with statistics
- Automatic and manual generation
- Comprehensive logging system 

## Trending FAQs Feature

The plugin now includes a powerful FAQ generation system that creates frequently asked questions based on trending topics from Google Trends.

### How It Works

1. **Trend Detection**: Uses the same Google Trends RSS feed as post generation
2. **AI-Generated FAQs**: Sends trend titles to Gemini API to generate comprehensive Q&A pairs
3. **Database Storage**: Stores FAQs in a dedicated database table with full management capabilities
4. **Shortcode Display**: Easy integration anywhere using the `[trending_faqs]` shortcode

### FAQ Shortcode Usage

Display trending FAQs anywhere on your site using the shortcode:

```
[trending_faqs]
```

#### Shortcode Parameters

- **per_page**: Number of FAQs to display per page (default: 10)
- **show_title**: Show the "Trending FAQs" heading (yes/no, default: yes)
- **show_trend**: Show trend topic badges (yes/no, default: yes)
- **style**: Display style (accordion, default: accordion)
- **order**: Sort order (ASC/DESC, default: DESC)

#### Example Usage

```
[trending_faqs per_page="5" show_title="no" show_trend="yes" order="ASC"]
```

### FAQ Management

Navigate to **Trendie Blogger > Trending FAQs** to:
- Generate new FAQs manually
- View FAQ statistics
- Delete unwanted FAQs
- Copy shortcode for easy use

### Features

- **Accordion Interface**: Clean, expandable FAQ display
- **AJAX Pagination**: Load more FAQs without page refresh
- **Responsive Design**: Works perfectly on all devices
- **SEO Optimized**: Structured data for better search visibility
- **Accessibility**: Full keyboard navigation and screen reader support
- **Auto-generation**: Can be automated with WordPress cron jobs 