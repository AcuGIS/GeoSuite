#!/usr/bin/perl

require './geohelm-lib.pl';
require './pg-lib.pl';
require '../webmin/webmin-lib.pl';	

sub setup_apache_for_geoserver(){
	my $gs_proxy_file = '';
	my %osinfo = &detect_operating_system();
	if(	( $osinfo{'real_os_type'} =~ /centos/i) or	#CentOS
		($osinfo{'real_os_type'} =~ /fedora/i)	or  #Fedora
		($osinfo{'real_os_type'} =~ /scientific/i)	){
		if( ! -d '/etc/httpd/'){
			return 0;
		}
		$gs_proxy_file = '/etc/httpd/conf.d/geoserver_proxy.conf';

	}elsif( ($osinfo{'real_os_type'} =~ /ubuntu/i) or
			($osinfo{'real_os_type'} =~ /debian/i) 	){	#ubuntu or debian
		if( ! -d '/etc/apache2/'){
			return 0;
		}
		$gs_proxy_file = '/etc/apache2/conf-enabled/geoserver_proxy.conf';
	}

	if(! -f $gs_proxy_file){
		open(my $fh, '>', $gs_proxy_file) or die "open:$!";

		if(	($osinfo{'real_os_type'} =~ /centos/i) or	#CentOS
			($osinfo{'real_os_type'} =~ /fedora/i)	){	#Fedora

			&execute_command('setsebool httpd_can_network_connect 1');

			print $fh "LoadModule proxy_module 		modules/mod_proxy.so\n";
			print $fh "LoadModule proxy_http_module modules/mod_proxy_http.so\n";
			print $fh "LoadModule rewrite_module  	modules/mod_rewrite.so\n";

		}elsif( $osinfo{'os_type'} =~ /debian/i){	#ubuntu or debian

			print $fh "LoadModule proxy_module /usr/lib/apache2/modules/mod_proxy.so\n";
			print $fh "LoadModule proxy_http_module  /usr/lib/apache2/modules/mod_proxy_http.so\n";
			print $fh "LoadModule rewrite_module  /usr/lib/apache2/modules/mod_rewrite.so\n";
		}

		print $fh "ProxyRequests Off\n";
		print $fh "ProxyPreserveHost On\n";
		print $fh "    <Proxy *>\n";
		print $fh "       Order allow,deny\n";
		print $fh "       Allow from all\n";
		print $fh "    </Proxy>\n";
		print $fh "ProxyPass /geoserver http://localhost:8080/geoserver\n";
		print $fh "ProxyPassReverse /geoserver http://localhost:8080/geoserver\n";

		close $fh;
	}
}


# Check if config file exists
if (! -r $config{'geohelm_config'}) {
	&ui_print_header(undef, $text{'index_title'}, "", "intro", 1, 1);
	print &text('index_econfig', "<tt>$config{'geohelm_config'}</tt>",
		    "$gconfig{'webprefix'}/config.cgi?$module_name"),"<p>\n";
	&ui_print_footer("/", $text{"index"});
	exit;
}

# Check if tomcat exists
my $tomcat_ver = installed_tomcat_version();
if(!$tomcat_ver){
	&ui_print_header(undef, $text{'index_title'}, "", "intro", 1, 1);

	my $latest_ver = latest_tomcat_version();
	print &ui_buttons_row("tomcat_install.cgi", $text{'index_install'}, "Tomcat $latest_ver will be installed from Apache site.");
	&ui_print_footer("/", $text{"index"});
	exit;
}

my %version = get_acugeo_versions();

&ui_print_header("GeoHelm<sup>&copy</sup> by <a href='https://www.acugis.com' target='blank'>AcuGIS</a>.  Cited, Inc. 2017 ", $text{'index_title'}, "", "intro", 1, 1, 0,
	&help_search_link("tomcat", "geoserver", "man", "doc", "google"), undef, undef,
	"Tomcat $version{'number'} / Java $version{'jvm'}");

push(@links, "edit_manual.cgi");
push(@titles, $text{'manual_title'});
push(@icons, "images/manual.gif");

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
my $latest_ver = latest_tomcat_version();
if("v$tomcat_ver" ne "v$latest_ver"){
	print &ui_buttons_row("tomcat_upgrade.cgi", $text{'index_upgrade'}, "Tomcat will be updated to  $latest_ver. All WARs will be moved and config will be copied to new install!");
}
print &ui_buttons_end();

#Check for commands
if (!&has_command('java')) {
	print '<p>Warning: Java is not found. Install it manually or from the '.
		  "<a href='./edit_java.cgi?return=%2E%2E%2Fgeohelm%2F&returndesc=GeoHelm&caller=geohelm'>Java tab</a>";
}

if (!&has_command('unzip')) {
	print '<p>Warning: unzip command is not found. Install it manually or '.
		  "<a href='../software/install_pack.cgi?source=3&update=unzip&return=%2E%2E%2Fgeohelm%2F&returndesc=GeoHelm&caller=geohelm'>click here</a> to have it downloaded and installed.</p>";
}

my $pg_ver = get_installed_pg_version();
if(have_pg_repo() == 0){
	print '<p>Warning: PostgreSQL repository is not found. Install it from <a href="./pg_install.cgi">'.$text{'pg_inst_title'}.'</a>';
}

check_pg_ext_deps($pg_ver);

foreign_require('software', 'software-lib.pl');
my @pinfo = software::package_info('haveged', undef, );
if(!@pinfo){
	print "<p>Warning: haveged package is not installed. Install it manually or ".
		  "<a href='../software/install_pack.cgi?source=3&update=haveged&return=%2E%2E%2Fgeohelm%2F&returndesc=GeoHelm&caller=geohelm'>click here</a> to have it downloaded and installed.</p>";
}

#setup apache modules on CentOS and Ubuntu
setup_apache_for_geoserver();

#Check if bootstrap web application is installed
if (! -f "$module_config_directory/bootstraped.txt"){
	print '<p>Warning: Bootstrap web app is not installed in /var/www/html. '.
		  "<a href='./bootstrap_web_app.cgi?return=%2E%2E%2Fgeohelm%2F&returndesc=GeoHelm&caller=geohelm'>Click here</a>  to install it automatically";
	print " or <a href='./bootstrap_web_app.cgi?dismiss=1&return=%2E%2E%2Fgeohelm%2F&returndesc=GeoHelm&caller=geohelm'>Dismiss</a> this warning.";
}

# Check if geoexplorer webapp exists
my $catalina_home = get_catalina_home();
if ((! -f "$module_config_directory/dismiss_geoexplorer.txt") &&
	(! -d "$catalina_home/webapps/geoexplorer/") 				){
	if( -f "$catalina_home/webapps/geoexplorer.war"){
		print "<p>The GeoExplorer webapp is not deployed yet!";
	}else{
		print "<p>The GeoExplorer webapp direcrory <tt>$catalina_home/webapps/geoexplorer/</tt> does not exist. ".
			  "<a href='./install_geoexplorer.cgi?return=%2E%2E%2Fgeohelm%2F&returndesc=Geohelm&caller=geohelm'>Click here</a> to have it downloaded and installed";
		print " or <a href='./install_geoexplorer.cgi?dismiss=1&return=%2E%2E%2Fgeohelm%2F&returndesc=Geohelm&caller=geohelm'>Dismiss</a> this warning</p>";
	}
}

# Check if OpenLayers exists
if ((! -f "$module_config_directory/dismiss_openlayers.txt") &&
	(! -d "/var/www/html/OpenLayers") 				){
	print "<p>The OpenLayers direcrory <tt>/var/www/html/OpenLayers</tt> does not exist. ".
		  "<a href='./install_openlayers.cgi?return=%2E%2E%2Fgeohelm%2F&returndesc=GeoHelm&caller=geohelm'>Click here</a> to have it downloaded and installed";
	print " or <a href='./install_openloayers.cgi?dismiss=1&return=%2E%2E%2Fgeohelm%2F&returndesc=GeoHelm&caller=geohelm'>Dismiss</a> this warning</p>";
}

# Check if LeafletJS exists
if ((! -f "$module_config_directory/dismiss_leafletjs.txt") &&
	(! -d "/var/www/html/leafletjs") 				){
	print "<p>The LeafletJS direcrory <tt>/var/www/html/leafletjs</tt> does not exist. ".
		  "<a href='./install_leafletjs.cgi?return=%2E%2E%2Fgeohelm%2F&returndesc=GeoHelm&caller=geohelm'>Click here</a> to have it downloaded and installed";
	print " or <a href='./install_leafletjs.cgi?dismiss=1&return=%2E%2E%2Fgeohelm%2F&returndesc=GeoHelm&caller=geohelm'>Dismiss</a> this warning</p>";
}

if (!&has_command('shp2pgsql')) {
	print '<p>Warning: shp2pgsql command is not found.';
	print "<a href='../software/install_pack.cgi?source=3&update=$config{'shp2pgsql_pkg'}&return=%2E%2E%2Fgeohelm%2F&returndesc=GeoHelm&caller=geohelm'>Click here</a> to have it installed from postgis package.</p>";

}

&ui_print_footer("/", $text{"index"});
