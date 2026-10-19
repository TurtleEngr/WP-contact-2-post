# Contact 2 Post

![version](https://img.shields.io/badge/version-1.3.7-orange.svg)

![WordPress](https://img.shields.io/badge/WordPress-Compatible-blue.svg)

-   ver-1.3.7

<!-- -->
```
-   When a Contact Form 7 form is submitted, this plugin saves the
    uploaded image files to the Media Library. Then, after Flamingo
    saves the message, this plugin can create a \"pending post\" from a
    template post page. The images copied to the Media Libray, and they
    are attached to the post.

## Installation

1.  Install and activate contact-form-7 plugin.
2.  Install and activate flamingo plugin.
3.  Optional: Install and activate ninja-auto-post-expire plugin
4.  If save-cf7-file-uploads is installed, delete it. It is not
    compatible with this plugin.
5.  Install and activate this plugin contact-2-post

### Development Installs

These are not stable versions. ver-1.3.7-rel is the current stable
version.

1.  Follow Installation steps 1 to 4 above.
2.  Go to:
    <https://moria.whyayh.com/rel/released/software/own/WP-contact-2-post>
3.  Then download the contact-2-VER.zip file you want.
4.  In WordPress, install with Upload Plugin, then activate.

## Usage

To use this plugin, you need to add some things to your contact forms.
See \"Contact Form Settings\" section.

Also, you need to define a template for your posts. See \"Template
Page\" section.

Uploaded images are saved for every form, even without
`create_post`{.verbatim}. See \"Uploaded Files\" section.

If you installed ninja-auto-post-expire, you will the \"Post\" option
needs to be set to \"Yes\" in its Post Expire Settings.

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

Textarea fields are the exception: they can have some HTML. When HTML
text is pasted into a form\'s textarea, it is cleaned, and the textarea
shows the HTML tags that are kept. Only these tags are kept: h1, h2, h3,
h4, a, p, b, strong, i, em, ul, ol, li, br. All attributes are removed,
except href on \"a\" tags, and only for http, https, or mailto URLs.
Other tags are removed (their text is kept), and script and style
elements are removed with their content. The same cleaning is done again
when the post is created. If a textarea value has none of the allowed
tags, it is escaped like other fields.

So the post can be edited in the block editor, a {field} with p, h1-h4,
ul, or ol tags is changed to blocks: Paragraph, Heading, and List
blocks. This only works when the {field} is in a Paragraph block in the
template. Any text before or after the {field} in that paragraph is put
in its own paragraph. In other blocks (for example a Heading), only the
inline tags are kept, and the block tags are changed to line breaks.

A post has attributes that are not blocks. This is how they are defined
in the page template. After the page\'s title, put the post\'s attribute
lines between ATTR-BEGIN and ATTR-END lines (one per line, or separated
with Shift-Enter). They can be in one top-level block or in several.
Every top-level block from the one with ATTR-BEGIN to the one with
ATTR-END are not copied. They are used to set the post\'s attributes.
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

-   `title`{.verbatim} - post title. See Flamingo documention for how to
    change {your-subject} Default: UNDEFINED
-   `categories`{.verbatim}, `tags`{.verbatim} - comma separated names
    or slugs. Only existing categories and tags are used; others are
    ignored. Default: empty
-   `publish-date`{.verbatim} - YYYY-MM-DD, time is set to 12:00. This
    date is kept when the post is published (a future date makes it
    \"Scheduled\"). Default: the time the post is published.
-   `expire-date`{.verbatim} - YYYY-MM-DD. Saved in the
    ninja-auto-post-expire meta (`_njtape_expiration_date`{.verbatim}).
    Default: not set.
-   `expire-time`{.verbatim} - HH:MM. Default: 00:00

ninja-auto-post-expire only acts on posts if its \"Post\" option is set
to \"Yes\" in it\'s settings, Post Expire Settings.

Invalid dates or times are ignored and logged to
`error_log()`{.verbatim}. Use a plugin such as \"debug-log-manager\" to
look at the `error_log`{.verbatim}.

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

## Examples

Complete examples can be found at \<a href=\"example.html\"
target=\"~blank~\"\> Contact 2 Post Examples\</a\>

## Support

Plugin bugs and documentation bugs can be filed at:
[Issues](https://github.com/TurtleEngr/WP-contact-2-post/issues) You can
also make Feature Requests there.

## For Developers

If you want to modify the plugin\'s code, you can find the directions
at: \<a href=\"development.html\" target=\"~blank~\"\> Plugin
Development\</a\>
