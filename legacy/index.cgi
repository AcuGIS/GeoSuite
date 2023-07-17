#!/usr/bin/perl

require './geohelm-lib.pl';
require './pg-lib.pl';
require '../webmin/webmin-lib.pl';

# Check if config file exists
if (! -r $config{'geohelm_config'}) {
	&ui_print_header(undef, $text{'index_title'}, "", "intro", 1, 1);
	print &text('index_econfig', "<tt>$config{'geohelm_config'}</tt>",
		    "$gconfig{'webprefix'}/config.cgi?$module_name"),"<p>\n";
	&ui_print_footer("/", $text{"index"});
	exit;
}

if(-f "$module_root_directory/setup.cgi"){
	&redirect("setup.cgi?mode=checks");
	exit;
}

my %version = get_acugeo_versions();

&ui_print_header("GeoHelm<sup>&copy</sup> by <a href='https://www.acugis.com' target='blank'>AcuGIS</a>.  Cited, Inc. 2023 ", $text{'index_title'}, "", "intro", 1, 1, 0,
	&help_search_link("tomcat", "geoserver", "man", "doc", "google"), undef, undef,
	"Tomcat $version{'number'} / Java $version{'jvm'}");

push(@links, "edit_manual.cgi");
push(@titles, $text{'manual_title'});
push(@icons, "images/edit-file.png");

push(@links, "edit_war.cgi");
push(@titles, $text{'wars_title'});
push(@icons, "images/war.png");

push(@links, "edit_java.cgi");
push(@titles, $text{'java_title'});
push(@icons, "images/java.png");

push(@links, "edit_geoserver.cgi");
push(@titles, $text{'extensions_title'});
push(@icons, "images/geoserver.png");

push(@links, "edit_pg_ext.cgi");
push(@titles, $text{'pg_ext_title'});
push(@icons, "images/postgis.png");

push(@links, "add_shape.cgi");
push(@titles, $text{'add_shape_title'});
push(@icons, "images/shp2pgsql.png");

#if PostgreSQL installer is available
if(-f "$module_root_directory/pg_install.cgi"){
	push(@links, "pg_install.cgi");
	push(@titles, $text{'pg_inst_title'});
	push(@icons, "images/pg.png");
}

push(@links, "edit_files.cgi");
push(@titles, $text{'editor_title'});
push(@icons, "images/map-js.png");

&icons_table(\@links, \@titles, \@icons, 4);

# Check if tomcat is running
print &ui_hr().&ui_buttons_start();
my ($running, $status) = &tomcat_service_ctl('status');
print "$status<br>";
if ($running == 1) {
	# Running .. offer to apply changes and stop
	print &ui_buttons_row("stop.cgi", $text{'index_stop'}, "$text{'index_stopmsg'}");
	print &ui_buttons_row("restart.cgi", $text{'index_restart'}, "$text{'index_restartmsg'}");
}else {
	# Not running .. offer to start
	print &ui_buttons_row("start.cgi", $text{'index_start'}, $text{'index_startmsg'});
}

#Check for an update of tomcat, once a day
my $tomcat_ver = installed_tomcat_version();
my $latest_ver = latest_tomcat_version($tomcat_ver);
if("v$tomcat_ver" ne "v$latest_ver"){
	print &ui_buttons_row("tomcat_upgrade.cgi", $text{'index_upgrade'}, "Tomcat will be updated to  $latest_ver. All WARs will be moved and config will be copied to new install!");
}
print &ui_buttons_end();

&ui_print_footer("/", $text{"index"});
