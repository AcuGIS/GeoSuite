#!/usr/bin/perl

require './geohelm-lib.pl';
use File::Path 'rmtree';

sub latest_leafletjs_version(){
	my $tmpfile = transname('download.html');
	&error_setup(&text('install_err3', "http://leafletjs.com/download.html"));
	&http_download('leafletjs.com', 80, '/download.html', $tmpfile, \$error);

	if($error){
		print &html_escape($error);
		die "Error: Failed to get latest version of LeafletJS";
	}

	my $latest_ver = '0.0.0';
	open(my $fh, '<', $tmpfile) or die "open:$!";
	while(my $line = <$fh>){
		if($line =~ /cdn\.leafletjs\.com\/leaflet\/v([0-9\.]+)\/leaflet\.zip/){
			$latest_ver = $1;
			last;
		}
	}
	close $fh;

	return $latest_ver;
}

if ($ENV{REQUEST_METHOD} eq "POST") {
	&ReadParseMime();
}else {
	&ReadParse();
	$no_upload = 1;
}

&ui_print_header(undef, $text{'index_title'}, "", "intro", 1, 1);

if($in{'dismiss'}){

	&error_setup($text{'start_err'});

	open(my $fh, '>', "$module_config_directory/dismiss_leafletjs.txt") or die "open:$!";
	print $fh "Dismissed\n";
	close $fh;

	print "<hr>LeafletJS warning was dismissed! <br>";

}elsif(! -d '/var/www/html/leafletjs'){

	if( ! -d '/var/www/html/'){
		print &html_escape("Error: /var/www/html/ is missing");
		&ui_print_footer("", $text{'index_return'});
		return 0;
	}

	my $ll_ver = latest_leafletjs_version();

	my $tmpfile = transname("leaflet.zip");
	my $url = "http://cdn.leafletjs.com/leaflet/v${ll_ver}/leaflet.zip";
	$progress_callback_url = $url;

	&error_setup(&text('install_err3', $url));
	&http_download('cdn.leafletjs.com', 80, "/leaflet/v${ll_ver}/leaflet.zip", $tmpfile, \$error, \&progress_callback);

	if($error){
		print &html_escape($error);
		die "Error: Failed to get latest LeafletJS archive";
	}

	my $ll_dir = unzip_me($tmpfile);

	print "Moving to /var/www/html/leafletjs ...";
	rename_file($ll_dir, '/var/www/html/leafletjs');
	&execute_command("chown -R root:root '/var/www/html/leafletjs'");
}

&ui_print_footer("", $text{'index_return'});
