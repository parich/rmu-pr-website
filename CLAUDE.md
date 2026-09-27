# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin called "RMU PR Website" that displays news posts from a university's PR website. It creates a custom Gutenberg block and provides a shortcode `[rmu_pr_website]` for displaying categorized posts with search functionality.

## Development Commands

### Build and Development

- `npm run build` - Build the plugin for production with blocks manifest
- `npm run start` - Start development server with hot reload and blocks manifest
- `npm run plugin-zip` - Create a distributable plugin ZIP file

### Code Quality

- `npm run format` - Format code using WordPress standards
- `npm run lint:css` - Lint CSS/SCSS files
- `npm run lint:js` - Lint JavaScript files
- `npm run packages-update` - Update WordPress script packages

### Testing

The plugin can be tested by:

1. Installing in a WordPress environment
2. Using the shortcode `[rmu_pr_website]` in posts/pages
3. Adding the block in Gutenberg editor

## Architecture

### Block Structure

The plugin follows WordPress block development standards:

- **Source files**: `src/rmu-pr-website/` - Development files
- **Built files**: `build/rmu-pr-website/` - Compiled assets
- **Block registration**: Uses `blocks-manifest.php` for efficient registration (WordPress 6.7+)

### Key Components

1. **Main plugin file** (`rmu-pr-website.php`):

   - Block registration with fallback for older WordPress versions
   - Admin settings page for customization
   - Shortcode registration
   - Asset enqueuing with localized data

2. **Block files**:

   - `block.json` - Block metadata and configuration
   - `edit.js` - Block editor interface
   - `view.js` - Frontend JavaScript functionality
   - `render.php` - Server-side rendering
   - `style.scss` - Frontend styles

3. **Frontend functionality** (`view.js`):
   - Fetches posts from external WordPress REST API
   - Implements tabbed category navigation
   - Search functionality with pagination
   - Uses DOMPurify for XSS protection

### Settings and Configuration

The plugin includes an admin settings page at **Settings > RMU PR Website** with options for:

- Category slugs (comma-separated)
- Base URL for the external WordPress site
- Color customization for tabs and pagination
- Option to hide search input
- Reset to default settings

### Data Flow

1. Admin configures category slugs and base URL in settings
2. Settings are passed to frontend via `wp_localize_script()`
3. Frontend JavaScript fetches posts from `{baseUrl}/?rest_route=/wp/v2/posts` (always `?rest_route=`, never `/wp-json/`, so it works when the source site uses Plain permalinks)
   - Always pass `_fields` (no `content`): the source site runs Elementor, which echoes `<style id="elementor-post-N">` before the JSON when `content` is rendered — this corrupts the JSON and suppresses `X-WP-TotalPages` + `Access-Control-Allow-Origin` (browser then blocks it as CORS). `_fields` must include `_links,_embedded` for `_embed` to work.
4. Posts are filtered by category and search terms
5. Results are displayed with pagination

## Coding Standards

- Follows WordPress coding standards (.editorconfig configured)
- Uses tabs for indentation (except YAML files)
- PHP code includes security checks (`ABSPATH` verification)
- Frontend code uses modern JavaScript with DOMPurify for security
- Thai language support in comments and UI text

## File Structure

```
rmu-pr-website/
├── src/rmu-pr-website/          # Source files
│   ├── block.json               # Block metadata
│   ├── edit.js                  # Block editor
│   ├── view.js                  # Frontend logic
│   ├── render.php               # Server rendering
│   ├── style.scss               # Styles
│   └── ...
├── build/                       # Built assets
├── rmu-pr-website.php           # Main plugin file
└── package.json                 # Dependencies and scripts
```

## Important Notes

- The plugin depends on an external WordPress site's REST API
- Requires WordPress 6.7+ for optimal performance (but includes fallbacks)
- Uses `@wordpress/scripts` for build tooling
- Includes both block and shortcode interfaces for flexibility
