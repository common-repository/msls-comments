=== Multisite Language Switcher Comments by Eyga.net ===
Contributors: DSmidgy
Donate link: http://blog.slo-host.com/wp-plugins/
Tags: multilingual
Requires at least: 3.2
Tested up to: 6.4

Extension for Multisite Language Switcher plugin. All comments posted on translation-joined pages are displayed on all translation-joined posts.

== Description ==

= Installation instructions have changed in this version! =

This is extension for Multisite Language Switcher plugin http://wordpress.org/plugins/multisite-language-switcher/

All comments posted on translation-joined pages are displayed on all translation-joined posts.
See installation instructions if you want to store all comments into one blog. Advantages: all comments have reply links.

== Installation ==

You need to edit the comments.php in your theme folder if you want to store all the comments into one (primary) blog:<br>
* activate the plugin first,<br>
* find "comment_form" function call,<br>
* add "MslsComments::switchBlog();" just in front of it,<br>
* add "MslsComments::restoreBlog();" just below it,<br>
* change the "comment_form();" to "comment_form( array(), MslsComments::postId() );",<br>
* calls don't have to be removed if you switch off primary blog functionality.

== Frequently Asked Questions ==

To do:<br>
* comments counters on multiple post web pages do not work correctly (total = current on page + 1 if post is valid)<br>
* notifications are always send in the primary blog's language, when storing all comments in one blog<br>

Limitations:<br>
* comment_id counter in a blog should not be higher than 100,000,000

== Changelog ==

= 0.1 =
* First version

= 0.1.1 =
* Fixing readme file

= 0.2 =
* Comments from all linked posts are displayed on a chosen post. New comments can still be stored in the first blog.
* Comment counters on single post now work correctly.
* Hidden "reply" links on comments from non-primary blogs.
* Hidden "edit" links on comments from other blogs.
* Simplification of installation procedure.

= 0.2.1 =
* Fixing readme file

= 0.3 =
* Better support for displaying only comments from joined blogs (when not storing them all in one blog).

= 0.4 =
* Writing comments to active blog when it is not joined to primary blog.

= 0.5 =
* Update comment counters on all joined blogs.

= 0.6 =
* Settings are now stored in site options (wp_sitemeta table, msls_comments field). You have to (for now) manually edit SQL table to change settings.
* Fixes on writing comments to active blog when it is not joined to primary blog.

= 0.7 =
* PHP compatibility fixes.

= 0.8 =
* WordPress v5.0 compatibility fixes.
