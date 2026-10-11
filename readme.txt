=== Contact 2 Post ===
Contributors: TurtleEngr
Tags: contact, form, post, flamingo, template
Requires at least: 6.0
Requires PHP: 8.0
Tested up to: 7.1
Stable tag: VERSION
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create a "pending post" from a Contact Form 7 submission, post template page.

== Description ==

When a Contact Form 7 form is submitted and Flamingo saves the
message, this plugin will create a post with status "Pending". The
post content is copied from a template page, where each {field} is
replaced by the submitted form's value. The post author is the user
with the site's Administration Email Address, or else the first
administrator.

This plugin assumes you are using Gutenberg Blocks for editing.

Required plugins: contact-form-7, flamingo.

Optional: save-cf7-file-uploads, ninja-auto-post-expire

* save-cf7-file-uploads (or store-file-uploads-for-contact-form-7 Pro)
  - either of these plugins will save attached image files to the
  Media Library. The first image will be used for the Featured Image.
  If save-cf7-file-uploads could not save an uploaded file, its error
  messages for that submission are added to the end of the post,
  after "Upload errors:".

* ninja-auto-post-expire - If this plugin is insatlled the "expiry
  date" field for a post can be set with this (contact-2-post) plugin.

== Installation ==

Install these plugins in this order.

1. Install and activate contact-form-7 plugin.
1. Install and activate flamingo plugin.
1. Install and activate save-cf7-file-uploads plugin
1. Install and activate this plugin contact-2-post

== Frequently Asked Questions ==

= Where can I get documentation =

See: https://github.com/TurtleEngr/WP-save-cf7-file-uploads

= How can I configure Contact Form 7 to use this? ==

See:
https://github.com/TurtleEngr/WP-contact-2-post#contact-form-settings

= How can I define a template for posts?

See:
https://github.com/TurtleEngr/WP-contact-2-post#contact-form-settings

= Where can I report bug or feature requests? =

See: https://github.com/TurtleEngr/WP-save-cf7-file-uploads Issues

= Where can I find newer versions? =

The latest "stable version" can be found as wordpress.org.

For development versions see:
https://github.com/TurtleEngr/WP-contact-2-post#development-installs

== Changelog ==

= 1.1.0 =

Changed to follow the wordpress.org plugin guidelines.

= 0.1.0 =

Initial version.
