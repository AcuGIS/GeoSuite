#!/usr/bin/perl

require './geohelm-lib.pl';
&ReadParse();

#&ui_print_header(undef, $text{'extensions_title'}, "");
my %version = &get_acugeo_versions();
&ui_print_header(undef, $text{'extensions_title'}, "", "intro", 1, 1, 0,
	&help_search_link("geoserver", "man", "doc", "google"), undef, undef,
	"ver. ".$version{'geoserver_ver'}." / built with JDK ".$version{'geoserver_build'});

# Check if geoserver webapp exists
my $catalina_home = get_catalina_home();
if (! -d "$catalina_home/webapps/geoserver/") {
	&ui_print_header(undef, $text{'index_title'}, "", "intro", 1, 1);

	print "<p>The Geoserver webapp direcrory <tt>$catalina_home/webapps/geoserver/</tt> does not exist. ".
			  "<a href='./install_geoserver.cgi?return=%2E%2E%2Fgeohelm%2F&returndesc=Geohelm&caller=geohelm'>Click here</a> to have it downloaded and installed.</p>";
	&ui_print_footer("/", $text{"index"});
	exit;
}


&ui_print_footer("", $text{'index_return'});
