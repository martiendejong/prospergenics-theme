<?php
/**
 * The main template file for Prospergenics theme
 * Shows homepage sections on front page, fallback to posts on old-style setups
 */
get_header(); ?>

<?php if (isset($_GET['contact'])): ?>
    <?php if ($_GET['contact'] === 'success'): ?>
        <div id="contact-message" style="position:fixed;top:20px;right:20px;background:#4CAF50;color:white;padding:1rem;border-radius:8px;z-index:10000;box-shadow:0 4px 12px rgba(0,0,0,0.15);max-width:400px;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <strong>✅ Success!</strong><br>
                    Your message has been sent successfully. We'll get back to you soon!
                </div>
                <button onclick="this.parentElement.parentElement.style.display='none'" style="background:none;border:none;color:white;margin-left:1rem;cursor:pointer;font-size:1.2rem;padding:5px;">&times;</button>
            </div>
        </div>
    <?php elseif ($_GET['contact'] === 'error'): ?>
        <?php 
        $error_reason = $_GET['reason'] ?? 'unknown';
        $error_messages = [
            'invalid_action' => 'Invalid form submission.',
            'security_failed' => 'Security check failed. Please try again.',
            'missing_fields' => 'Please fill in all required fields.',
            'invalid_email' => 'Please enter a valid email address.',
            'database_error' => 'Error saving your message. Please try again.',
            'unknown' => 'An error occurred. Please try again.'
        ];
        $error_message = $error_messages[$error_reason] ?? $error_messages['unknown'];
        ?>
        <div id="contact-message" style="position:fixed;top:20px;right:20px;background:#f44336;color:white;padding:1rem;border-radius:8px;z-index:10000;box-shadow:0 4px 12px rgba(0,0,0,0.15);max-width:400px;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <strong>❌ Error!</strong><br>
                    <?php echo esc_html($error_message); ?>
                </div>
                <button onclick="this.parentElement.parentElement.style.display='none'" style="background:none;border:none;color:white;margin-left:1rem;cursor:pointer;font-size:1.2rem;padding:5px;">&times;</button>
            </div>
        </div>
    <?php endif; ?>
    <script>
        setTimeout(function() {
            var message = document.getElementById('contact-message');
            if (message) {
                message.style.display = 'none';
            }
        }, 8000);
    </script>
<?php endif; ?>
<main id="main" class="site-main">
<?php if ( is_front_page() ) : // Show custom homepage sections on front page only ?>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-container">
            <h1 class="hero-title fade-in-up"><?php echo esc_html(get_theme_mod('prospergenics_hero_title', 'Building Prosperity Together')); ?></h1>
            <p class="hero-subtitle fade-in-up"><?php echo esc_html(get_theme_mod('prospergenics_hero_subtitle', 'Empowering Futures, Cultivating Growth')); ?></p>
            <p class="hero-description fade-in-up"><?php echo esc_html(get_theme_mod('prospergenics_hero_description', 'Join our diverse community—where learning, teamwork, and growth lead to real success. Discover the unique \'genetics\' of prosperity and help shape success for yourself and others.')); ?></p>
            <div class="cta-container fade-in-up">
                <a href="#" id="join-movement-btn" class="btn btn-primary"><?php echo esc_html(get_theme_mod('prospergenics_primary_cta_text', 'Join the Movement')); ?></a>
                <a href="<?php echo esc_url(get_theme_mod('prospergenics_secondary_cta_url', '#programs')); ?>" class="btn btn-secondary"><?php echo esc_html(get_theme_mod('prospergenics_secondary_cta_text', 'Explore Our Programs')); ?></a>
            </div>
        </div>
    </section>
    <!-- Core Values Section -->
    <section class="core-values-section" id="values">
        <div class="section-container">
            <h2 class="section-title">Our Core Values</h2>
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon">👂</div>
                    <h3 class="value-title">We Listen and Act</h3>
                    <p class="value-description">
                        At Prospergenics, we respond quickly to the needs of our members. 
                        Your ideas shape our journey and drive meaningful change.
                    </p>
                </div>
                <div class="value-card">
                    <div class="value-icon">🛡️</div>
                    <h3 class="value-title">Quality You Can Trust</h3>
                    <p class="value-description">
                        We share skills and knowledge that make a real difference. 
                        No empty promises—just practical learning and tangible results.
                    </p>
                </div>
                <div class="value-card">
                    <div class="value-icon">🤝</div>
                    <h3 class="value-title">Support in Every Step</h3>
                    <p class="value-description">
                        Whether you're taking your first step or making your next leap, 
                        you're never alone. Our community has your back, always.
                    </p>
                </div>
            </div>
        </div>
    </section>
    <!-- Impact Section -->
    <section class="impact-section" id="impact">
        <div class="section-container">
            <h2 class="section-title">Our Impact</h2>
            <div class="impact-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo esc_html(get_theme_mod('prospergenics_stat1_number', '500+')); ?></span>
                    <span class="stat-label"><?php echo esc_html(get_theme_mod('prospergenics_stat1_label', 'Individuals Trained')); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo esc_html(get_theme_mod('prospergenics_stat2_number', '25+')); ?></span>
                    <span class="stat-label"><?php echo esc_html(get_theme_mod('prospergenics_stat2_label', 'Communities Impacted')); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo esc_html(get_theme_mod('prospergenics_stat3_number', '100+')); ?></span>
                    <span class="stat-label"><?php echo esc_html(get_theme_mod('prospergenics_stat3_label', 'Successful Projects')); ?></span>
                </div>
            </div>
        </div>
    </section>
    <!-- About Section -->
    <section class="about-section" id="about">
        <div class="section-container">
            <div class="about-content">
                <div class="about-text">
                    <h2><?php echo esc_html(get_theme_mod('prospergenics_about_title', 'About Prospergenics')); ?></h2>
                    <p><?php echo nl2br(esc_html(get_theme_mod('prospergenics_about_text', 'Prospergenics is a grassroots community where people from all backgrounds come together to learn, grow, and support each other on the journey to prosperity. We believe that prosperity is not reserved for a select few—it\'s something anyone can learn, develop, and share. Powered by an active team in Kenya and the Netherlands, Prospergenics is built on real connection and practical learning. Our members collaborate in real projects, share knowledge, and empower each other. Whether you are starting your career, looking to improve your skills, or want to give back—at Prospergenics, you are not alone.'))); ?></p>
                </div>
                <div class="about-image">
                    <?php if(get_theme_mod('prospergenics_about_image')): ?>
                        <img src="<?php echo esc_url(get_theme_mod('prospergenics_about_image')); ?>" alt="Prospergenics community members collaborating" />
                    <?php else: ?>
                        <img src="<?php echo get_template_directory_uri(); ?>/images/community-collaboration.jpg" alt="Prospergenics community members collaborating" />
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <!-- Programs Section -->
    <section class="core-values-section" id="programs">
        <div class="section-container">
            <h2 class="section-title"><?php echo esc_html(get_theme_mod('prospergenics_programs_title', 'Our Programs')); ?></h2>
            <p><?php echo nl2br(esc_html(get_theme_mod('prospergenics_programs_desc', ''))); ?></p>
            <div class="values-grid">
                <?php for($i=1;$i<=3;$i++): ?>
                <div class="value-card">
                    <div class="value-icon"><?php echo esc_html(get_theme_mod("prospergenics_program{$i}_icon", ($i==1?'💻':($i==2?'🌱':'📈')))); ?></div>
                    <h3 class="value-title"><?php echo esc_html(get_theme_mod("prospergenics_program{$i}_title", ($i==1?'AI & Technology Training':($i==2?'Community Development':'Entrepreneurship Support')))); ?></h3>
                    <p class="value-description"><?php echo esc_html(get_theme_mod("prospergenics_program{$i}_desc", ($i==1?'Learn cutting-edge AI and software development skills that open doors to new opportunities and economic empowerment.':($i==2?'Participate in real projects that create lasting impact in communities while building valuable skills and connections.':'Get guidance, resources, and mentorship to turn your ideas into sustainable businesses that benefit your community.')))); ?></p>
                    <a href="<?php echo esc_url(get_theme_mod("prospergenics_program{$i}_link", '#')); ?>" class="btn btn-primary" style="margin-top: 1rem;">Learn More</a>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
    <!-- Final CTA Section -->
    <section class="final-cta-section" id="join">
        <div class="section-container">
            <h2 class="final-cta-title"><?php echo esc_html(get_theme_mod('prospergenics_final_cta_title', 'Start Your Journey to Prosperity Today')); ?></h2>
            <p class="final-cta-description"><?php echo nl2br(esc_html(get_theme_mod('prospergenics_final_cta_text', 'Join thousands of individuals who are building a better future for themselves and their communities.'))); ?></p>
            <div class="cta-container">
                <a href="<?php echo esc_url(get_theme_mod('prospergenics_final_cta_primary_link', '#')); ?>" class="btn btn-primary"><?php echo esc_html(get_theme_mod('prospergenics_final_cta_primary', 'Join the Community')); ?></a>
                <a href="<?php echo esc_url(get_theme_mod('prospergenics_final_cta_secondary_link', '#')); ?>" class="btn btn-secondary"><?php echo esc_html(get_theme_mod('prospergenics_final_cta_secondary', 'Browse Free Trainings')); ?></a>
            </div>
        </div>
    </section>
<?php elseif ( have_posts() ) : // Fallback: show posts if not homepage ?>
    <?php while ( have_posts() ) : the_post(); ?>
        <?php get_template_part( 'content', get_post_format() ); ?>
    <?php endwhile; ?>
    <div class="posts-navigation">
        <?php the_posts_navigation(); ?>
    </div>
<?php else : ?>
    <section class="blog-section">
        <div class="section-container">
            <p><?php esc_html_e('No posts found.', 'prospergenics'); ?></p>
        </div>
    </section>
<?php endif; ?>
</main>
<!-- Contact Form Modal -->
<div id="contact-modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;padding:1.5rem;max-width:400px;width:85%;border-radius:12px;position:relative;box-shadow:0 10px 30px rgba(0,0,0,0.3);">
    <button id="close-contact-modal" style="position:absolute;top:15px;right:15px;font-size:1.5rem;background:none;border:none;cursor:pointer;color:#666;padding:5px;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;">&times;</button>
    <h2 style="margin-bottom:1rem;color:#2E8B57;font-size:1.5rem;">Contact Us</h2>
    <form id="contact-form" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
      <input type="hidden" name="action" value="prospergenics_send_contact_form">
      <?php wp_nonce_field('prospergenics_contact_form'); ?>
      <div class="contact-form-grid">
        <div class="contact-form-group">
          <label for="cf-name" style="display:block;margin-bottom:0.3rem;font-weight:600;color:#333;font-size:14px;">Name *</label>
          <input type="text" id="cf-name" name="cf_name" required style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;transition:border-color 0.3s ease;box-sizing:border-box;">
        </div>
        <div class="contact-form-group">
          <label for="cf-email" style="display:block;margin-bottom:0.3rem;font-weight:600;color:#333;font-size:14px;">Email *</label>
          <input type="email" id="cf-email" name="cf_email" required style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;transition:border-color 0.3s ease;box-sizing:border-box;">
        </div>
        <div class="contact-form-group">
          <label for="cf-phone" style="display:block;margin-bottom:0.3rem;font-weight:600;color:#333;font-size:14px;">Phone (Optional)</label>
          <input type="tel" id="cf-phone" name="cf_phone" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;transition:border-color 0.3s ease;box-sizing:border-box;">
        </div>
        <div class="contact-form-group">
          <label for="cf-subject" style="display:block;margin-bottom:0.3rem;font-weight:600;color:#333;font-size:14px;">Subject (Optional)</label>
          <input type="text" id="cf-subject" name="cf_subject" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;transition:border-color 0.3s ease;box-sizing:border-box;">
        </div>
        <div class="contact-form-group contact-form-group-full">
          <label for="cf-message" style="display:block;margin-bottom:0.3rem;font-weight:600;color:#333;font-size:14px;">Message *</label>
          <textarea id="cf-message" name="cf_message" required style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;transition:border-color 0.3s ease;box-sizing:border-box;height:100px;resize:vertical;font-family:inherit;"></textarea>
        </div>
      </div>
      <button type="submit" class="btn btn-primary contact-form-submit-btn" style="width:100%;padding:12px;font-size:14px;font-weight:600;border-radius:6px;border:none;cursor:pointer;transition:all 0.3s ease;margin-top:0.5rem;">Submit Registration</button>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var modal = document.getElementById('contact-modal');
  var btn = document.getElementById('join-movement-btn');
  var closeBtn = document.getElementById('close-contact-modal');
  var contactForm = document.getElementById('contact-form');
  
  // Open modal
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    modal.style.display = 'flex';
  });
  
  // Close modal
  closeBtn.addEventListener('click', function() {
    modal.style.display = 'none';
  });
  
  // Close modal when clicking outside
  window.onclick = function(event) {
    if (event.target == modal) {
      modal.style.display = 'none';
    }
  }
  
  // Form submission
  if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
      // Validate form before submission
      var name = document.getElementById('cf-name').value.trim();
      var email = document.getElementById('cf-email').value.trim();
      var message = document.getElementById('cf-message').value.trim();
      
      if (!name || !email || !message) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
      }
      
      // Basic email validation
      var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address.');
        return false;
      }
      
      // Show loading state
      var submitBtn = contactForm.querySelector('button[type="submit"]');
      var originalText = submitBtn.textContent;
      submitBtn.textContent = 'Sending...';
      submitBtn.disabled = true;
      submitBtn.style.opacity = '0.7';
      
      // Form will submit normally to WordPress admin-post.php
      // The success redirect will show the success message
    });
  }
  
  // Add focus styles for better UX
  var inputs = modal.querySelectorAll('input, textarea');
  inputs.forEach(function(input) {
    input.addEventListener('focus', function() {
      this.style.borderColor = '#2E8B57';
    });
    input.addEventListener('blur', function() {
      this.style.borderColor = '#e0e0e0';
    });
  });
});
</script>
<?php get_footer(); ?>
