=== Google Analytics Post Pageviews ===
Contributors: maximevalette
Donate link: http://maxime.sh/paypal
Tags: google, analytics, ga, post, pageviews, counter, visits
Requires at least: 3.0
Tested up to: 4.3
Stable tag: 1.3.8

Retrieves and displays the pageviews for each post by linking to your Google Analytics account.

== Description ==

This WordPress plugin links to your Google Analytics account to retrieve the pageviews for your posts.

Therefore you can easily include this number in your blog for yourself or all of your visitors.

== Installation ==

1. Copy the google-analytics-post-pageviews folder into wp-content/plugins
2. Activate the plugin through the Plugins menu
3. Configure from the new Post Pageviews Settings submenu

== Changelog ==

= 1.3.8 =
* Fix namespace unique ID first generation.
* Static translation namespace because i18n tools are dumb.

= 1.3.7 =
* Handling different DB namespaces for cache reset.
* Handling transients using other cache system.

= 1.3.6 =
* Reset cache also reset transient timeout values.

= 1.3.5 =
* Fixed wrong transient expiration delay.

= 1.3.4 =
* Try to display the cached number even if Google is disconnected.
* Check the array for websites results in the settings.

= 1.3.3 =
* Don't disconnect the account if the API HTTP code is something else than 403.

= 1.3.2 =
* Returns API error message from Google API.

= 1.3.1 =
* Fixed an issue with Google auth token refresh.

= 1.3 =
* Removed Google auth cronjobs and improved async re-authentication.

= 1.2.9 =
* Added more details in the website selection.
* Redirection after Google connection to avoid mis-auth.

= 1.2.8 =
* Huge improvements on the Google authentication mechanism.

= 1.2.7 =
* Improved the way the Google token is refreshed.
* Added an anti-flood system when the Google API requests are not successful.
* Added a link to empty the pageviews cache.

= 1.2.6 =
* Using international number formatting for the views count, and adding a parameter to disable it on function call.

= 1.2.5 =
* Avoid unnecessary API calls when the post is not published.

= 1.2.4 =
* Improved Views column in Posts list.
* Improved permalink detection.

= 1.2.3 =
* Fixed some settings saving.
* Improved API calls and error handling.

= 1.2.2 =
* Minor but essential fix in the code snippet.

= 1.2.1 =
* Added an optional Views column in Posts list.

= 1.2 =
* Updated curl_ and file_ methods to WP_Http requests.

= 1.1 =
* Changed the Google URLs to match the new Google Developers Console.
* Specified a unique slug to prevent redirect_uri_mismatch.

= 1.0.1 =
* Fixed a bug that prevented the token refresh.

= 1.0 =
* First version. Enjoy!
