# Contact 2 Post

![version](https://img.shields.io/badge/version-1.2.2-orange.svg)

![WordPress](https://img.shields.io/badge/WordPress-Compatible-blue.svg)

-   When a Contact Form 7 form is submitted, this plugin saves the
    uploaded image files to the Media Library. Then, after Flamingo
    saves the message, this plugin can create a \"pending post\" from a
    template post page. The images are attached to the post.

-   The upload code was merged in from
    [save-cf7-file-uploads](https://github.com/TurtleEngr/WP-save-cf7-file-uploads),
    which added enhancements to [Store file uploads for Contact Form
    7](https://wordpress.org/plugins/store-file-uploads-for-contact-form-7/)
    by Mircea N. (version 1.3.0). save-cf7-file-uploads is no longer
    needed.

-   See readme.txt for the user documentation.

## Installation

1.  Install and activate contact-form-7 plugin.
2.  Install and activate flamingo plugin.
3.  Optional: Install and activate ninja-auto-post-expire plugin
4.  If save-cf7-file-uploads is installed, deactivate and delete it. Its
    code is now in this plugin, and both would save each image.
5.  Install and activate this plugin contact-2-post

### Development Installs

1.  Follow install steps 1 to 4 above.
2.  Go to:
    <https://moria.whyayh.com/rel/released/software/own/WP-contact-2-post>
3.  Then download the contact-2-VER.zip file you want.
4.  In WordPress, install with upload plugin, then activate.

## Usage

To use this plugin, you need to add some things to your contact forms.
See \"Contact Form Settings\" section.

Also, you need to define a template for your posts. See \"Template
Page\" section.

Uploaded images are saved for every form, even without
`create_post`{.verbatim}. See \"Uploaded Files\" section.

### Contact Form Settings

In a form\'s \"Additional Settings\" tab, add these settings:

``` example
skip_mail: on
create_post: true
do_not_store: false
post_template: PageSlug
```

-   `skip_mail`{.verbatim} - this has no effect on this plugin, but you
    can decide if you want to send an email of the form, in addition to
    making a post.

-   `create_post`{.verbatim} - if \'true\' that activates this plugin

-   `do_not_store`{.verbatim} - this is a Flamingo option. The default
    is \'false\'. If it is set to \'true\', the form data will not be
    stored and this plugin will not work.

-   `post_template`{.verbatim} - this is the slug-name for a \"page\"
    that will be used as a template for the posts. See \"Template Page\"
    for details.

Even though this plugin does not \"publish\" the posts, you should turn
on reCAPTCHA in contact-form-7 settings.

### Template Page

All the blocks of the \'PageSlug\' page are copied to the new post.
{field} is replaced with the value of the form \'field\' with that name.
Values are HTML escaped, and \"\[\" \"\]\" in values are encoded so they
cannot run a shortcode. Checkbox values are joined with \", \". If there
is no field with that name, {field} is left as is.

A post has attributes that are not blocks. This is how they are defined
in the page template. After the page\'s title, put the post attribute
lines between ATTR-BEGIN and ATTR-END lines (one per line, or separated
with Shift-Enter). They can be in one top-level block or in several.
Every top-level block from the one with ATTR-BEGIN to the one with
ATTR-END is not copied. They are used to set the post\'s attributes.
FYI, all these are optional.

``` example
ATTR-BEGIN
title: {your-subject}
categories: {field}
tags: {field}
publish-date: {field}
expire-date: {field}
expire-time: {field}
ATTR-END
```

-   title - post title. See Flamingo documention for how to change
    {your-subject} Default: UNDEFINED
-   categories, tags - comma separated names or slugs. Only existing
    categories and tags are used; others are ignored. Default: empty
-   publish-date - YYYY-MM-DD, time is set to 12:00. This date is kept
    when the post is published (a future date makes it \"Scheduled\").
    Default: the time the post is published.
-   expire-date - YYYY-MM-DD. Saved in the ninja-auto-post-expire meta
    (`_njtape_expiration_date`{.verbatim}). Default: not set.
-   expire-time - HH:MM. Default: 00:00

ninja-auto-post-expire only acts on posts if its \"Post\" option is set
to \"Yes\" in it\'s settings, Post Expire Settings.

Invalid dates or times are ignored and logged to
`error_log()`{.verbatim}. Use a plugin such as debug-log-manager to look
at the `error_log`{.verbatim}.

### Uploaded Files

Files uploaded with the Contact Form 7 `[file NAME]`{.verbatim} field
are saved to the Media Library before the email is sent.

1.  Only image files are saved. The type is checked from the file
    content, and the file extension must also be an allowed image type.
    Other files are skipped and logged.
2.  File names are normalized. File names can only contain letters in
    the set: \[a-zA-Z0-9.\_-\]. Any letters not in the set are converted
    to a \"[\", and repeated \"]{.underline}\" are collapsed.
3.  Existing files are never overwritten. \"~1~\", \"~2~\", ... is added
    to the base name instead.

If a post is created, the images are attached to it, and the first image
is the post\'s Featured Image.

If an uploaded file could not be saved, the error messages for that
submission are added to the end of the post, in a paragraph that starts
with \"Upload errors:\". They are also written to the PHP error log.

## How it works

-   `cx2p_fOnBeforeCf7SendMail()`{.verbatim} is hooked to
    `wpcf7_before_send_mail`{.verbatim}. It saves each uploaded image
    with `cx2p_fCreateAttachment()`{.verbatim}, and collects the
    attachment IDs with `cx2p_fSaveAttachId()`{.verbatim}.

-   Upload error messages are saved with `cx2p_fErrorLog()`{.verbatim},
    which also writes them to the PHP error log. Calling
    `cx2p_fErrorLog()`{.verbatim} with no argument returns the list. The
    list is a `static`{.verbatim} variable, and each form submission is
    one PHP request with its own memory, so a post only gets the errors
    from its own submission, even when several people submit at the same
    time. The messages are not kept after the request ends; the PHP
    error log still has them.

-   These filters can change how uploads are saved:
    `cx2p_fCreateAttachment_file_name`{.verbatim} (the destination
    path),
    `cx2p_should_skip_save_attachment_to_media_library`{.verbatim}
    (return true to only copy the file), and
    `cx2p_before_insert_attachment`{.verbatim} (the attachment array).

-   Flamingo saves the message in `wpcf7_submit`{.verbatim}, then fires
    `wpcf7_after_flamingo`{.verbatim} with
    `flamingo_inbound_id`{.verbatim}. This plugin hooks that action, so
    no post is made when Flamingo does not store the message
    (`do_not_store: true`{.verbatim}), or for spam.

-   The form\'s \"`create_post`{.verbatim}\" and
    \"`post_template`{.verbatim}\" Additional Settings are read with
    `WPCF7_ContactForm::is_true()`{.verbatim} and `pref()`{.verbatim}.

-   The template page is split with `parse_blocks()`{.verbatim}.
    Attribute blocks are removed, {field} is replaced only in the block
    HTML (not in the block comment JSON), then
    `serialize_blocks()`{.verbatim}.

## Notes

-   File fields: CF7 saves a hash of the file as the field value, so
    `{file-field}`{.verbatim} in a template shows that hash. The images
    are attached to the post instead.
-   Post author is the user with the site\'s `admin_email`{.verbatim},
    else the lowest ID administrator. Content is still filtered by core
    kses, because kses checks the submitter (not logged in), not the
    author.
-   If publish-date is valid, the date is fixed
    (`post_date_gmt`{.verbatim} is set), so publishing keeps it; a
    future date makes the post \"Scheduled\". If there is no valid
    publish-date, the post date \"floats\": it is set when the post is
    published.
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
