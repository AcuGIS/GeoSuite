#!/usr/bin/perl

require './geoserver-lib.pl';
require './tomcat-lib.pl';

if ($ENV{REQUEST_METHOD} eq "POST") {
	&ReadParseMime();
}else {
	&ReadParse();
	$no_upload = 1;
}


#export GEOSERVER_DATA_DIR to /var/lib/geoser
#my $tomcat_env = $catalina_home."/bin/setenv.sh";

#my %os_env;
#read_env_file($tomcat_env, \%os_env);
#$os_env{'GEOSERVER_DATA_DIR'} = "/var/lib/data";

#&make_dir($os_env{'GEOSERVER_DATA_DIR'}, 0755, 1);
#&set_ownership_permissions('tomcat','tomcat', undef, $os_env{'GEOSERVER_DATA_DIR'});

#write_env_file($tomcat_env, \%os_env, 0);

#call tomcat install war page
my $geo_ver = get_latest_geoserver_ver();
my $url = &urlize("http://sourceforge.net/projects/geoserver/files/GeoServer/$geo_ver/geoserver-$geo_ver-war.zip");

&redirect("./install_war.cgi?source=2&url=$url&return=%2E%2E%2Fgeohelm%2F&returndesc=Geohelm&caller=geohelm");

&ui_print_footer("", $text{'index_return'});
