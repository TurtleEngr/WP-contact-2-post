=== Contact 2 Post ===
Contributors: TurtleEngr
Tags: contact, form, post, flamingo, upload
Requires at least: 6.0
Requires PHP: 8.0
Tested up to: 7.1
Stable tag: VERSION
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

When a Contact Form 7 form is submitted, this plugin saves uploaded
images to the Media Library, and can create a "pending post" from a
template post page.

== Description ==

When a Contact Form 7 form is submitted and Flamingo saves the
message, this plugin will create a post with status "Pending". The
post content is copied from a template page, where each {field} is
replaced by the submitted form's value. The post author is the user
with the site's Administration Email Address, or else the first
administrator.

This plugin assumes you are using Gutenberg Blocks for editing.

Files uploaded with the Contact Form 7 `[file NAME]` field are saved
to the Media Library before the email is sent. Only image files are
saved. The type is checked from the file content, and the file
extension must also be an allowed image type. Other files are
skipped, and a line is written to the PHP error log. File names are
reduced to the characters a-z, A-Z, 0-9, ".", "_" and "-", and
existing files are never overwritten: "_1", "_2", and so on is added
to the name instead.

The images are attached to the created post, and the first image is
used for the Featured Image. If an uploaded file could not be saved,
the error messages for that submission are added to the end of the
post, after "Upload errors:".

The upload code is from the Save CF7 File Uploads plugin, which
added enhancements to version 1.3.0 of
[Store file uploads for Contact Form 7](https://wordpress.org/plugins/store-file-uploads-for-contact-form-7/)
by Mircea N.

Required plugins: contact-form-7, flamingo.

Optional: ninja-auto-post-expire - If this plugin is installed the
"expiry date" field for a post can be set with this (contact-2-post)
plugin.

== Installation ==

Install these plugins in this order.

1. Install and activate contact-form-7 plugin.
1. Install and activate flamingo plugin.
1. Install and activate this plugin contact-2-post

If the save-cf7-file-uploads plugin is installed, deactivate and
delete it. Its code is now in this plugin.

In the "Additional Settings" tab for a contact form make sure
"do_not_store:" is not "true".

== Frequently Asked Questions ==

= Where can I get documentation =

See: https://github.com/TurtleEngr/WP-contact-2-post

= How can I configure Contact Form 7 to use this? ==

See:
https://github.com/TurtleEngr/WP-contact-2-post#contact-form-settings

= How can I define a template for posts?

See:
https://github.com/TurtleEngr/WP-contact-2-post#contact-form-settings

= Where can I report bug or feature requests? =

See: https://github.com/TurtleEngr/WP-contact-2-post Issues

= Where can I find newer versions? =

The latest "stable version" can be found as wordpress.org.

For development versions see:
https://github.com/TurtleEngr/WP-contact-2-post#development-installs

== Changelog ==

= 1.1.0 =

1. Changed to follow the wordpress.org plugin guidelines.
2. Merged in the save-cf7-file-uploads plugin. The function and
   filter prefix is now cx2p_. That plugin is no longer needed.
3. Fixed: uploaded images were not attached to the post (hook name
   mismatch).

= 0.1.0 =

Initial version.
