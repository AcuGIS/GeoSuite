#!/usr/bin/perl

require './geosuite-lib.pl';
&ReadParse();


my %version = &get_acugeo_versions();
&ui_print_header(undef, $text{'extensions_title'}, "", "intro", 1, 1, 0,
	&help_search_link("geoserver", "man", "doc", "google"), undef, undef,
	"ver. ".$version{'geoserver_ver'}." / built with JDK ".$version{'geoserver_build'});


my $catalina_home = get_catalina_home();
if (! -d "$catalina_home/webapps/geoserver/") {
	print "<p>The Geoserver webapp direcrory <tt>$catalina_home/webapps/geoserver/</tt> does not exist. ".
			  "<a href='./install_geoserver.cgi?return=%2E%2E%2Fgeosuite%2F&returndesc=Geohelm&caller=geosuite'>Click here</a> to have it downloaded and installed.</p>";
}else{
	print "Geoserver webapp is installed in <tt>$catalina_home/webapps/geoserver/</tt>";
}


&ui_print_footer("", $text{'index_return'});
