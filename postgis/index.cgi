#!/usr/bin/perl

require './geohelm-lib.pl';
require './pg-lib.pl';
require '../webmin/webmin-lib.pl';	#for OS detection

# Check if config file exists
if (! -r $config{'postgis_config'}) {
	&ui_print_header(undef, $text{'index_title'}, "", "intro", 1, 1);
	print &text('index_econfig', "<tt>$config{'postgis_config'}</tt>",
		    "$gconfig{'webprefix'}/config.cgi?$module_name"),"<p>\n";
	&ui_print_footer("/", $text{"index"});
	exit;
}

if(-f "$module_root_directory/setup.cgi"){
	&redirect('setup.cgi');
}

&ui_print_header("PostGIS Webmin Module<sup></sup> by <a href='https://www.acugis.com' target='blank'>Cited, Inc.</a> 2023 ", $text{'index_title'}, "", "intro", 1, 1, 0,
	&help_search_link("GIS", "Postgre", "postgis", ));



push(@links, "pg_install.cgi");
push(@titles, $text{'pg_inst_title'});
push(@icons, "images/pg.png");

push(@links, "edit_pg_ext.cgi");
push(@titles, $text{'pg_ext_title'});
push(@icons, "images/postgis.png");

push(@links, "add_shape.cgi");
push(@titles, $text{'add_shape_title'});
push(@icons, "images/shp2pgsql.png");

push(@links, "add_raster.cgi");
push(@titles, $text{'add_raster_title'});
push(@icons, "images/raster2pgsql.png");

push(@links, "add_osm.cgi");
push(@titles, $text{'add_osm_title'});
push(@icons, "images/osm2pgsql.png");

push(@links, "edit_snapshots.cgi?mode=clone");
push(@titles, 'Clone');
push(@icons, "images/clone-database.png");

push(@links, "edit_snapshots.cgi?mode=create");
push(@titles, 'Snapshots');
push(@icons, "images/take-snapshot.png");

push(@links, "edit_snapshots.cgi?mode=restore");
push(@titles, 'Restore');
push(@icons, "images/restore-snapshot.png");

push(@links, "docs.cgi");
push(@titles, 'Docs');
push(@icons, "images/docs.png");


&icons_table(\@links, \@titles, \@icons, 4);



if( -d '/opt/pg_tileserv'){
print &ui_buttons_start();
	if (app_is_running('pg_tileserv') == 1) {
		print "pg_tileserv</br>";
		print &ui_buttons_row("crunchy_ctl.cgi", $text{'index_stop'}, $text{'index_stopmsg'}, '<input type="hidden" id="app_pg_tileserv" name="app_pg_tileserv" value="stop">');
		print &ui_buttons_row("crunchy_ctl.cgi", $text{'index_restart'}, $text{"index_restartmsg"}, '<input type="hidden" id="app_pg_tileserv" name="app_pg_tileserv" value="restart">');
	}else {
		print "PG TileServ is stopped.</br>";
		print &ui_buttons_row("crunchy_ctl.cgi", $text{'index_start'}, $text{'index_startmsg'}, '<input type="hidden" id="app_pg_tileserv" name="app_pg_tileserv" value="start">');
	}
&ui_buttons_end();
}



if( -d '/opt/pg_featureserv'){
print &ui_hr().&ui_buttons_start();
	print &ui_hr();
	if (app_is_running('pg_featureserv') == 1) {
		print "pg_featureserv</br>";
		print &ui_buttons_row("crunchy_ctl.cgi", $text{'index_stop'}, $text{'index_stopmsg_fs'}, '<input type="hidden" id="app_pg_featureserv" name="app_pg_featureserv" value="stop">');
		print &ui_buttons_row("crunchy_ctl.cgi", $text{'index_restart'}, $text{'index_restartmsg_fs'}, '<input type="hidden" id="app_pg_featureserv" name="app_pg_featureserv" value="restart">');
	}else {
		print "PG FeatureServ is stopped.</br>";
		print &ui_buttons_row("crunchy_ctl.cgi", $text{'index_start'}, $text{'index_startmsg_fs'}, '<input type="hidden" id="app_pg_featureserv" name="app_pg_featureserv" value="start">');
	}
&ui_buttons_end();
}



&ui_print_footer("/", $text{"index_return"});
