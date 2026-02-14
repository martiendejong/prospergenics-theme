# Theme Packaging Instructions

How to package the Prospergenics theme for distribution or installation.

## Quick Package (For Installation)

### Option 1: ZIP File (Recommended)

**Windows:**
```powershell
# From parent directory (C:\Projects\)
Compress-Archive -Path "prospergenics-wp-theme" -DestinationPath "prospergenics-wp-theme.zip"
```

**Mac/Linux:**
```bash
# From parent directory
zip -r prospergenics-wp-theme.zip prospergenics-wp-theme -x "*.git*" "*.DS_Store" "node_modules/*"
```

**Manual:**
1. Right-click the `prospergenics-wp-theme` folder
2. Select "Send to" > "Compressed (zipped) folder"
3. Rename to `prospergenics-wp-theme.zip`

### Option 2: Direct Upload

If you have FTP/SFTP access:
1. Upload the entire `prospergenics-wp-theme` folder
2. Place in `/wp-content/themes/` directory
3. No need to create ZIP file

---

## Files to Include

### Essential Files (Must Include)
- ✅ style.css
- ✅ functions.php
- ✅ header.php
- ✅ footer.php
- ✅ front-page.php
- ✅ index.php
- ✅ single.php
- ✅ page.php
- ✅ archive.php
- ✅ 404.php
- ✅ searchform.php
- ✅ comments.php
- ✅ js/scripts.js
- ✅ inc/contact-form-handler.php

### Documentation (Recommended)
- ✅ README.md
- ✅ INSTALLATION.md
- ✅ QUICK_START.md
- ✅ DEPLOYMENT_CHECKLIST.md

### Directories
- ✅ images/ (even if empty)
- ✅ languages/ (for translations)
- ✅ inc/ (includes directory)
- ✅ js/ (JavaScript directory)

### Optional Files
- ⏳ screenshot.png (add before packaging if available)
- ⏳ .htaccess-recommended (for reference)
- ⏳ Background images (if you have them)

---

## Files to Exclude

Do NOT include:
- ❌ .git/ directory
- ❌ .gitignore
- ❌ .DS_Store (Mac)
- ❌ Thumbs.db (Windows)
- ❌ node_modules/
- ❌ .env files
- ❌ Personal notes
- ❌ Development files

---

## Before Packaging Checklist

- [ ] All template files present
- [ ] No syntax errors in PHP files
- [ ] JavaScript tested and working
- [ ] CSS validated
- [ ] Documentation complete
- [ ] Screenshot added (optional)
- [ ] Background images added (optional)
- [ ] Version number correct in style.css
- [ ] Copyright year current
- [ ] No sensitive data in files
- [ ] No TODO comments in code

---

## Package Verification

After creating the ZIP file:

1. **Test the ZIP file:**
   - Extract to a test location
   - Verify all files present
   - Check file structure intact

2. **Install on test site:**
   - Upload to WordPress
   - Activate theme
   - Check for errors
   - Verify all pages work

3. **File size check:**
   - Without images: ~50-100 KB
   - With images: <10 MB recommended

---

## Distribution Options

### For Client Installation
- Send ZIP file via email/file transfer
- Client uploads via WordPress Admin > Themes > Add New > Upload Theme

### For WordPress.org Submission
1. Review WordPress Theme Review Guidelines
2. Ensure GPL compatibility
3. Add proper licensing headers
4. Remove any premium/paid features
5. Submit through wordpress.org/themes/upload

### For Private Repository
- Upload to GitHub/GitLab
- Tag version (v1.0.0)
- Create release with ZIP file attached

### For Theme Shop
- Add demo content
- Create documentation site
- Prepare support system
- Set up licensing (if applicable)

---

## Version Control

When creating new versions:

1. Update version in `style.css`:
   ```css
   Version: 1.0.1
   ```

2. Update `functions.php` if you're using version-based cache busting

3. Document changes in CHANGELOG.md (create if needed)

4. Create new ZIP with version number:
   ```
   prospergenics-wp-theme-v1.0.1.zip
   ```

---

## Quick Commands

**Create ZIP (Windows PowerShell):**
```powershell
cd C:\Projects
Compress-Archive -Path "prospergenics-wp-theme" -DestinationPath "E:\jengo\documents\output\prospergenics-wp-theme.zip" -Force
```

**Create ZIP (Mac/Linux):**
```bash
cd /path/to/themes
zip -r prospergenics-wp-theme.zip prospergenics-wp-theme -x "*.git*"
```

**Verify ZIP contents:**
```powershell
# Windows
Expand-Archive -Path "prospergenics-wp-theme.zip" -DestinationPath "test-extract" -Force

# Mac/Linux
unzip -l prospergenics-wp-theme.zip
```

---

## Upload Methods

### WordPress Admin Upload
1. Log in to WordPress Admin
2. Go to Appearance > Themes
3. Click "Add New"
4. Click "Upload Theme"
5. Choose ZIP file
6. Click "Install Now"
7. Click "Activate"

### FTP Upload
1. Extract ZIP file locally
2. Upload folder to `/wp-content/themes/`
3. Activate in WordPress Admin

### WP-CLI Upload
```bash
wp theme install /path/to/prospergenics-wp-theme.zip --activate
```

---

## Final Package Location

Recommended output location:
```
E:\jengo\documents\output\prospergenics-wp-theme.zip
```

Or for client delivery:
```
E:\jengo\documents\output\prospergenics-theme-v1.0.0-final.zip
```

---

## Support Package

When sending to client, include:

1. **prospergenics-wp-theme.zip** (the theme)
2. **INSTALLATION.md** (setup guide)
3. **QUICK_START.md** (fast setup)
4. **Background images** (if prepared separately)
5. **Screenshot.png** (if created)
6. **Demo content** (optional XML export)

Create folder structure:
```
prospergenics-theme-package/
  ├── prospergenics-wp-theme.zip
  ├── Installation Guide.pdf (converted from INSTALLATION.md)
  ├── Quick Start.pdf (converted from QUICK_START.md)
  ├── background-images/
  │   ├── hero-bg.jpg
  │   ├── about-bg.jpg
  │   ├── programs-bg.jpg
  │   └── contact-bg.jpg
  └── screenshot.png
```

---

**Package ready!** Theme can now be distributed and installed on any WordPress site.
