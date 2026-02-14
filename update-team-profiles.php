<?php
/**
 * Update Prospergenics Team Profiles
 * Run this once via: curl http://localhost/prospergenics/wp-content/themes/prospergenics-wp-theme/temp/update-team-profiles.php
 */

require_once('E:/xampp/htdocs/wp-load.php');

// Updated team member content
$team_updates = [
    // COACHES
    'Martien de Jong' => [
        'content' => "Founder and technical coach at Prospergenics. Martien bridges technology and people through AI innovation, teaching tools like Cursor, Claude Code, and React to empower the next generation of African developers. With decades of experience in software development and international collaboration, he creates learning pathways that transform students into professional developers.",
        'featured_image' => 'martien.jpg'
    ],

    // PARTICIPANTS (Core Team - 2024-2025)
    'Lessy' => [
        'content' => "First Kenyan participant (2024). Programming student who pioneered Prospergenics in Kenya. Rapidly mastering React and Cursor while building real-world applications. Lessy represents the beginning of a movement—proof that with the right tools and mentorship, African developers can compete globally.",
        'featured_image' => 'lessy_profile.png'
    ],

    'Frank Kobaai' => [
        'content' => "From pharmaceutical sciences to web development (2024). Frank discovered his passion for coding and joined Prospergenics shortly after Lessy. Specializes in modern development workflows with Warp.dev, building elegant solutions with React and contemporary web technologies. His transition shows that it's never too late to follow your true calling.",
        'featured_image' => 'frank_profile.png'
    ],

    'Diko' => [
        'content' => "Project manager turned developer and SCRUM master (2024). Studied project management before discovering the power of code. Now leads development sprints with Claude Code while writing production-ready applications. Diko bridges the gap between technical execution and strategic planning, ensuring projects deliver real value.",
        'featured_image' => 'diko_profile.png'
    ],

    'Sandra Simitia Mpoe' => [
        'content' => "Organization and development leader (2025). Sandra combines programming skills with exceptional organizational leadership to scale Prospergenics across Kenya. She ensures the community runs smoothly while continuously improving her development skills with Cursor and React. Her leadership transforms individual success into collective progress.",
        'featured_image' => 'sandra_profile.png'
    ]
];

echo "<h1>Updating Team Profiles</h1>\n";

foreach ($team_updates as $title => $data) {
    // Find post by title
    $post = get_page_by_title($title, OBJECT, 'page');

    if ($post) {
        // Update content
        wp_update_post([
            'ID' => $post->ID,
            'post_content' => $data['content']
        ]);

        // Update featured image
        $image_path = 'E:/xampp/htdocs/wp-content/themes/prospergenics-wp-theme/images/' . $data['featured_image'];

        if (file_exists($image_path)) {
            // Check if already attached
            $existing_attachment = get_post_thumbnail_id($post->ID);

            if (!$existing_attachment) {
                // Upload to media library
                $upload = wp_upload_bits($data['featured_image'], null, file_get_contents($image_path));

                if (!$upload['error']) {
                    $attachment = [
                        'post_mime_type' => wp_check_filetype($upload['file'])['type'],
                        'post_title' => $title,
                        'post_content' => '',
                        'post_status' => 'inherit'
                    ];

                    $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post->ID);
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
                    wp_update_attachment_metadata($attachment_id, $attachment_data);
                    set_post_thumbnail($post->ID, $attachment_id);

                    echo "✓ Updated {$title} (ID: {$post->ID}) with image<br>\n";
                } else {
                    echo "✗ Image upload failed for {$title}: {$upload['error']}<br>\n";
                }
            } else {
                echo "✓ Updated {$title} (ID: {$post->ID}) - image already set<br>\n";
            }
        } else {
            echo "✗ Image not found: {$image_path}<br>\n";
        }
    } else {
        echo "✗ Post not found: {$title}<br>\n";
    }
}

echo "<h2>Done!</h2>\n";

// Self-delete
@unlink(__FILE__);
?>
