/**
 * File customizer.js.
 *
 * Theme Customizer enhancements for a better user experience.
 *
 * Contains handlers to make Theme Customizer preview reload changes asynchronously.
 */

(function($) {
    // Site title and description.
    wp.customize('blogname', function(value) {
        value.bind(function(to) {
            $('.site-title a').text(to);
        });
    });
    
    wp.customize('blogdescription', function(value) {
        value.bind(function(to) {
            $('.site-description').text(to);
        });
    });

    // Hero section
    wp.customize('prospergenics_hero_title', function(value) {
        value.bind(function(to) {
            $('.hero-title').text(to);
        });
    });

    wp.customize('prospergenics_hero_subtitle', function(value) {
        value.bind(function(to) {
            $('.hero-subtitle').text(to);
        });
    });

    wp.customize('prospergenics_hero_description', function(value) {
        value.bind(function(to) {
            $('.hero-description').text(to);
        });
    });

    // CTA buttons
    wp.customize('prospergenics_primary_cta_text', function(value) {
        value.bind(function(to) {
            $('.cta-container .btn-primary').text(to);
        });
    });

    wp.customize('prospergenics_secondary_cta_text', function(value) {
        value.bind(function(to) {
            $('.cta-container .btn-secondary').text(to);
        });
    });

    // Colors
    wp.customize('prospergenics_primary_color', function(value) {
        value.bind(function(to) {
            $('head').append('<style>:root { --primary-green: ' + to + '; }</style>');
        });
    });

    wp.customize('prospergenics_accent_color', function(value) {
        value.bind(function(to) {
            $('head').append('<style>:root { --accent-gold: ' + to + '; }</style>');
        });
    });

    // Impact stats
    wp.customize('prospergenics_stat1_number', function(value) {
        value.bind(function(to) {
            $('.stat-item:nth-child(1) .stat-number').text(to);
        });
    });

    wp.customize('prospergenics_stat1_label', function(value) {
        value.bind(function(to) {
            $('.stat-item:nth-child(1) .stat-label').text(to);
        });
    });

    wp.customize('prospergenics_stat2_number', function(value) {
        value.bind(function(to) {
            $('.stat-item:nth-child(2) .stat-number').text(to);
        });
    });

    wp.customize('prospergenics_stat2_label', function(value) {
        value.bind(function(to) {
            $('.stat-item:nth-child(2) .stat-label').text(to);
        });
    });

    wp.customize('prospergenics_stat3_number', function(value) {
        value.bind(function(to) {
            $('.stat-item:nth-child(3) .stat-number').text(to);
        });
    });

    wp.customize('prospergenics_stat3_label', function(value) {
        value.bind(function(to) {
            $('.stat-item:nth-child(3) .stat-label').text(to);
        });
    });

    // Footer
    wp.customize('prospergenics_footer_text', function(value) {
        value.bind(function(to) {
            $('.footer-section:first-child p:first-of-type').text(to);
        });
    });

    // Header text color.
    wp.customize('header_textcolor', function(value) {
        value.bind(function(to) {
            if ('blank' === to) {
                $('.site-title, .site-description').css({
                    'clip': 'rect(1px, 1px, 1px, 1px)',
                    'position': 'absolute'
                });
            } else {
                $('.site-title, .site-description').css({
                    'clip': 'auto',
                    'position': 'relative'
                });
                $('.site-title a, .site-description').css({
                    'color': to
                });
            }
        });
    });
})(jQuery);

