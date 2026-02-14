# Prospergenics Theme Installation Guide

Complete step-by-step guide to install and configure the Prospergenics WordPress theme.

## Prerequisites

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Installation Steps

### 1. Upload Theme Files

**Option A: Via WordPress Admin**
1. Log in to WordPress Admin
2. Go to Appearance > Themes
3. Click "Add New"
4. Click "Upload Theme"
5. Choose the `prospergenics-wp-theme.zip` file
6. Click "Install Now"
7. Click "Activate"

**Option B: Via FTP**
1. Upload the `prospergenics-wp-theme` folder to `/wp-content/themes/`
2. Go to Appearance > Themes in WordPress Admin
3. Find "Prospergenics" and click "Activate"

### 2. Configure Theme Settings

#### Site Identity
1. Go to Appearance > Customize > Site Identity
2. Upload your logo (recommended size: 200x60px)
3. Set Site Title: "Prospergenics"
4. Set Tagline: "Building Prosperity Together"

#### Hero Section
1. Go to Appearance > Customize > Hero Section
2. Set Hero Headline: "Building Prosperity Together"
3. Set Hero Tagline: "Empowering Futures, Cultivating Growth"
4. Set Hero Description: "Join our diverse community where learning, teamwork, and growth lead to real success."
5. Upload Hero Background Image (1920x1080px minimum)

#### Contact Information
1. Go to Appearance > Customize > Contact Information
2. Add your email address
3. Add your phone number (optional)

#### Social Media Links
1. Go to Appearance > Customize > Social Media Links
2. Add URLs for:
   - Facebook
   - Twitter
   - LinkedIn
   - Instagram

### 3. Create Navigation Menus

#### Primary Menu
1. Go to Appearance > Menus
2. Click "Create a new menu"
3. Name it "Primary Menu"
4. Add pages:
   - Home
   - About
   - Our Team
   - Our Programs
   - Blog
   - Contact
5. Check "Primary Menu" under Menu Settings
6. Click "Save Menu"

#### Footer Menu
1. Create another menu named "Footer Menu"
2. Add pages:
   - Privacy Policy
   - Terms of Service
   - Sitemap
3. Check "Footer Menu" under Menu Settings
4. Click "Save Menu"

### 4. Create Pages

Create the following pages:

#### Home Page
1. Go to Pages > Add New
2. Title: "Home"
3. Don't add content (the front-page.php template handles this)
4. Publish

Set as homepage:
1. Go to Settings > Reading
2. Select "A static page"
3. Choose "Home" for Homepage

#### About Page
1. Create page titled "About"
2. Add your full about content
3. Publish

#### Our Team Page
1. Create page titled "Our Team"
2. Add team member information
3. Publish

#### Our Programs Page
1. Create page titled "Our Programs"
2. Add detailed program information
3. Publish

#### Contact Page
1. Create page titled "Contact"
2. Add contact form shortcode (if using Contact Form 7)
3. Publish

#### Blog Page
1. Create page titled "Blog"
2. Leave content empty
3. Publish

Set as blog page:
1. Go to Settings > Reading
2. Choose "Blog" for Posts page

### 5. Install Recommended Plugins

#### Essential Plugins

**Contact Form 7**
1. Go to Plugins > Add New
2. Search "Contact Form 7"
3. Install and activate
4. Go to Contact > Contact Forms
5. Create a new form with fields:
   - Name (required)
   - Email (required)
   - Phone
   - Subject (required)
   - Message (required)
6. Copy the shortcode
7. Add it to your Contact page

**Yoast SEO** (Optional but recommended)
1. Install and activate Yoast SEO
2. Run the configuration wizard
3. Optimize your pages

**Akismet** (Spam protection)
1. Pre-installed with WordPress
2. Activate and configure with API key

#### Optional Plugins

- **Jetpack**: Site stats, security, backups
- **WP Super Cache**: Page caching for performance
- **Smush**: Image optimization
- **Wordfence**: Security

### 6. Upload Background Images

1. Prepare images (1920x1080px minimum):
   - `hero-bg.jpg`
   - `about-bg.jpg`
   - `programs-bg.jpg`
   - `contact-bg.jpg`

2. Upload to `/wp-content/themes/prospergenics-wp-theme/images/`

3. Or set via Customizer (Hero Section background)

### 7. Configure Widgets (Optional)

1. Go to Appearance > Widgets
2. Add widgets to Footer Area 1, 2, and 3:
   - **Footer Area 1**: Text widget with site description
   - **Footer Area 2**: Custom Menu widget with footer menu
   - **Footer Area 3**: Text widget with contact information

### 8. Create Sample Blog Posts

1. Go to Posts > Add New
2. Create 3-5 sample blog posts
3. Add featured images (800x500px recommended)
4. Assign categories
5. Publish

### 9. Set Permalinks

1. Go to Settings > Permalinks
2. Choose "Post name" structure
3. Click "Save Changes"

### 10. Test the Site

#### Desktop Testing
- Test all navigation links
- Verify parallax scrolling works
- Test contact form submission
- Check all pages load correctly
- Verify social media links

#### Mobile Testing
- Test mobile menu toggle
- Verify responsive layout
- Check touch interactions
- Test form on mobile

#### Accessibility Testing
- Test keyboard navigation (Tab key)
- Verify skip link works
- Check color contrast
- Test with screen reader

## Customization Tips

### Change Colors

Add this to Appearance > Customize > Additional CSS:

```css
:root {
    --color-green: #YOUR_COLOR;
    --color-terracotta: #YOUR_COLOR;
}
```

### Add Custom Fonts

1. Use a plugin like "Easy Google Fonts"
2. Or add via Additional CSS:

```css
@import url('https://fonts.googleapis.com/css2?family=Your+Font&display=swap');

:root {
    --font-primary: 'Your Font', sans-serif;
}
```

### Modify Section Content

Edit `front-page.php` to customize:
- Hero text
- Core values
- About content
- Programs
- Stats

## Troubleshooting

### Parallax Not Working
- Ensure JavaScript is enabled
- Check browser console for errors
- Try different browser

### Contact Form Not Sending
- Install Contact Form 7 plugin
- Check email settings in WordPress
- Verify SMTP is configured

### Images Not Showing
- Check file paths are correct
- Verify images are uploaded
- Clear cache and reload

### Menu Not Appearing
- Create menu in Appearance > Menus
- Assign to "Primary Menu" location
- Save menu

## Support Resources

- WordPress Codex: https://codex.wordpress.org/
- Theme Support Forum: (add your support URL)
- Contact: Use contact form on website

## Next Steps

After installation:
1. Add your real content
2. Upload high-quality images
3. Configure SEO settings
4. Set up backups
5. Enable SSL certificate
6. Test performance
7. Launch!

---

**Congratulations!** Your Prospergenics website is ready to empower your community.
