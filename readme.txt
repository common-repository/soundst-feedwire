=== Soundst FeedWire ===
Contributors: SoundStrategies
Donate link: None
Tags: Sound Strategies, FeedWire
Requires at least: 3.0
Tested up to: 4.9.4
Stable tag: 1.3.4

Fetches content from multiple feeds and allows the administrator to select which headlines appear on the “Wire”.  The wire can be presented as either a widget or by using the short-code [soundstfeedwire] on a page. 

== Description ==

The Sound Strategies FeedWire plugin fetches content from multiple feeds and allows the administrator to select which headlines appear on the “Wire”.  The wire can be presented as either a widget or by using the short-code [soundstfeedwire] on a page. 

== Installation ==

1. Upload the ss-feed-wire folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Adjust the plugin settings

== Screenshots ==

== Changelog ==

= 1.3.4 =
1. plugin meta update

= 1.3.3 =
1. jquery lib include only if not included already
2. little refactoring
3. php 7 adoptation

= 1.3.2 =
1. debug triggering disabling

= 1.3.1 =
1. fixed arguments parsing and format (shortcode[space]arguments)

= 1.3.0 =
1.Added the new option to log the feeds processing status to the errors log.
2.Changed the admin Settings page - expanded the Manage Feeds table:
	- added the Update Status column (may have folowing value: success, failure or warning)
	- added the sliding section with the last update process status
3.Changed the XML RSS parsing. The parsing has now a "quirk mode" now: XML not neccessary should be valid, but warning is always thrown in this case.

= 1.2.3 =
- changed the shortcode output, Ajax now displays the content, and there is no need to reload the entire page when a new "filter" or "Entries per page" value is selected.

= 1.2.2 =
- changed the update processing for faster working
- changed the shortcode filter
- changed the shedule processing (now just one feed updated each time, when automatic update process started)

= 1.2.1 =
- changed the shortcode output for faster loading;
- updated the parsing of XML documents for better compatibility with more types of XML Docs;

= 1.2.0 =
1.Fixed bug with data duplicating.
2.Changed the widget output (now the plugin used js) for the better seo.
3.Added new Update feauture and abilty to update one feed separately from all others.
4.Added the new Setting Options:
	- Spacing for the title wrapping
	- Left and bottom padding (Widget Content Styles)
	- Top and bottom padding (Widget Content Styles)

= 1.1.2 =
Added the new setting options for the shortcode and widget content: Spacing for title wrapping

= 1.1.1 =
Fixed styles

= 1.1.0 =
Added the new options:
	- Days to keep wire
	- Maximum entries per page
Added new feature for news pagination
Added new feature for the media for wire
Added the "Upper HTML" option for the widget
Changed the "Additional HTML" option for the widget to "Lower HTML"

= 1.0.1 =
Added new options:
	- Date Format
	- Padding between title and source
	- Padding between entries
Added the "Additional HTML" option for the widget
Removed option for “Enable filter for page content”
Changed the shortcode content appearance
Fixed the post type columns bug

= 1.0.0 =
This is the initial build that was released to the WordPress community

== Upgrade Notice ==

= 1.0.0 =
This is the initial build that was released to the WordPress community
