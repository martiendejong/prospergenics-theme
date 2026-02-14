# Prospergenics Theme - Quick Start Guide

Get your Prospergenics website up and running in 15 minutes.

## 1. Install Theme (2 minutes)

1. Upload theme to `/wp-content/themes/prospergenics-wp-theme/`
2. Activate in WordPress Admin > Appearance > Themes

## 2. Basic Settings (3 minutes)

**Site Identity** (Appearance > Customize > Site Identity)
- Site Title: Prospergenics
- Tagline: Building Prosperity Together
- Upload logo (optional)

**Hero Section** (Appearance > Customize > Hero Section)
- Headline: Building Prosperity Together
- Tagline: Empowering Futures, Cultivating Growth
- Description: Join our diverse community where learning, teamwork, and growth lead to real success.
- Upload background image (1920x1080px)

## 3. Create Pages (5 minutes)

Create these pages with "Publish" button:

1. **Home** (leave content empty)
2. **About** (add your about text)
3. **Our Team** (add team info)
4. **Our Programs** (add program details)
5. **Blog** (leave empty)
6. **Contact** (add contact form shortcode if using CF7)

**Set Homepage:**
- Settings > Reading > Static Page
- Homepage: Home
- Posts page: Blog

## 4. Create Menu (2 minutes)

**Appearance > Menus**
1. Create "Primary Menu"
2. Add pages: Home, About, Our Team, Our Programs, Blog, Contact
3. Check "Primary Menu" location
4. Save

## 5. Install Contact Form 7 (3 minutes)

1. Plugins > Add New > Search "Contact Form 7"
2. Install and Activate
3. Contact > Contact Forms > Add New
4. Use this template:

```
<label> Your Name (required)
    [text* your-name] </label>

<label> Your Email (required)
    [email* your-email] </label>

<label> Phone
    [tel your-phone] </label>

<label> Subject (required)
    [text* your-subject] </label>

<label> Your Message (required)
    [textarea* your-message] </label>

[submit "Send Message"]
```

5. Save and copy shortcode
6. Add shortcode to Contact page

## 6. Configure Permalinks

Settings > Permalinks > Post name > Save

## Done!

Visit your site. You should see:
- Parallax hero section
- Core values cards
- About section
- Programs
- Stats
- Contact form

## Next Steps (Optional)

- Add social media links (Customizer > Social Media)
- Upload background images for other sections
- Create blog posts
- Install Yoast SEO
- Add Google Analytics
- Set up backups

## Need Help?

See full documentation:
- `README.md` - Theme features and overview
- `INSTALLATION.md` - Detailed installation guide
- `DEPLOYMENT_CHECKLIST.md` - Pre-launch checklist

---

**That's it!** Your Prospergenics site is live.
