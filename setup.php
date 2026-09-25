<?php
// Test fixture. Run with: wp eval-file setup.php
wp_insert_term('Events', 'category');
wp_insert_term('News', 'category');
wp_insert_term('music', 'post_tag');

$cTemplate = <<<'TPL'
<!-- wp:paragraph -->
<p>title: Event: [ev-name]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>categories: [cats]<br>tags: [tagz]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>publish-date: [pub]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>expire-date: [exp]<br>expire-time: [etime]</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">[ev-name]</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Details: [details] and [nofield]</p>
<!-- /wp:paragraph -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p>From [your-name]</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
TPL;
wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_name' => 'event-template',
    'post_title' => 'Event Template', 'post_content' => wp_slash($cTemplate)]);
wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_name' => 'no-title-template',
    'post_title' => 'No Title', 'post_content' => wp_slash("<!-- wp:paragraph -->\n<p>Hello [your-name]</p>\n<!-- /wp:paragraph -->")]);

$cForm = '[text* your-name] [text ev-name] [checkbox cats "Events" "News" "Bogus"] [text tagz]'
    . ' [date pub] [date exp] [text etime] [textarea details] [submit]';
foreach ([
    'full'     => "skip_mail: on\ncreate_post: true\ndo_not_store: false\npost_template: event-template",
    'notitle'  => "skip_mail: on\ncreate_post: true\npost_template: no-title-template",
    'nostore'  => "skip_mail: on\ncreate_post: true\ndo_not_store: true\npost_template: event-template",
    'nocreate' => "skip_mail: on\ndo_not_store: false\npost_template: event-template",
    'badslug'  => "skip_mail: on\ncreate_post: true\npost_template: missing-page",
] as $tName => $tSettings) {
    $tForm = WPCF7_ContactForm::get_template(['title' => $tName]);
    $tForm->set_properties(['form' => $cForm, 'additional_settings' => $tSettings]);
    $tForm->save();
    echo "$tName=" . $tForm->id() . "\n";
}
$tImg = imagecreatetruecolor(20, 20);
imagepng($tImg, '/tmp/c2p-test.png');
