=== Contact 2 Post ===
Contributors: TurtleEngr
Tags: contact, form, post, flamingo, template
Requires at least: 5.8
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

* ninja-auto-post-expire - If this plugin is insatlled the "expiry
  date" field for a post can be set with this (contact-2-post) plugin.

== Installation ==

1. Install and activate contact-form-7 plugin.
1. Install and activate flamingo plugin.
1. Optional: Install and activate save-cf7-file-uploads plugin
1. Optional: Install and activate ninja-auto-post-expire plugin
1. Install and activate this plugin contact-2-post

== Changelog ==

= 1.1.0 =

Changed to follow the wordpress.org plugin guidelines.

= 0.1.0 =

Initial version.

== Usage: Contact Form Settings ==

In a form's "Additional Settings" tab, add these settings:

`
skip_mail: on
create_post: true
do_not_store: false
post_template: PageSlug
`

* skip_mail - this has no effect on this plugin, but you can decide if
  you want to send an email of the form, in addition to making a post.

* create_post - if 'true' that activates this plugin

* do_not_store - this is a Flamingo option. The default is 'false'. If
  it is set to 'true', the form data will not be stored and this
  plugin will not work.

* post_template - this is the slug-name for a "page" that will be used
  as a template for the posts. See "Template Page" for details.

Even though this plugin does not "publish" the posts, you should turn
on reCAPTCHA in contact-form-7 settings.

== Usage: Template Page ==

All the blocks of the 'PageSlug' page are copied to the new
post. {field} is replaced with the value of the form 'field' with that
name. Values are HTML escaped, and "[" "]" in values are encoded so
they cannot run a shortcode. Checkbox values are joined with ", ". If
there is no field with that name, {field} is left as is.

A post has attributes that are not blocks. This is how they are
defined in the page template. After the page's title, put the post
attribute lines between ATTR-BEGIN and ATTR-END lines (one per line,
or separated with Shift-Enter). They can be in one top-level block or
in several. Every top-level block from the one with ATTR-BEGIN to the
one with ATTR-END is not copied. They are used to set the post's
attributes. FYI, all these are optional.

`
ATTR-BEGIN
title: {your-subject}
categories: {field}
tags: {field}
publish-date: {field}
expire-date: {field}
expire-time: {field}
ATTR-END
`

* title - post title. See Flamingo documention for how to change
  {your-subject} Default: UNDEFINED
* categories, tags - comma separated names or slugs. Only existing
  categories and tags are used; others are ignored. Default: empty
* publish-date - YYYY-MM-DD, time is set to 12:00. This date is kept
  when the post is published (a future date makes it "Scheduled").
  Default: the time the post is published.
* expire-date - YYYY-MM-DD. Saved in the ninja-auto-post-expire meta
  (_njtape_expiration_date). Default: not set.
* expire-time - HH:MM. Default: 00:00

ninja-auto-post-expire only acts on posts if its "Post" option is set
to "Yes" in it's settings, Post Expire Settings.

Invalid dates or times are ignored and logged to error_log().  Use a
plugin such as debug-log-manager to look at the error_log.

== Support, Source Code, and more Documentation  ==

See: https://github.com/TurtleEngr/WP-save-cf7-file-uploads

Issues can be posted there. And the README.md will point to where you
can find the latest downloadable "development" versions.
