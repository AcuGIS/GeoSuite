#!/usr/bin/perl

require './tomcat-lib.pl'; #require

sub install_tomcat_from_archive{
	my $tomcat_ver = latest_tomcat_version();

	add_tomcat_user();
	download_and_install($tomcat_ver);

	setup_catalina_env($tomcat_ver);
	setup_tomcat_users($tomcat_ver);
	setup_tomcat_service($tomcat_ver);
	return 0;
}

&ui_print_header(undef, $text{'index_title'}, "", "intro", 1, 1);
&ReadParse();
&error_setup($text{'start_err'});
$err = install_tomcat_from_archive();
&error($err) if ($err != 0);

&ui_print_footer("", $text{'index_return'});
