#!/usr/bin/perl

require './geohelm-lib.pl';
require '../webmin/webmin-lib.pl';	#for OS detection

if ($ENV{REQUEST_METHOD} eq "POST") {
	&ReadParseMime();
}else {
	&ReadParse();
	$no_upload = 1;
}

if($in{'dismiss'}){
	&ui_print_header(undef, $text{'index_title'}, "", "intro", 1, 1);
	&ReadParse();
	&error_setup($text{'start_err'});

	open(my $fh, '>', "$module_config_directory/dismiss_geoexplorer.txt") or die "open:$!";
	print $fh "Dismissed\n";
	close $fh;

	print "<hr>GeoExplorer App warning was dismissed! <br>";
	&ui_print_footer("", $text{'index_return'});
	exit 0;
}

#setup geoexplorer in apache configure file
my $gs_proxy_file = '';
my %osinfo = &detect_operating_system();
if( $osinfo{'real_os_type'} =~ /centos/i){	#CentOS
	$gs_proxy_file = '/etc/httpd/conf.d/geoserver_proxy.conf';

}elsif( $osinfo{'os_type'} =~ /debian/i){	#ubuntu
	$gs_proxy_file = '/etc/apache2/conf-enabled/geoserver_proxy.conf';
}

if(-f $gs_proxy_file){
	open(my $fh, '>>', $gs_proxy_file) or die "open:$!";

	print $fh "ProxyPass /geoexplorer http://localhost:8080/geoexplorer\n";
	print $fh "ProxyPassReverse /geoexplorer http://localhost:8080/geoexplorer\n";

	close $fh;
}

#call tomcat install war page
my $url = &urlize("http://cdn.acugis.com/geohelm/external/geoexplorer.war");
&redirect("./install_war.cgi?source=2&url=$url&return=%2E%2E%2Fgeohelm%2F&returndesc=Geohelm&caller=geohelm");

&ui_print_footer("", $text{'index_return'});
