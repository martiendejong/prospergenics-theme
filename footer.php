    <footer id="colophon" class="site-footer">
        <div class="footer-content">
            <div class="footer-section">
                <h4>Prospergenics</h4>
                <p>Building prosperity together through authentic collaboration and shared skills.</p>
                <p>Empowering communities worldwide to achieve sustainable growth and success.</p>
            </div>
            
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul style="list-style: none;">
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#programs">Our Programs</a></li>
                    <li><a href="#impact">Our Impact</a></li>
                    <li><a href="#join">Join Community</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>Programs</h4>
                <ul style="list-style: none;">
                    <li><a href="#">AI & Technology Training</a></li>
                    <li><a href="#">Community Development</a></li>
                    <li><a href="#">Entrepreneurship Support</a></li>
                    <li><a href="#">Free Resources</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>Connect With Us</h4>
                <ul style="list-style: none;">
                    <li><a href="#">Contact</a></li>
                    <li><a href="#">Newsletter</a></li>
                    <li><a href="#">Community Forum</a></li>
                    <li><a href="#">Support</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Prospergenics. All rights reserved. | 
               <a href="#">Privacy Policy</a> | 
               <a href="#">Terms of Service</a>
            </p>
            <p>Built with ❤️ for empowering communities worldwide.</p>
        </div>
    </footer>
</div><!-- #page -->

<script>
// Simple JavaScript for smooth scrolling and animations
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for anchor links
    const links = document.querySelectorAll('a[href^="#"]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add fade-in animation on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
            }
        });
    }, observerOptions);
    
    // Observe elements for animation
    const animateElements = document.querySelectorAll('.value-card, .stat-item, .about-content');
    animateElements.forEach(el => observer.observe(el));
    
    // Add hover effects to CTA buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<?php wp_footer(); ?>

</body>
</html>

