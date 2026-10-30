<?php
defined('ABSPATH') || exit;
// Member profile at /in/{slug}/ (profiles track, cards #6343–#6354). Loaded by inc/profiles/routes.php.
// Every section is rendered only when the viewer may see it (inc/profiles/helpers.php privacy rules).
$owner     = prospergenics_profiles_current_owner();
$viewer_id = prospergenics_profiles_viewer_id();
$data      = prospergenics_profiles_public_data( $owner, $viewer_id );
$is_owner  = ! empty( $data['is_owner'] );
$preview   = get_current_user_id() === $owner->ID && ! $is_owner;
$editing   = $is_owner && isset( $_GET['edit'] );
$vis       = $data['visible'];
$profile_url = prospergenics_profiles_url( $owner->ID );
// The curated /people/ story this member is linked to (user meta _pg_people_slug, audit CON-11):
// linked from here, and its photo and role stand in while the account has none of its own.
$person      = prospergenics_profiles_person( $owner->ID );
$story_link  = $person ? '<a class="pgp-btn pgp-btn-light" href="' . esc_url( $person['url'] ) . '">' . esc_html( sprintf( 'Read %s’s story', $person['first_name'] ) ) . '</a>' : '';
get_header();
?>
<div class="pgp-root<?php echo $editing ? ' is-editing' : ''; ?>" data-pgp-root data-slug="<?php echo esc_attr( $data['slug'] ); ?>">
<?php if ( $preview ) : ?>
  <div class="pgp-preview-bar" role="status">
    <span>Preview: this is what a logged-out visitor sees.</span>
    <a href="<?php echo esc_url( $profile_url ); ?>">Back to your profile</a>
  </div>
<?php endif; ?>

<?php if ( ! $vis['profile'] ) : ?>
  <div class="post-hero">
    <div class="post-hero-inner">
      <div class="ph-eyebrow"><span class="dot"></span>Member profile</div>
      <h1>This profile is not public</h1>
      <p class="ph-excerpt">
        <?php if ( get_current_user_id() && ! $preview ) : ?>
          This member shares their profile with a limited group only.
        <?php else : ?>
          This member only shares their profile with signed-in members.
        <?php endif; ?>
      </p>
    </div>
  </div>
  <div class="post-wrap">
    <?php if ( $person ) : ?>
      <p class="empty-note"><a href="<?php echo esc_url( $person['url'] ); ?>"><?php echo esc_html( sprintf( 'Read %s’s story', $person['first_name'] ) ); ?></a></p>
    <?php endif; ?>
    <?php if ( ! get_current_user_id() ) : ?>
      <p class="empty-note"><a class="pgp-btn pgp-btn-primary" href="<?php echo esc_url( prospergenics_profiles_login_url( $profile_url ) ); ?>">Log in to see more</a></p>
    <?php else : ?>
      <p class="empty-note"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to the homepage</a></p>
    <?php endif; ?>
  </div>
<?php else : ?>

  <div class="post-hero pgp-hero" data-pgp-region="hero">
    <div class="pgp-hero-inner">
      <div class="pgp-photo-wrap">
        <?php if ( ! empty( $data['photo'] ) ) : ?>
          <img class="pgp-photo" src="<?php echo esc_url( $data['photo'] ); ?>" alt="<?php echo esc_attr( $data['name'] ); ?>" width="160" height="160" />
        <?php elseif ( $person && '' !== $person['photo'] ) : ?>
          <img class="pgp-photo" src="<?php echo esc_url( $person['photo'] ); ?>" alt="<?php echo esc_attr( $data['name'] ); ?>" width="160" height="160" style="object-fit: cover; object-position: <?php echo esc_attr( $person['pos'] ); ?>" />
        <?php else : ?>
          <span class="pgp-photo pgp-photo-ph" aria-hidden="true"><?php echo esc_html( prospergenics_profiles_initials( $data['name'] ) ); ?></span>
        <?php endif; ?>
        <?php if ( $is_owner ) : ?>
          <div class="pgp-photo-tools pgp-edit-only" id="pgp-photo">
            <input class="pgp-file-input" type="file" id="pgp-photo-input" accept="image/jpeg,image/png,image/webp,image/gif" data-pgp-photo-input data-pgp-upload="1" />
            <label class="pgp-btn pgp-btn-light pgp-file-btn" for="pgp-photo-input"><?php echo ! empty( $data['photo'] ) ? 'Change photo' : 'Upload photo'; ?></label>
            <?php if ( ! empty( $data['photo'] ) ) : ?>
              <button type="button" class="pgp-btn pgp-btn-light" data-pgp-delete="photo" data-refresh="hero sidebar" data-confirm="Remove your profile photo?">Remove photo</button>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="pgp-hero-text">
        <div class="ph-eyebrow"><span class="dot"></span>Member profile</div>
        <h1><?php echo esc_html( $data['name'] ); ?></h1>
        <?php if ( '' !== $data['headline'] ) : ?>
          <p class="pgp-headline"><?php echo esc_html( $data['headline'] ); ?></p>
        <?php elseif ( $is_owner ) : ?>
          <p class="pgp-headline pgp-muted-light">Add a headline so people know what you do.</p>
        <?php elseif ( $person ) : ?>
          <p class="pgp-headline"><?php echo esc_html( $person['role'] ); ?></p>
        <?php endif; ?>
        <?php if ( '' !== $data['location'] ) : ?>
          <p class="pgp-location"><?php echo esc_html( $data['location'] ); ?></p>
        <?php endif; ?>
        <p class="pgp-counts">
          <span><strong data-pgp-followers><?php echo (int) $data['followers']; ?></strong> <?php echo 1 === (int) $data['followers'] ? 'follower' : 'followers'; ?></span>
          <span><strong><?php echo (int) $data['following']; ?></strong> following</span>
        </p>
        <div class="pgp-hero-actions">
          <?php if ( $is_owner ) : ?>
            <button type="button" class="pgp-btn pgp-btn-on-dark" data-pgp-edit-toggle aria-pressed="<?php echo $editing ? 'true' : 'false'; ?>">
              <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
              <span data-pgp-label><?php echo $editing ? 'Done editing' : 'Edit profile'; ?></span>
            </button>
            <a class="pgp-btn pgp-btn-light" href="<?php echo esc_url( home_url( '/profile/settings/' ) ); ?>">Settings</a>
            <?php echo $story_link; // escaped above ?>
          <?php elseif ( get_current_user_id() && ! $preview ) : ?>
            <button type="button" class="pgp-btn pgp-btn-on-dark" data-pgp-follow="<?php echo esc_attr( $data['slug'] ); ?>" aria-pressed="<?php echo $data['followed_by_me'] ? 'true' : 'false'; ?>">
              <span data-pgp-label><?php echo $data['followed_by_me'] ? 'Following' : 'Follow'; ?></span><span class="screen-reader-text"> <?php echo esc_html( $data['name'] ); ?></span>
            </button>
          <?php elseif ( ! $preview ) : ?>
            <a class="pgp-btn pgp-btn-on-dark" href="<?php echo esc_url( prospergenics_profiles_login_url( $profile_url ) ); ?>">Log in to follow</a>
          <?php endif; ?>
          <?php if ( ! $is_owner ) { echo $story_link; } // escaped above ?>
          <?php if ( ! $is_owner && ! $preview && function_exists( 'prospergenics_profiles_report_html' ) ) { echo prospergenics_profiles_report_html( 'profile', $owner->ID ); } // #6472, escaped in the helper ?>
        </div>
      </div>
    </div>
  </div>

  <div class="pgp-wrap">
    <div class="pgp-main">
      <noscript><?php if ( $is_owner ) { prospergenics_profiles_notice( 'info', 'Editing your profile needs JavaScript. Privacy settings work without it.' ); } ?></noscript>
      <?php
      if ( isset( $_GET['verified'] ) ) {
          prospergenics_profiles_notice( 'ok', 'Thank you: your email address is confirmed.' );
      }
      if ( $is_owner && isset( $_GET['welcome'] ) ) {
          prospergenics_profiles_notice( 'ok', 'Welcome! Your profile is set up. Use “Edit profile” to add more.' );
      }
      if ( function_exists( 'prospergenics_projects_placeholder_notice' ) ) {
          prospergenics_projects_placeholder_notice( $owner ); // #6388: a profile staff set up, not claimed yet
      }
      if ( prospergenics_profiles_is_hidden( $owner->ID ) ) {
          // audit ARC-15: only the owner and staff get this far
          prospergenics_profiles_notice( 'info', $is_owner
              ? 'The ProsperGenics team has hidden your profile: only you can see it. Please contact us if you think this is a mistake.'
              : 'This profile is hidden by the ProsperGenics team: only its owner and staff can see it.' );
      }
      ?>

      <?php if ( $is_owner ) :
          $notes = prospergenics_profiles_unread_notifications( $owner->ID ); ?>
        <section class="pgp-section pgp-notes" data-pgp-region="notes" aria-labelledby="pgp-notes-h"<?php echo $notes ? '' : ' hidden'; ?>>
          <h2 id="pgp-notes-h">New for you</h2>
          <ul>
            <?php foreach ( array_slice( $notes, 0, 5 ) as $n ) :
                $from = get_userdata( (int) $n['from'] ); if ( ! $from ) { continue; } ?>
              <li><a href="<?php echo esc_url( prospergenics_profiles_url( $from->ID ) ); ?>"><?php echo esc_html( $from->display_name ); ?></a> endorsed you for <strong><?php echo esc_html( $n['skill'] ); ?></strong>.</li>
            <?php endforeach; ?>
          </ul>
          <button type="button" class="pgp-link-btn" data-pgp-notes-read>Mark as read</button>
        </section>

        <section class="pgp-section pgp-edit-only" data-pgp-region="intro" aria-labelledby="pgp-intro-h" id="pgp-intro">
          <h2 id="pgp-intro-h">Your details</h2>
          <form class="pgp-form" data-pgp-form data-endpoint="me" data-method="PUT" data-refresh="hero about sidebar" data-pgp-keep-open>
            <p class="pgp-hint">Single-line fields save as soon as you leave them.</p>
            <div class="pgp-grid2">
              <?php
              prospergenics_profiles_field( 'pgp-first', 'first_name', 'First name', $data['first_name'], 'text', array( 'maxlength' => 60, 'autocomplete' => 'given-name', 'data-pgp-autosave' => '1' ) );
              prospergenics_profiles_field( 'pgp-last', 'last_name', 'Last name', $data['last_name'], 'text', array( 'maxlength' => 60, 'autocomplete' => 'family-name', 'data-pgp-autosave' => '1' ) );
              ?>
            </div>
            <?php prospergenics_profiles_field( 'pgp-headline', 'headline', 'Headline', $data['headline'], 'text', array( 'maxlength' => 160, 'data-pgp-autosave' => '1', 'hint' => 'One line about what you do, e.g. “Solar installer and trainer in Kisumu”.' ) ); ?>
            <div class="pgp-grid2">
              <?php
              prospergenics_profiles_field( 'pgp-location', 'location', 'Location', $data['location'], 'text', array( 'maxlength' => 100, 'autocomplete' => 'address-level2', 'data-pgp-autosave' => '1' ) );
              prospergenics_profiles_field( 'pgp-phone', 'phone', 'Phone', (string) prospergenics_profiles_meta( $owner->ID, 'phone' ), 'tel', array( 'maxlength' => 40, 'autocomplete' => 'tel', 'data-pgp-autosave' => '1', 'hint' => 'Hidden unless you change it in privacy settings.' ) );
              ?>
            </div>
            <?php prospergenics_profiles_field( 'pgp-about', 'about', 'About', $data['about'], 'textarea', array( 'maxlength' => 2600 ) ); ?>
            <?php prospergenics_profiles_form_actions( 'Save details' ); ?>
          </form>
        </section>
      <?php endif; ?>

      <?php if ( $vis['featured'] && ( $is_owner || ! empty( $data['featured'] ) ) ) :
          $featured = isset( $data['featured'] ) ? $data['featured'] : array(); ?>
        <section class="pgp-section" data-pgp-region="featured" id="pgp-featured" aria-labelledby="pgp-featured-h">
          <div class="pgp-section-head"><h2 id="pgp-featured-h" tabindex="-1">Featured</h2><?php prospergenics_profiles_badge( $data, 'featured' ); ?></div>
          <?php if ( $featured ) : ?>
            <ul class="pgp-featured" data-pgp-featured-list>
              <?php foreach ( $featured as $i => $f ) : ?>
                <li class="pgp-feat" data-pgp-item="<?php echo esc_attr( wp_json_encode( $f ) ); ?>"<?php echo $is_owner ? ' draggable="true"' : ''; ?>>
                  <a class="pgp-feat-link" href="<?php echo esc_url( $f['url'] ); ?>"<?php echo 0 === strpos( $f['url'], home_url() ) ? '' : ' rel="noopener nofollow ugc" target="_blank"'; ?>>
                    <?php if ( '' !== $f['image'] ) : ?>
                      <img src="<?php echo esc_url( $f['image'] ); ?>" alt="" loading="lazy" referrerpolicy="no-referrer" />
                    <?php else : ?>
                      <span class="pgp-feat-ph" aria-hidden="true"><?php echo esc_html( prospergenics_profiles_featured_types()[ $f['type'] ] ); ?></span>
                    <?php endif; ?>
                    <span class="pgp-feat-type"><?php echo esc_html( prospergenics_profiles_featured_types()[ $f['type'] ] ); ?></span>
                    <span class="pgp-feat-title"><?php echo esc_html( '' !== $f['title'] ? $f['title'] : $f['url'] ); ?></span>
                    <?php if ( 0 !== strpos( $f['url'], home_url() ) ) : ?><span class="screen-reader-text"> (opens in a new tab)</span><?php endif; ?>
                  </a>
                  <?php if ( $is_owner ) : ?>
                    <div class="pgp-feat-tools pgp-edit-only">
                      <span class="pgp-drag" aria-hidden="true" title="Drag to reorder">⠿</span>
                      <button type="button" class="pgp-link-btn" data-pgp-feat-move="-1"<?php disabled( 0, $i ); ?>>Move up<span class="screen-reader-text">: <?php echo esc_html( $f['title'] ); ?></span></button>
                      <button type="button" class="pgp-link-btn" data-pgp-feat-move="1"<?php disabled( count( $featured ) - 1, $i ); ?>>Move down<span class="screen-reader-text">: <?php echo esc_html( $f['title'] ); ?></span></button>
                      <button type="button" class="pgp-link-btn pgp-danger" data-pgp-feat-remove>Remove<span class="screen-reader-text">: <?php echo esc_html( $f['title'] ); ?></span></button>
                    </div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php elseif ( $is_owner ) : ?>
            <p class="pgp-empty">Pin up to 3 things you are proud of: an article, a link, a photo of your work.</p>
          <?php endif; ?>
          <?php if ( $is_owner && count( $featured ) < 3 ) : ?>
            <div class="pgp-edit-only">
              <button type="button" class="pgp-btn" data-pgp-open="pgp-feat-new-form" aria-expanded="false" aria-controls="pgp-feat-new-form">Add featured item</button>
              <form class="pgp-form" id="pgp-feat-new-form" data-pgp-featured-add hidden>
                <fieldset><legend>Add a featured item</legend>
                <?php
                prospergenics_profiles_field( 'pgp-feat-type', 'type', 'Type', 'link', 'select', array( 'options' => array( 'link' => 'External link', 'article' => 'Article (on this site or elsewhere)', 'media' => 'Image of your work' ), 'data-pgp-feat-type' => '1' ) );
                prospergenics_profiles_field( 'pgp-feat-url', 'url', 'Link', '', 'url', array( 'placeholder' => 'https://', 'data-pgp-feat-url' => '1', 'hint' => 'We fetch the title and image from the page.' ) );
                prospergenics_profiles_field( 'pgp-feat-file', 'media', 'Image (JPEG, PNG or WebP, max 5 MB)', '', 'file', array( 'accept' => 'image/jpeg,image/png,image/webp,image/gif', 'data-pgp-feat-file' => '1' ) );
                prospergenics_profiles_field( 'pgp-feat-title', 'title', 'Title (optional for links)', '', 'text', array( 'maxlength' => 140 ) );
                ?>
                </fieldset>
                <?php prospergenics_profiles_form_actions( 'Add' ); ?>
              </form>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ( '' !== $data['about'] || $is_owner ) : ?>
        <section class="pgp-section" data-pgp-region="about" aria-labelledby="pgp-about-h">
          <div class="pgp-section-head"><h2 id="pgp-about-h" tabindex="-1">About</h2><?php prospergenics_profiles_badge( $data, 'profile' ); ?></div>
          <?php if ( '' !== $data['about'] ) : ?>
            <div class="pgp-about"><?php echo wpautop( esc_html( $data['about'] ) ); ?></div>
          <?php else : ?>
            <p class="pgp-empty">Tell people about yourself: switch on “Edit profile” and fill in “About”.</p>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ( $vis['experience'] && ( $is_owner || ! empty( $data['experience'] ) ) ) : ?>
        <section class="pgp-section" data-pgp-region="experience" id="pgp-experience" aria-labelledby="pgp-exp-h">
          <div class="pgp-section-head"><h2 id="pgp-exp-h" tabindex="-1">Experience</h2><?php prospergenics_profiles_badge( $data, 'experience' ); ?></div>
          <?php if ( ! empty( $data['experience'] ) ) : ?>
            <ol class="pgp-timeline">
              <?php foreach ( $data['experience'] as $e ) : ?>
                <li class="pgp-entry">
                  <div class="pgp-entry-view" id="pgp-exp-<?php echo (int) $e['id']; ?>-view">
                    <h3><?php echo esc_html( $e['title'] ); ?></h3>
                    <p class="pgp-entry-sub"><?php echo esc_html( $e['company'] ); ?></p>
                    <p class="pgp-entry-dates"><?php echo esc_html( prospergenics_profiles_experience_dates( $e ) ); ?><?php if ( $e['current'] ) : ?> <span class="pgp-current">Current</span><?php endif; ?></p>
                    <?php if ( '' !== $e['description'] ) : ?><div class="pgp-entry-desc"><?php echo wpautop( esc_html( $e['description'] ) ); ?></div><?php endif; ?>
                    <?php // #6389: published notes about this entry and "Suggest a correction" (inc/profiles/scrutiny-render.php)
                    do_action( 'prospergenics_scrutiny_public', 'claim', $e['id'] ); do_action( 'prospergenics_scrutiny_actions', 'claim', $e['id'] ); ?>
                    <?php if ( $is_owner ) : ?>
                      <div class="pgp-entry-tools pgp-edit-only">
                        <button type="button" class="pgp-link-btn" data-pgp-open="pgp-exp-<?php echo (int) $e['id']; ?>-form" data-pgp-hide="pgp-exp-<?php echo (int) $e['id']; ?>-view" aria-expanded="false">Edit<span class="screen-reader-text"> <?php echo esc_html( $e['title'] ); ?></span></button>
                        <button type="button" class="pgp-link-btn pgp-danger" data-pgp-delete="experience/<?php echo (int) $e['id']; ?>" data-refresh="experience sidebar" data-confirm="<?php echo esc_attr( sprintf( 'Delete “%s”?', $e['title'] ) ); ?>">Delete<span class="screen-reader-text"> <?php echo esc_html( $e['title'] ); ?></span></button>
                      </div>
                    <?php endif; ?>
                  </div>
                  <?php if ( $is_owner ) { prospergenics_profiles_experience_form( $e ); } ?>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php else : ?>
            <p class="pgp-empty">No experience yet.</p>
          <?php endif; ?>
          <?php if ( $is_owner ) : ?>
            <div class="pgp-edit-only">
              <button type="button" class="pgp-btn" data-pgp-open="pgp-exp-new-form" aria-expanded="false">Add experience</button>
              <?php prospergenics_profiles_experience_form(); ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ( $vis['education'] && ( $is_owner || ! empty( $data['education'] ) ) ) : ?>
        <section class="pgp-section" data-pgp-region="education" id="pgp-education" aria-labelledby="pgp-edu-h">
          <div class="pgp-section-head"><h2 id="pgp-edu-h" tabindex="-1">Education</h2><?php prospergenics_profiles_badge( $data, 'education' ); ?></div>
          <?php if ( ! empty( $data['education'] ) ) : ?>
            <ol class="pgp-timeline">
              <?php foreach ( $data['education'] as $e ) :
                  $years = trim( ( $e['start_year'] ? $e['start_year'] : '' ) . ' – ' . ( $e['end_year'] ? $e['end_year'] : 'present' ), ' –' );
                  $sub   = implode( ', ', array_filter( array( $e['degree'], $e['field'] ) ) ); ?>
                <li class="pgp-entry">
                  <div class="pgp-entry-view" id="pgp-edu-<?php echo (int) $e['id']; ?>-view">
                    <h3><?php echo esc_html( $e['institution'] ); ?></h3>
                    <?php if ( '' !== $sub ) : ?><p class="pgp-entry-sub"><?php echo esc_html( $sub ); ?></p><?php endif; ?>
                    <?php if ( $e['start_year'] || $e['end_year'] ) : ?><p class="pgp-entry-dates"><?php echo esc_html( $years ); ?></p><?php endif; ?>
                    <?php do_action( 'prospergenics_scrutiny_public', 'claim', $e['id'] ); do_action( 'prospergenics_scrutiny_actions', 'claim', $e['id'] ); // #6389 ?>
                    <?php if ( $is_owner ) : ?>
                      <div class="pgp-entry-tools pgp-edit-only">
                        <button type="button" class="pgp-link-btn" data-pgp-open="pgp-edu-<?php echo (int) $e['id']; ?>-form" data-pgp-hide="pgp-edu-<?php echo (int) $e['id']; ?>-view" aria-expanded="false">Edit<span class="screen-reader-text"> <?php echo esc_html( $e['institution'] ); ?></span></button>
                        <button type="button" class="pgp-link-btn pgp-danger" data-pgp-delete="education/<?php echo (int) $e['id']; ?>" data-refresh="education sidebar" data-confirm="<?php echo esc_attr( sprintf( 'Delete “%s”?', $e['institution'] ) ); ?>">Delete<span class="screen-reader-text"> <?php echo esc_html( $e['institution'] ); ?></span></button>
                      </div>
                    <?php endif; ?>
                  </div>
                  <?php if ( $is_owner ) { prospergenics_profiles_education_form( $e ); } ?>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php else : ?>
            <p class="pgp-empty">No education yet.</p>
          <?php endif; ?>
          <?php if ( $is_owner ) : ?>
            <div class="pgp-edit-only">
              <button type="button" class="pgp-btn" data-pgp-open="pgp-edu-new-form" aria-expanded="false">Add education</button>
              <?php prospergenics_profiles_education_form(); ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ( $vis['skills'] && ( $is_owner || ! empty( $data['skills'] ) ) ) :
          $skills = isset( $data['skills'] ) ? $data['skills'] : array();
          $top    = array_slice( $skills, 0, 3 );
          $rest   = array_slice( $skills, 3 ); ?>
        <section class="pgp-section" data-pgp-region="skills" id="pgp-skills" aria-labelledby="pgp-skills-h">
          <div class="pgp-section-head"><h2 id="pgp-skills-h" tabindex="-1">Skills</h2><?php prospergenics_profiles_badge( $data, 'skills' ); ?></div>
          <?php if ( $skills ) : ?>
            <ul class="pgp-skills">
              <?php foreach ( $top as $s ) { prospergenics_profiles_skill_item( $s, $data, $viewer_id ); } ?>
            </ul>
            <?php if ( $rest ) : ?>
              <details class="pgp-more">
                <summary>See all <?php echo (int) count( $skills ); ?> skills</summary>
                <ul class="pgp-skills">
                  <?php foreach ( $rest as $s ) { prospergenics_profiles_skill_item( $s, $data, $viewer_id ); } ?>
                </ul>
              </details>
            <?php endif; ?>
            <?php if ( ! $viewer_id && ! $preview ) : ?>
              <p class="pgp-hint"><a href="<?php echo esc_url( prospergenics_profiles_login_url( $profile_url ) ); ?>">Log in</a> to endorse skills.</p>
            <?php endif; ?>
          <?php else : ?>
            <p class="pgp-empty">No skills yet.</p>
          <?php endif; ?>
          <?php if ( $is_owner ) : ?>
            <form class="pgp-form pgp-skill-add pgp-edit-only" data-pgp-form data-endpoint="skills" data-method="POST" data-refresh="skills sidebar" data-pgp-keep-open>
              <?php prospergenics_profiles_field( 'pgp-skill-new', 'name', 'Add a skill', '', 'text', array( 'list' => 'pgp-skill-vocab', 'maxlength' => 60, 'autocomplete' => 'off', 'required' => true, 'hint' => 'Start typing for suggestions, or enter your own. Pin up to 3 to show them first.' ) ); ?>
              <?php prospergenics_profiles_skills_datalist(); ?>
              <p class="pgp-form-error" role="alert" hidden></p>
              <div class="pgp-form-actions"><button type="submit" class="pgp-btn pgp-btn-primary">Add skill</button></div>
            </form>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php
      // #6388: projects, products, services and employers with the member's role, contribution and
      // evidence (inc/projects/render.php); manual forms in edit mode
      if ( function_exists( 'prospergenics_projects_profile_section' ) ) {
          prospergenics_projects_profile_section( $owner, $data, $viewer_id );
      }
      ?>

      <?php if ( $vis['certificates'] && ! empty( $data['certificates'] ) ) : ?>
        <section class="pgp-section" id="pgp-certificates" aria-labelledby="pgp-certs-h">
          <div class="pgp-section-head"><h2 id="pgp-certs-h">Certificates</h2><?php prospergenics_profiles_badge( $data, 'certificates' ); ?></div>
          <ul class="pgp-list">
            <?php foreach ( $data['certificates'] as $c ) : ?>
              <li class="pgp-list-item">
                <h3><?php if ( '' !== $c['course_url'] ) : ?><a href="<?php echo esc_url( $c['course_url'] ); ?>"><?php echo esc_html( $c['course'] ); ?></a><?php else : ?><?php echo esc_html( $c['course'] ); ?><?php endif; ?></h3>
                <p class="pgp-entry-dates">Completed <?php echo esc_html( $c['date'] ); ?> · <a href="<?php echo esc_url( $c['verify_url'] ); ?>">Verify<span class="screen-reader-text"> the certificate for <?php echo esc_html( $c['course'] ); ?></span></a></p>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>

      <?php if ( ! empty( $data['courses'] ) ) : ?>
        <section class="pgp-section" id="pgp-courses" aria-labelledby="pgp-courses-h">
          <div class="pgp-section-head"><h2 id="pgp-courses-h"><?php echo $is_owner ? 'Courses you teach' : 'Courses'; ?></h2></div>
          <ul class="pgp-list">
            <?php foreach ( $data['courses'] as $c ) : ?>
              <li class="pgp-list-item"><h3><a href="<?php echo esc_url( $c['url'] ); ?>"><?php echo esc_html( $c['title'] ); ?></a></h3></li>
            <?php endforeach; ?>
          </ul>
          <?php if ( ! empty( $data['instructor_url'] ) ) : ?>
            <p class="pgp-more-link"><a href="<?php echo esc_url( $data['instructor_url'] ); ?>"><?php echo esc_html( sprintf( 'All courses by %s', $data['name'] ) ); ?></a></p>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ( ! empty( $data['articles'] ) ) : ?>
        <section class="pgp-section" id="pgp-articles" aria-labelledby="pgp-articles-h">
          <div class="pgp-section-head"><h2 id="pgp-articles-h"><?php echo $is_owner ? 'Your articles' : 'Articles'; ?></h2></div>
          <ul class="pgp-list">
            <?php foreach ( $data['articles'] as $a ) : ?>
              <li class="pgp-list-item"><h3><a href="<?php echo esc_url( $a['url'] ); ?>"><?php echo esc_html( $a['title'] ); ?></a></h3><p class="pgp-entry-dates"><?php echo esc_html( $a['date'] ); ?></p></li>
            <?php endforeach; ?>
          </ul>
          <?php if ( $is_owner ) : ?>
            <p class="pgp-more-link"><a href="<?php echo esc_url( home_url( '/articles/mine/' ) ); ?>">My articles: drafts, scheduled and published</a></p>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php // #6389 public scrutiny of this member: what was published about them after review, and
      // "Suggest a correction, add evidence or review" (inc/profiles/scrutiny-render.php; nothing when
      // the profile is hidden or suspended)
      do_action( 'prospergenics_scrutiny_public', 'person', $owner->ID );
      do_action( 'prospergenics_scrutiny_actions', 'person', $owner->ID ); ?>
    </div>

    <aside class="pgp-side" data-pgp-region="sidebar" aria-label="Profile details">
      <?php if ( $is_owner ) :
          $completion = $data['completion']; ?>
        <?php if ( $completion['percent'] < 100 ) : ?>
          <div class="pgp-card pgp-completion">
            <h2 class="pgp-card-h" id="pgp-completion-h">Profile strength</h2>
            <div class="pgp-meter" role="progressbar" aria-labelledby="pgp-completion-h" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $completion['percent']; ?>">
              <span style="width:<?php echo (int) $completion['percent']; ?>%"></span>
            </div>
            <p class="pgp-meter-num"><?php echo (int) $completion['percent']; ?>% complete</p>
            <ul class="pgp-todo">
              <?php
              $anchors = array( 'photo' => '#pgp-photo', 'headline' => '#pgp-intro', 'experience' => '#pgp-experience', 'skills' => '#pgp-skills', 'education' => '#pgp-education' );
              foreach ( $completion['missing'] as $key => $label ) : ?>
                <li><a href="<?php echo esc_url( add_query_arg( 'edit', '1', $profile_url ) . $anchors[ $key ] ); ?>" data-pgp-goto="<?php echo esc_attr( $anchors[ $key ] ); ?>"><?php echo esc_html( $label ); ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="pgp-card">
        <h2 class="pgp-card-h">Profile link</h2>
        <div class="pgp-copy">
          <label class="screen-reader-text" for="pgp-url">Profile link</label>
          <input type="text" id="pgp-url" value="<?php echo esc_attr( $profile_url ); ?>" readonly />
          <button type="button" class="pgp-btn" data-pgp-copy="<?php echo esc_attr( $profile_url ); ?>">Copy link</button>
        </div>
        <p class="pgp-status" role="status" data-pgp-copy-status></p>
      </div>

      <?php if ( isset( $data['email'] ) || ( isset( $data['phone'] ) && '' !== $data['phone'] ) ) : ?>
        <div class="pgp-card">
          <h2 class="pgp-card-h">Contact</h2>
          <dl class="pgp-contact">
            <?php if ( isset( $data['email'] ) ) : ?>
              <dt>Email</dt><dd><a href="mailto:<?php echo esc_attr( antispambot( $data['email'] ) ); ?>"><?php echo esc_html( antispambot( $data['email'] ) ); ?></a> <?php prospergenics_profiles_badge( $data, 'email' ); ?></dd>
            <?php endif; ?>
            <?php if ( isset( $data['phone'] ) && '' !== $data['phone'] ) : ?>
              <dt>Phone</dt><dd><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $data['phone'] ) ); ?>"><?php echo esc_html( $data['phone'] ); ?></a> <?php prospergenics_profiles_badge( $data, 'phone' ); ?></dd>
            <?php endif; ?>
          </dl>
        </div>
      <?php endif; ?>

      <?php if ( $is_owner ) : ?>
        <div class="pgp-card">
          <h2 class="pgp-card-h">Your profile</h2>
          <ul class="pgp-side-links">
            <li><a href="<?php echo esc_url( prospergenics_profiles_url( $owner->ID, array( 'view' => 'public' ) ) ); ?>">See what visitors see</a></li>
            <li><a href="<?php echo esc_url( home_url( '/profile/settings/' ) ); ?>">Account and privacy settings</a></li>
            <li><a href="<?php echo esc_url( home_url( '/profile/following/' ) ); ?>">Your connections</a></li>
            <li><a href="<?php echo esc_url( home_url( '/courses/my/' ) ); ?>">My courses</a></li>
            <li><a href="<?php echo esc_url( home_url( '/articles/mine/' ) ); ?>">My articles</a></li>
            <?php if ( function_exists( 'prospergenics_courses_is_instructor' ) && prospergenics_courses_is_instructor() ) : ?>
              <li><a href="<?php echo esc_url( home_url( '/instructor/dashboard/' ) ); ?>">Instructor dashboard</a></li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>
    </aside>
  </div>
<?php endif; ?>
</div>

<p class="pgp-toast" role="status" data-pgp-status></p>
<?php if ( $is_owner ) : ?>
<?php prospergenics_profiles_crop_dialog(); ?>
<?php endif; ?>
<?php get_footer(); ?>
