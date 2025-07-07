<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Theme Styles -->
    <link rel="stylesheet" href="<?php echo get_stylesheet_uri(); ?>">
    
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">
    <a class="skip-link sr-only" href="#main"><?php esc_html_e('Skip to content', 'prospergenics'); ?></a>

    <header id="masthead" class="site-header">
        <div class="header-container">
            <div class="site-branding">
                <?php if (has_custom_logo()) : ?>
                    <div class="site-logo">
                        <?php the_custom_logo(); ?>
                    </div>
                <?php else : ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo" rel="home">
                        <?php bloginfo('name'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <nav id="site-navigation" class="main-navigation">
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'menu-1',
                    'menu_id'        => 'primary-menu',
                    'fallback_cb'    => 'prospergenics_default_menu',
                ));
                ?>
            </nav>
        </div>
    </header>

<?php
/**
 * Default menu fallback if no menu is assigned
 */
function prospergenics_default_menu() {
    echo '<ul id="primary-menu" class="menu">';
    echo '<li><a href="#values">Our Values</a></li>';
    echo '<li><a href="#impact">Our Impact</a></li>';
    echo '<li><a href="#about">About</a></li>';
    echo '<li><a href="#programs">Programs</a></li>';
    echo '<li><a href="#join">Join Us</a></li>';
    echo '</ul>';
}
?>

