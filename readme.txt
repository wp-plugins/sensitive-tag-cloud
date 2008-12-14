=== SensitiveTagCloud ===
Contributors: reneade
Donate link: http://www.rene-ade.de/stichwoerter/spenden
Tags: widget, sidebar, posts, tags, categories, category, navigation, widgets, links, tag, tagcloud, sensitive, condition, stc
Stable tag: trunk
Requires at least: 2.3
Tested up to: 2.7

This wordpress plugin provides a tagcloud that shows tags depending of the current context (e.g. Category, Author, Tag, Post) only. The style and sizes are configurable. The tag-links of the cloud can be restricted to the current category or current selected tag.

== Description ==

This wordpress plugin provides a configurable tagcloud that shows tags depending of the current context only. 
For example the tagcloud shows only tags that really occur in the current category, within the current date-, author-, tag- archive or your search results. 
And the widget can be configured to be only visible if viewing a tag archive, category, a sinlge post or even only if viewing the searchresults for example. 
It is also possible to restrict the links of the tag cloud to the current viewing tag archive or category: If you click on the tag "test1" within the tag cloud of the tag archive of "test2" the target page will only contain posts that have both tags.
Of course, the style and sizes of the tagcloud can be configured.

Plugin Website: http://www.rene-ade.de/inhalte/wordpress-plugin-sensitivetagcloud.html
Comments are welcome! And of course, I also like presents of my Amazon-Wishlist (http://www.rene-ade.de/inhalte/amazon-wunschliste.html) or paypal donations (http://www.rene-ade.de/inhalte/paypal-spende.html). :-)

== Installation ==

1. Upload the folder 'sensitive-tag-cloud' with all files to '/wp-content/plugins' on your webserver
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add the widget "sensitive tag cloud" to your sidebar and configure it as you like

German ScreenShots: sensitive-tag-cloud_install_de.jpg

IF YOUR THEME DOES NOT SUPPORT SIDEBAR WIDGETS:
- Use the page "SensitiveTagCloud" under the "Presentation"/"Themes"-menu of your admin panel to configure the SensitiveTagCloud 
- Add the following code to your template file where you like to output the SensitiveTagCloud:
  ' 
  <?php 
    if( function_exists("stc_widget") )
      stc_widget(); 
  ?> 
  '