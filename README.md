# WP-contact-2-post

-   Version: 0.1.5

-   Create a \"Pending\" post from a Contact Form 7 submission, after
    Flamingo saves it. The post content comes from a template page.

-   See readme.txt for the user documentation.

## How it works

-   store-cf7-file-uploads saves images in wpcf7~beforesendmail~ and
    fires nmr~createattachmentidgenerated~. This plugin collects those
    IDs.
-   Flamingo saves the message in wpcf7~submit~, then fires
    wpcf7~afterflamingo~ with flamingo~inboundid~. This plugin hooks
    that action, so no post is made when Flamingo does not store the
    message (do~notstore~: true), or for spam.
-   The form\'s \"create~post~\" and \"post~template~\" Additional
    Settings are read with WPCF7~ContactForm~::is~true~() and pref().
-   The template page is split with parse~blocks~(). Attribute blocks
    are removed, {field} is replaced only in the block HTML (not in the
    block comment JSON), then serialize~blocks~().

## Notes

-   File fields: CF7 saves a hash of the file as the field value, so
    {file-field} in a template shows that hash. The images are attached
    to the post instead.
-   store-cf7-file-uploads only saves image files.
-   Post author is the user with the site\'s admin~email~, else the
    lowest ID administrator. Content is still filtered by core kses,
    because kses checks the submitter (not logged in), not the author.
-   If publish-date is valid, the date is fixed (post~dategmt~ is set),
    so publishing keeps it; a future date makes the post \"Scheduled\".
    If there is no valid publish-date, the post date \"floats\": it is
    set when the post is published.
-   ninja-auto-post-expire only acts on posts if its \"Post\" option is
    set to \"Yes\" in Settings, Post Expire Settings.

## Test

-   test/setup.php and test/submit.php are run with wp-cli on a test
    site with all the plugins active. They are not in the dist zip.

``` bash
wp eval-file test/setup.php
wp eval-file test/submit.php full attach   # post, attachment, expiry
wp eval-file test/submit.php notitle       # title UNDEFINED
wp eval-file test/submit.php nostore       # no post
wp eval-file test/submit.php nocreate      # no post
wp eval-file test/submit.php badslug       # no post, error_log
wp eval-file test/submit.php full spam     # no post
wp post list --post_type=post --post_status=pending
```
