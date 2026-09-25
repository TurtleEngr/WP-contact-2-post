<?php
// Simulate one CF7 submission. Run with: wp eval-file submit.php FORMTITLE [spam] [attach]
// $args is set by wp eval-file.
$tTitle = $args[0];
$tForm = null;
foreach (WPCF7_ContactForm::find(['posts_per_page' => -1]) as $tF) {
    if ($tF->title() === $tTitle) {
        $tForm = $tF;
    }
}
$_SERVER['HTTP_USER_AGENT'] = in_array('spam', $args, true) ? '' : 'Mozilla/5.0 test';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
if (in_array('attach', $args, true)) {
    // CLI can not do a real HTTP upload, so call store-cf7-file-uploads'
    // own function at the same hook it uses.
    add_action('wpcf7_before_send_mail', function () {
        copy('/tmp/c2p-test.png', '/tmp/c2p-upload.png');
        nmr_create_attachment('/tmp/c2p-upload.png');
    });
}
$_POST = [
    '_wpcf7' => $tForm->id(),
    'your-name' => 'Ann <b>Bold</b>',
    'ev-name' => 'Jazz Night',
    'cats' => ['Events', 'Bogus'],
    'tagz' => 'music, nosuchtag',
    'pub' => '2026-10-15',
    'exp' => '2026-11-01',
    'etime' => '9:30',
    'details' => "<script>alert(1)</script> [gallery] [your-name]\nline2",
];
$tResult = $tForm->submit();
echo 'status=' . $tResult['status'] . "\n";
