# Screenshot Instructions

WordPress requires a theme screenshot for the theme selection screen in the admin area.

## Requirements

- **Filename**: `screenshot.png` (must be in theme root directory)
- **Size**: 1200x900 pixels (4:3 aspect ratio)
- **Format**: PNG or JPG
- **Content**: Representative preview of the theme

## How to Create

### Option 1: Take a Screenshot of Your Live Site

1. Open your site in a browser at full width (1920px or wider)
2. Navigate to the homepage
3. Take a full-page screenshot using:
   - **Chrome**: DevTools > Cmd/Ctrl + Shift + P > "Capture full size screenshot"
   - **Firefox**: Right-click > Take Screenshot > Save full page
   - **Extension**: Use "Full Page Screen Capture" extension

4. Crop the screenshot to 1200x900px showing the hero section and first content section

5. Save as `screenshot.png`

6. Place in the theme root: `/wp-content/themes/prospergenics-wp-theme/screenshot.png`

### Option 2: Use Existing Team Photo

If you have a team photo that represents Prospergenics:

1. Resize image to 1200x900px
2. Optionally add theme name overlay in image editor
3. Save as `screenshot.png`
4. Place in theme root

### Option 3: Create a Mockup

1. Use a design tool (Figma, Photoshop, Canva)
2. Create 1200x900px canvas
3. Add:
   - Theme name: "Prospergenics"
   - Tagline: "Building Prosperity Together"
   - Representative colors (green #2E8B57, terracotta #b45309)
   - Sample layout or team image
4. Export as PNG
5. Save to theme root

## Image Optimization

Before adding to theme:
1. Compress the image (use TinyPNG.com or similar)
2. Aim for file size <500KB
3. Ensure image quality is still good

## Verification

After adding screenshot.png:
1. Go to WordPress Admin > Appearance > Themes
2. You should see the screenshot as the theme preview
3. If not visible, check:
   - Filename is exactly `screenshot.png`
   - File is in theme root (not in subdirectory)
   - File permissions allow reading (644)

## Placeholder

Until you create a proper screenshot, WordPress will show a default placeholder image. The theme will work fine without it, but adding one makes the theme look more professional in the admin area.

## Sample Content

The screenshot should ideally show:
- Hero section with headline
- Part of the first content section
- Navigation bar
- Theme colors and typography
- Professional, clean appearance

---

**Note**: The screenshot is only for the WordPress admin theme selector. It does not affect the front-end appearance of your site.
