#!/usr/bin/perl

require './geohelm-lib.pl';

sub setup_bootstrap_web_app(){

	my $www_dir = '/var/www/html';

	if( ! -d $www_dir){
		print &html_escape("Error: $www_dir is missing");
		return 0;
	}

	#download bootstrap web app zip
	my $url = "https://cdn.acugis.com/geohelm/docs.tar.bz2";
	$progress_callback_url = $url;
	&error_setup(&text('install_err3', $url));
	my $tmpfile = &transname("docs.tar.bz2");
	&http_download('cdn.acugis.com', 443, "/geohelm/docs.tar.bz2", $tmpfile, \$error, \&progress_callback, 1);

	if($error){
		print &html_escape($error);
		return 1;
	}

	#unzip extension to temp dir
	$cmd_out;
	$cmd_err;
	print "<hr>Extracting docs ...<br>";
	local $out = &execute_command("tar -x --overwrite -f \"$tmpfile\" -C\"$www_dir\"", undef, \$cmd_out, \$cmd_err, 0, 0);

	if($cmd_err){
		&error("Error: tar: $cmd_err");
	}else{
		$cmd_out = s/\r\n/<br>/g;
		print &html_escape($cmd_out);
	}

	open(my $fh, '>', "$module_config_directory/bootstraped.txt") or die "open:$!";
	print $fh "Installed\n";
	close $fh;

	print "Done<br>";

	return 0;
}


&ui_print_header(undef, $text{'index_title'}, "", "intro", 1, 1);
&ReadParse();
&error_setup($text{'start_err'});
if($in{'dismiss'}){
	open(my $fh, '>', "$module_config_directory/bootstraped.txt") or die "open:$!";
	print $fh "Dismissed\n";
	close $fh;

	print "<hr>Bootstrap Web App warning was dismissed! <br>";
}else{
	$err = setup_bootstrap_web_app();
	&error($err) if ($err != 0);
}

&ui_print_footer("", $text{"index_return"});
