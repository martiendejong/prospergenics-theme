<footer class="site-footer" role="contentinfo">
    <div class="footer-content">
        <?php if ( is_active_sidebar( 'footer-1' ) || is_active_sidebar( 'footer-2' ) || is_active_sidebar( 'footer-3' ) ) : ?>
            <?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
                <div class="footer-section">
                    <?php dynamic_sidebar( 'footer-1' ); ?>
                </div>
            <?php endif; ?>

            <?php if ( is_active_sidebar( 'footer-2' ) ) : ?>
                <div class="footer-section">
                    <?php dynamic_sidebar( 'footer-2' ); ?>
                </div>
            <?php endif; ?>

            <?php if ( is_active_sidebar( 'footer-3' ) ) : ?>
                <div class="footer-section">
                    <?php dynamic_sidebar( 'footer-3' ); ?>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <!-- Default Footer Content -->
            <div class="footer-section">
                <h3><?php bloginfo( 'name' ); ?></h3>
                <p><?php bloginfo( 'description' ); ?></p>
                <p><?php esc_html_e( 'Empowering communities through learning, collaboration, and sustainable growth.', 'prospergenics' ); ?></p>
            </div>

            <div class="footer-section">
                <h3><?php esc_html_e( 'Quick Links', 'prospergenics' ); ?></h3>
                <?php
                wp_nav_menu( array(
                    'theme_location' => 'footer',
                    'menu_id'        => 'footer-menu',
                    'container'      => false,
                    'fallback_cb'    => 'prospergenics_footer_fallback_menu',
                ) );
                ?>
            </div>

            <div class="footer-section">
                <h3><?php esc_html_e( 'Contact Us', 'prospergenics' ); ?></h3>
                <?php if ( get_theme_mod( 'prospergenics_email' ) ) : ?>
                    <p>
                        <?php esc_html_e( 'Email:', 'prospergenics' ); ?>
                        <a href="mailto:<?php echo esc_attr( get_theme_mod( 'prospergenics_email' ) ); ?>">
                            <?php echo esc_html( get_theme_mod( 'prospergenics_email' ) ); ?>
                        </a>
                    </p>
                <?php endif; ?>

                <?php if ( get_theme_mod( 'prospergenics_phone' ) ) : ?>
                    <p>
                        <?php esc_html_e( 'Phone:', 'prospergenics' ); ?>
                        <a href="tel:<?php echo esc_attr( str_replace( ' ', '', get_theme_mod( 'prospergenics_phone' ) ) ); ?>">
                            <?php echo esc_html( get_theme_mod( 'prospergenics_phone' ) ); ?>
                        </a>
                    </p>
                <?php endif; ?>

                <?php
                $social_links = prospergenics_get_social_links();
                if ( ! empty( $social_links ) ) :
                ?>
                    <div class="social-links">
                        <?php foreach ( $social_links as $network => $data ) : ?>
                            <a href="<?php echo esc_url( $data['url'] ); ?>"
                               target="_blank"
                               rel="noopener noreferrer"
                               aria-label="<?php echo esc_attr( $data['label'] ); ?>">
                                <span aria-hidden="true"><?php echo esc_html( substr( $data['label'], 0, 1 ) ); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer-bottom">
        <p>
            &copy; <?php echo esc_html( date( 'Y' ) ); ?>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                <?php bloginfo( 'name' ); ?>
            </a>.
            <?php esc_html_e( 'All rights reserved.', 'prospergenics' ); ?>
        </p>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

<?php
/**
 * Fallback footer menu
 */
function prospergenics_footer_fallback_menu() {
    echo '<ul id="footer-menu" class="menu">';
    echo '<li class="menu-item"><a href="' . esc_url( home_url( '/privacy-policy' ) ) . '">' . esc_html__( 'Privacy Policy', 'prospergenics' ) . '</a></li>';
    echo '<li class="menu-item"><a href="' . esc_url( home_url( '/terms' ) ) . '">' . esc_html__( 'Terms of Service', 'prospergenics' ) . '</a></li>';
    echo '</ul>';
}
?>
