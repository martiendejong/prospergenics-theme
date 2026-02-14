# Prospergenics WordPress Theme

A modern, parallax-scrolling WordPress theme for Prospergenics - Building Prosperity Together.

## Features

- **Parallax Scrolling**: Fullscreen hero sections with smooth parallax effects
- **Responsive Design**: Mobile-first approach with breakpoints for all devices
- **Accessibility Ready**: WCAG 2.1 compliant with proper ARIA labels and semantic HTML
- **Translation Ready**: All strings wrapped with translation functions
- **SEO Friendly**: Proper heading structure and semantic markup
- **Customizer Integration**: Easy customization through WordPress Customizer
- **Widget Areas**: Three footer widget areas for flexible content
- **Navigation Menus**: Primary and footer menu locations
- **Contact Form 7 Compatible**: Styled support for CF7 forms
- **Modern CSS**: CSS custom properties (variables) for easy theming
- **Performance Optimized**: Minimal dependencies, optimized assets

## Installation

1. Download the theme files
2. Upload to `wp-content/themes/prospergenics-wp-theme/` directory
3. Activate the theme in WordPress Admin > Appearance > Themes
4. Configure theme settings in Appearance > Customize

## Theme Setup

### Navigation Menus

The theme supports two menu locations:

- **Primary Menu**: Main navigation in the header
- **Footer Menu**: Footer navigation links

Create menus in Appearance > Menus and assign them to these locations.

### Customizer Settings

Access Appearance > Customize to configure:

- **Site Identity**: Logo, site title, tagline
- **Hero Section**: Headline, tagline, description, background image
- **Contact Information**: Email and phone number
- **Social Media Links**: Facebook, Twitter, LinkedIn, Instagram URLs

### Widget Areas

Three footer widget areas are available:

- **Footer Area 1**: First footer column
- **Footer Area 2**: Second footer column
- **Footer Area 3**: Third footer column

Add widgets in Appearance > Widgets.

### Contact Form

The theme is designed to work with Contact Form 7:

1. Install and activate Contact Form 7 plugin
2. Create a contact form
3. The theme will automatically style it

Alternatively, a fallback HTML form is provided.

### Background Images

For best results, upload high-resolution images for parallax sections:

- **Hero Background**: 1920x1080px minimum
- **Section Backgrounds**: 1920x1080px minimum

Set the hero background in Appearance > Customize > Hero Section.

## Page Templates

- **Front Page** (front-page.php): Homepage with parallax sections
- **Blog** (index.php): Blog post listing
- **Single Post** (single.php): Individual blog post
- **Page** (page.php): Standard pages

## Customization

### Colors

The theme uses CSS custom properties defined in `style.css`:

```css
--color-green: #2E8B57;
--color-terracotta: #b45309;
--color-white: #ffffff;
```

You can customize these in your child theme or via additional CSS.

### Typography

The theme uses system fonts for optimal performance:

```css
--font-primary: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
```

## Browser Support

- Chrome (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Edge (latest 2 versions)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Performance

- Minimal JavaScript dependencies
- CSS-only animations where possible
- Optimized images with responsive srcset support
- Lazy loading for images (WordPress 5.5+)

## Accessibility

- Keyboard navigation support
- Screen reader friendly
- Proper heading hierarchy
- ARIA labels on interactive elements
- Focus indicators on all interactive elements
- Skip to content link

## Credits

- **Design**: Prospergenics Team
- **Development**: Custom WordPress theme
- **Fonts**: System fonts (no external dependencies)

## Changelog

### Version 1.0.0
- Initial release
- Parallax homepage with hero sections
- Blog and single post templates
- Customizer integration
- Widget areas
- Navigation menus
- Contact form support
- Responsive design
- Accessibility features

## Support

For support and questions:
- Visit: https://prospergenics.com
- Email: Use the contact form on the website

## License

This theme is licensed under the GNU General Public License v2 or later.

Copyright 2026 Prospergenics
