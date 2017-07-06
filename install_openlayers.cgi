#!/usr/bin/perl

require './geohelm-lib.pl';
use File::Path 'rmtree';

sub latest_openlayers_version(){
	my $tmpfile = transname('download.html');
	&error_setup(&text('install_err3', "https://openlayers.org/download"));
	&http_download('openlayers.org', 443, '/download', $tmpfile, \$error, undef, 1);

	if($error){
		print &html_escape($error);
		die "Error: Failed to get latest version of OpenLayers";
	}

	my $latest_ver = '0.0.0';
	open(my $fh, '<', $tmpfile) or die "open:$!";
	while(my $line = <$fh>){
		
		if($line =~ /Downloads\sfor\sthe\sv([0-9\.]+)\srelease/){
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

	open(my $fh, '>', "$module_config_directory/dismiss_openlayers.txt") or die "open:$!";
	print $fh "Dismissed\n";
	close $fh;

	print "<hr>OpenLayers warning was dismissed! <br>";

}elsif(! -d '/var/www/html/OpenLayers'){

	if( ! -d '/var/www/html/'){
		print &html_escape("Error: /var/www/html/ is missing");
		&ui_print_footer("", $text{'index_return'});
		return 0;
	}

	
	my $ol_ver = latest_openlayers_version();

	my $tmpfile = transname("v${ol_ver}.zip");
	my $url = "https://github.com/openlayers/openlayers/releases/download/v${ol_ver}/v${ol_ver}.zip";
	$progress_callback_url = $url;

	my $dist = '-dist';	#empty string for full release or '-dist' for libs only

	&error_setup(&text('install_err3', $url));
	&http_download('github.com', 443, "/openlayers/openlayers/releases/download/v${ol_ver}/v${ol_ver}${dist}.zip", $tmpfile, \$error, \&progress_callback, 1);

	if($error){
		print &html_escape($error);
		die "Error: Failed to get latest OpenLayers archive";
	}

	my $ol_dir = unzip_me($tmpfile);

	print "Moving to /var/www/html/OpenLayers ...";
	rename_file($ol_dir."/v${ol_ver}${dist}", '/var/www/html/OpenLayers');
	&execute_command("chown -R root:root '/var/www/html/OpenLayers'");
}

&ui_print_footer("", $text{'index_return'});
