=== Contact 2 Post ===
Contributors: TurtleEngr
Tags: contact, form, post, flamingo, upload
Requires at least: 6.0
Requires PHP: 8.0
Tested up to: 7.1
Stable tag: VERSION
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

When a contact-form-7 form is submitted, this plugin saves uploaded
images to Media Library, then it creates a post from a template page.

== Description ==

The created post will have a "Pending" status. See the documentation
for how to configure this plugin, contact-form-7, and flamingo.

Required plugins: contact-form-7, flamingo.

Optional plugin: ninja-auto-post-expire - If this plugin is installed
the "expiry date" field for a post can be set with this
(contact-2-post) plugin.

This plugin assumes you are using Gutenberg Blocks for editing.

Files uploaded with the Contact Form 7 "[file NAME]" fields are saved
to the Media Library before the email is sent. Only image files are
saved. The type is checked from the file content, and the file
extension must also be an allowed image type. Other files are
skipped, and a line is written to the PHP error log. File names are
reduced to the characters a-z, A-Z, 0-9, ".", "_" and "-", and
existing files are never overwritten: "_1", "_2", and so on is added
to the name.

The images are attached to the created post, and the first image is
used for the Featured Image. If an uploaded file could not be saved,
the error messages for that submission are added to the end of the
post, after "Upload errors:".

== Installation ==

Install these plugins in this order.

1. Install and activate contact-form-7 plugin.
1. Install and activate flamingo plugin.
1. Install and activate this plugin contact-2-post

If the save-cf7-file-uploads plugin is installed, delete it. That
functionallity is handled by this plugin.

In the "Additional Settings" tab for a contact form make sure
"do_not_store: true" is not defined.

== Frequently Asked Questions ==

= Where can I get more documentation =

See: https://github.com/TurtleEngr/WP-contact-2-post

= How can I configure Contact Form 7 to use this? ==

See:
https://github.com/TurtleEngr/WP-contact-2-post#contact-form-settings

= How can I define a template for the posts?

See:
https://github.com/TurtleEngr/WP-contact-2-post#contact-form-settings

= Where can I report bug or feature requests? =

See: https://github.com/TurtleEngr/WP-contact-2-post Issues

= Where can I find newer versions? =


The latest "stable version" can be found at
https://moria.whyayh.com/rel/released/software/own/WP-contact-2-post/
(or if accepted, at wordpress.org.)

For development versions see:
https://github.com/TurtleEngr/WP-contact-2-post#development-installs

== Changelog ==

= 1.4.0 =

- Errors are mailed to admin and the "your-email" address (if it is not
  flagged as Spam)

- Identified problem and a workaround with the "date" type in
  contact-form-7.

= 1.1.0 =

- Changed to follow the wordpress.org plugin guidelines.

- Merged in the save-cf7-file-uploads plugin functionallity. The
  function and filter prefix is now cx2p_. That plugin is no longer
  needed.

- Fixed: uploaded images were not attached to the post (hook name
  mismatch).

= 0.1.0 =

Initial version.
