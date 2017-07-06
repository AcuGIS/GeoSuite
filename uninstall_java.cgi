#!/usr/bin/perl

use File::Path 'rmtree';

require './java-lib.pl';
require './tomcat-lib.pl';
require '../webmin/webmin-lib.pl';	#For OS Fetection
&ReadParse();

&error_setup($text{'delete_err'});
&ui_print_header(undef, $text{'delete_title'}, "");

my $jdk_name = $in{'inst_jdk'};
$jdk_name || &error($text{'delete_enone'});

my $jdk_dir = '/usr/java/'.$jdk_name;
my $def_jdk = is_default_jdk($jdk_dir);

if(($def_jdk == 1) and ($in{'rm_def_jdk'} == 0)){
	print "Uninstall stopped, since $jdk_dir is default JDK.<br>";
	exit;
}

print "Removing $jdk_dir...<br>";

if($def_jdk == 1){
	print "Removing Java environment variables ...<br>";
	if(-f '/etc/profile.d/jdk8.sh'){
		unlink_file('/etc/profile.d/jdk8.sh');
	}elsif(-f '/etc/environment'){
		my %os_env;
		read_env_file('/etc/environment', \%os_env);
		delete $os_env{'J2SDKDIR'};
		delete $os_env{'JAVA_HOME'};
		delete $os_env{'DERBY_HOME'};
		delete $os_env{'J2REDIR'};
		write_env_file('/etc/environment', \%os_env, 0);
	}

	my $alt_cmd = "";
	if(has_command('alternatives')){        #CentOS
			$alt_cmd = 'alternatives';
	}elsif(has_command('update-alternatives')){     #ubuntu
			$alt_cmd = 'update-alternatives';
	}else{
			print "Warning: No alternatives command found<br>";
	}

	if($alt_cmd ne ""){
		print "Removing Java using $alt_cmd<br>";
		my @jdk_progs = ('java', 'jar', 'javac');
		foreach my $prog (@jdk_progs){

			$cmd_out='';
			$cmd_err='';
			local $out = &execute_command("$alt_cmd --remove $prog $jdk_dir/bin/$prog", undef, \$cmd_out, \$cmd_err, 0, 0);
			if($cmd_err){
				&error("Error: $alt_cmd: $cmd_err");
			}else{
				$cmd_out = s/\r\n/<br>/g;
				print &html_escape($cmd_out);
			}
		}
	}
}

if( -d $jdk_dir){
	rmtree($jdk_dir);
}

print "<hr>Uninstall of <tt>$jdk_name</tt> is successful<br>";

&ui_print_footer("", $text{'index_return'});

