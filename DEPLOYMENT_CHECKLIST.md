# Prospergenics Theme Deployment Checklist

Use this checklist before deploying the theme to production.

## Pre-Deployment Checks

### Theme Files
- [ ] All template files present and tested
- [ ] style.css has correct theme information
- [ ] functions.php loads without errors
- [ ] No PHP errors in error log
- [ ] All images optimized (<500KB each)
- [ ] screenshot.png added (1200x900px)

### Content
- [ ] All pages created and published
- [ ] Navigation menus configured
- [ ] Homepage set to static page
- [ ] Blog page assigned
- [ ] Sample posts created with featured images
- [ ] About page content finalized
- [ ] Contact page working

### Settings
- [ ] Site title and tagline set
- [ ] Logo uploaded
- [ ] Hero section configured in Customizer
- [ ] Contact information added
- [ ] Social media links added
- [ ] Permalinks set to "Post name"
- [ ] Timezone configured

### Forms
- [ ] Contact Form 7 installed and activated
- [ ] Contact form created and tested
- [ ] Form submissions received successfully
- [ ] Email notifications working
- [ ] Thank you message displays correctly

### SEO
- [ ] Yoast SEO or similar plugin installed
- [ ] Meta descriptions added to key pages
- [ ] XML sitemap generated
- [ ] Google Analytics code added (if applicable)
- [ ] Favicon uploaded

### Security
- [ ] WordPress core updated to latest version
- [ ] All plugins updated
- [ ] Strong admin password set
- [ ] Akismet configured for spam protection
- [ ] Security plugin installed (Wordfence/iThemes Security)
- [ ] File permissions correct (755 for directories, 644 for files)
- [ ] wp-config.php secured

### Performance
- [ ] Images optimized (Smush or similar)
- [ ] Caching plugin installed (WP Super Cache/W3 Total Cache)
- [ ] Database optimized
- [ ] Unused plugins deleted
- [ ] Site loads in <3 seconds

### Mobile & Responsive
- [ ] Tested on iPhone (Safari)
- [ ] Tested on Android (Chrome)
- [ ] Tested on tablet (iPad)
- [ ] Mobile menu works correctly
- [ ] All images scale properly
- [ ] Touch interactions work
- [ ] Forms work on mobile

### Browser Testing
- [ ] Chrome (latest version)
- [ ] Firefox (latest version)
- [ ] Safari (latest version)
- [ ] Edge (latest version)
- [ ] No console errors

### Accessibility
- [ ] Keyboard navigation tested
- [ ] Skip link works
- [ ] All images have alt text
- [ ] Color contrast meets WCAG 2.1 AA
- [ ] Forms have proper labels
- [ ] Heading hierarchy is correct
- [ ] ARIA labels on interactive elements

### Functionality Testing
- [ ] All internal links work
- [ ] External links open in new tab
- [ ] Contact form submits successfully
- [ ] Search function works
- [ ] Comments work (if enabled)
- [ ] Social media links work
- [ ] 404 page displays correctly
- [ ] Pagination works

### Content Review
- [ ] No lorem ipsum placeholder text
- [ ] All spelling and grammar checked
- [ ] No broken images
- [ ] No TODO or placeholder comments in code
- [ ] Copyright year is current
- [ ] Privacy policy page exists
- [ ] Terms of service page exists

### Backup
- [ ] Full site backup created
- [ ] Database backup created
- [ ] Theme files backed up
- [ ] Media files backed up
- [ ] Backup restoration tested

### SSL & Domain
- [ ] SSL certificate installed
- [ ] Site redirects HTTP to HTTPS
- [ ] Mixed content warnings resolved
- [ ] Domain properly configured
- [ ] WWW vs non-WWW decided and enforced

### Email
- [ ] WordPress email sending works
- [ ] SMTP configured (if needed)
- [ ] Admin email address correct
- [ ] Contact form emails arrive
- [ ] Test all automated emails

### Legal & Compliance
- [ ] Privacy policy updated
- [ ] Cookie notice added (if EU visitors)
- [ ] GDPR compliance checked (if applicable)
- [ ] Terms of service updated
- [ ] Copyright notices correct

### Final Steps
- [ ] Remove demo/test content
- [ ] Delete unused themes
- [ ] Delete unused plugins
- [ ] Clear all caches
- [ ] Test from incognito/private window
- [ ] Ask colleague to review
- [ ] Final client approval received

## Post-Deployment

### Immediate (Within 24 hours)
- [ ] Monitor error logs
- [ ] Check form submissions
- [ ] Verify Google Analytics tracking
- [ ] Test from different locations/networks
- [ ] Monitor site speed
- [ ] Check search engine indexing

### Week 1
- [ ] Review visitor behavior
- [ ] Check for 404 errors
- [ ] Monitor uptime
- [ ] Review security logs
- [ ] Check backup schedule

### Month 1
- [ ] Update WordPress core
- [ ] Update all plugins
- [ ] Review analytics data
- [ ] Gather user feedback
- [ ] Plan improvements

## Emergency Contacts

- **Hosting Support**: (add hosting provider contact)
- **Developer**: (add developer contact)
- **Domain Registrar**: (add registrar contact)

## Rollback Plan

If issues occur post-deployment:
1. Put site in maintenance mode
2. Restore from backup
3. Identify and fix issue
4. Test fix on staging
5. Redeploy

---

**Sign-off:**

- Developer: _________________ Date: _______
- Client: _________________ Date: _______
- QA Tester: _________________ Date: _______

**Deployment Date**: _____________

**Go-live approved**: [ ] Yes [ ] No
