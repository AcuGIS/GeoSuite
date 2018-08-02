=head1 geoserver-lib.pl

Functions for managing Geoserver webapp configuration files.

  foreign_require("geoserver", "geoserver-lib.pl");
  @sites = geoserver::list_geoserver_websites()

=cut

BEGIN { push(@INC, ".."); };
use warnings;
use WebminCore;
use File::Copy;

sub get_geoserver_version
{
	local %version;
	my %manifest;
	my $catalina_home = get_catalina_home();

	read_file_cached("$catalina_home/webapps/geoserver/META-INF/MANIFEST.MF", \%manifest, undef, undef, ":");

	($version{'number'}	= $manifest{'Implementation-Version'}) =~ s/[\s\r\n]+//g;
	($version{'jdk'}	= $manifest{'Build-Jdk'}) =~ s/[\s\r\n]+//g;

	return %version;
}

sub get_latest_geoserver_ver(){
	my $error;
	my $geo_version = shift;


	$url = "http://geoserver.org/";
	$tmpfile = &transname("page");
	&error_setup(&text('install_err3', $url));

	&http_download("geoserver.org", 80, "/", $tmpfile, \$error);

	#Parse Stable Version
	open(my $fb, '<', $tmpfile) or &webmin_log("open:$!");
	while (my $line = <$fb>){
		#<li><a href="/release/stable">2.11.0</a></li>
		if($line =~ /<li><a\s+href="\/release\/stable">([0-9\.]+)<\/a>/i){
			$geo_version = $1;
			last;
		}
	}
	close $fb;

	return $geo_version;
}

sub file_basename
{
	my $rv = $_[0];
	$rv =~ s/^.*[\/\\]//;
	return $rv;
}

1;
