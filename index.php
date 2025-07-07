<?php
/**
 * The main template file for Prospergenics theme
 */

get_header(); ?>

<main id="main" class="site-main">
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-container">
            <h1 class="hero-title fade-in-up">Building Prosperity Together</h1>
            <p class="hero-subtitle fade-in-up">Empowering Futures, Cultivating Growth</p>
            <p class="hero-description fade-in-up">
                Join our diverse community—where learning, teamwork, and growth lead to real success. 
                Discover the unique 'genetics' of prosperity and help shape success for yourself and others.
            </p>
            <div class="cta-container fade-in-up">
                <a href="#join" class="btn btn-primary">Join the Movement</a>
                <a href="#programs" class="btn btn-secondary">Explore Our Programs</a>
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
                    <span class="stat-number">500+</span>
                    <span class="stat-label">Individuals Trained</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">25+</span>
                    <span class="stat-label">Communities Impacted</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">100+</span>
                    <span class="stat-label">Successful Projects</span>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-section" id="about">
        <div class="section-container">
            <div class="about-content">
                <div class="about-text">
                    <h2>About Prospergenics</h2>
                    <p>
                        Prospergenics is a grassroots community where people from all backgrounds 
                        come together to learn, grow, and support each other on the journey to prosperity. 
                        We believe that prosperity is not reserved for a select few—it's something 
                        anyone can learn, develop, and share.
                    </p>
                    <p>
                        Powered by an active team in Kenya and the Netherlands, Prospergenics is built 
                        on real connection and practical learning. Our members collaborate in real projects, 
                        share knowledge, and empower each other.
                    </p>
                    <p>
                        Whether you are starting your career, looking to improve your skills, or want 
                        to give back—at Prospergenics, you are not alone.
                    </p>
                </div>
                <div class="about-image">
                    <img src="<?php echo get_template_directory_uri(); ?>/images/community-collaboration.jpg" 
                         alt="Prospergenics community members collaborating" />
                </div>
            </div>
        </div>
    </section>

    <!-- Programs Section -->
    <section class="core-values-section" id="programs">
        <div class="section-container">
            <h2 class="section-title">Our Programs</h2>
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon">💻</div>
                    <h3 class="value-title">AI & Technology Training</h3>
                    <p class="value-description">
                        Learn cutting-edge AI and software development skills that open doors 
                        to new opportunities and economic empowerment.
                    </p>
                    <a href="#" class="btn btn-primary" style="margin-top: 1rem;">Learn More</a>
                </div>
                <div class="value-card">
                    <div class="value-icon">🌱</div>
                    <h3 class="value-title">Community Development</h3>
                    <p class="value-description">
                        Participate in real projects that create lasting impact in communities 
                        while building valuable skills and connections.
                    </p>
                    <a href="#" class="btn btn-primary" style="margin-top: 1rem;">Learn More</a>
                </div>
                <div class="value-card">
                    <div class="value-icon">📈</div>
                    <h3 class="value-title">Entrepreneurship Support</h3>
                    <p class="value-description">
                        Get guidance, resources, and mentorship to turn your ideas into 
                        sustainable businesses that benefit your community.
                    </p>
                    <a href="#" class="btn btn-primary" style="margin-top: 1rem;">Learn More</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA Section -->
    <section class="final-cta-section" id="join">
        <div class="section-container">
            <h2 class="final-cta-title">Start Your Journey to Prosperity Today</h2>
            <p class="final-cta-description">
                Join thousands of individuals who are building a better future for themselves and their communities.
            </p>
            <div class="cta-container">
                <a href="#" class="btn btn-primary">Join the Community</a>
                <a href="#" class="btn btn-secondary">Browse Free Trainings</a>
            </div>
        </div>
    </section>

</main>

<?php get_footer(); ?>

