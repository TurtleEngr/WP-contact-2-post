=== Contact 2 Post ===
Contributors: TurtleEngr
Tags: contact, form, post, flamingo, template
Requires at least: 5.8
Tested up to: 7.0
Stable tag: VERSION
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create a pending post from a Contact Form 7 submission, using a page
as the post template.

== Description ==

When a Contact Form 7 form is submitted and Flamingo saves the
message, this plugin creates a post with status "Pending". The post
content is copied from a template page, with each {field} replaced by
the submitted value. The post author is the user with the site's
Administration Email Address, or else the first administrator.

Required plugins: Contact Form 7, Flamingo. Optional:
store-cf7-file-uploads (images are attached to the post, and the first
one is the featured image), ninja-auto-post-expire (expiry date).

= Form "Additional Settings" tab =

`
skip_mail: on
create_post: true
do_not_store: false
post_template: PageSlug
`

skip_mail has no effect on this plugin. If do_not_store is true,
Flamingo does not save the message, so no post is created. Spam
messages never create a post.

= Template page =

All blocks of the PageSlug page are copied to the new post. {field} is
replaced with the value of the form field with that name. Values are
HTML escaped, and "[" "]" in values are encoded so they can not run a
shortcode. Checkbox values are joined with ", ". If there is no field
with that name, {field} is left as is.

A top-level block with only these lines (one per line, or separated
with Shift-Enter) is not copied. It sets the post attributes:

`
title: {field}
categories: {field}
tags: {field}
publish-date: {field}
expire-date: {field}
expire-time: {field}
`

* title - post title. Default: UNDEFINED
* categories, tags - comma separated names or slugs. Only existing
  categories and tags are used; others are ignored.
* publish-date - YYYY-MM-DD, time is set to 12:00. This date is kept
  when the post is published (a future date makes it "Scheduled").
  Default: the time the post is published.
* expire-date - YYYY-MM-DD. Saved in the ninja-auto-post-expire meta
  (_njtape_expiration_date).
* expire-time - HH:MM. Default: 00:00

Invalid dates or times are ignored.

== Installation ==

1. Upload the contact-2-post folder to /wp-content/plugins/
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Changelog ==

= 0.1.0 =

Initial version.
