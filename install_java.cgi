#!/usr/bin/perl

require './java-lib.pl';
require './tomcat-lib.pl';
require '../webmin/webmin-lib.pl';	#for OS detection
use File::Basename;

sub extract_java_archive{
	my $jdk_archive = $_[0];

	my $java_dir = '/usr/java';
	if( ! -d $java_dir){
		&make_dir($java_dir, 0755, 1);
	}

	$cmd_out='';
	$cmd_err='';
	print "<hr>Extracting $jdk_archive to $java_dir...<br>";
	local $out = &execute_command("tar -x --overwrite -f \"$jdk_archive\" -C/usr/java", undef, \$cmd_out, \$cmd_err, 0, 0);

	if($cmd_err){
		&error("Error: tar: $cmd_err");
	}else{
		$cmd_out = s/\r\n/<br>/g;
		print &html_escape($cmd_out);
	}

	my ($jdk_subver) = $jdk_archive =~ /8u([0-9]+)/;
	my $jdk_dir = $java_dir."/jdk1.8.0_".$jdk_subver;

	&set_ownership_permissions('root','root', 0755, $jdk_dir);
	&execute_command("chown -R root:root $jdk_dir", undef, \$cmd_out, \$cmd_err, 0, 0);
	if($cmd_err){
		&error("Error: chown: $cmd_err");
	}

	return $jdk_dir;
}

sub set_default_java{
	my $jdk_dir = $_[0];

	my $alt_cmd = "";
	if(has_command('alternatives')){        #CentOS
			$alt_cmd = 'alternatives';
	}elsif(has_command('update-alternatives')){     #ubuntu
			$alt_cmd = 'update-alternatives';
	}else{
			print "Warning: No alternatives command found<br>";
	}

	if($alt_cmd ne ""){
		print "Updating Java using $alt_cmd<br>";
		my @jdk_progs = ('java', 'jar', 'javac');
		foreach my $prog (@jdk_progs){

			$cmd_out='';
			$cmd_err='';
			local $out = &execute_command("$alt_cmd --install /usr/bin/$prog $prog $jdk_dir/bin/$prog 1", undef, \$cmd_out, \$cmd_err, 0, 0);
			      $out.= &execute_command("$alt_cmd --set $prog $jdk_dir/bin/$prog", undef, \$cmd_out, \$cmd_err, 0, 0);

			if($cmd_err){
				&error("Error: $alt_cmd: $cmd_err");
			}else{
				$cmd_out = s/\r\n/<br>/g;
				print &html_escape($cmd_out);
			}
		}
	}

	#set Java environment variables
	print "<hr>Setting Java environment variables...<br>";
	my %os_env;
	$os_env{'J2SDKDIR'}  = $jdk_dir;
	$os_env{'JAVA_HOME'} = $jdk_dir;
	$os_env{'DERBY_HOME'}= "$jdk_dir/db";
	$os_env{'J2REDIR'}	 = "$jdk_dir/jre";

	if(-e '/etc/profile.d/'){
		$os_env{'PATH'}		 = "\$PATH:$jdk_dir/bin:$jdk_dir/db/bin:$jdk_dir/jre/bin";
		write_env_file('/etc/profile.d/jdk8.sh', \%os_env, 1);
	}elsif(-e '/etc/environment'){
		read_env_file('/etc/environment', \%os_env);
		$os_env{'PATH'}		 = "$os_env{'PATH'}:$jdk_dir/bin:$jdk_dir/db/bin:$jdk_dir/jre/bin";
		write_env_file('/etc/environment', \%os_env, 0);
	}
}

$| = 1;

if ($ENV{REQUEST_METHOD} eq "POST") {
	&ReadParseMime();
}else {
	&ReadParse();
	$no_upload = 1;
}

&ui_print_header(undef, $text{'java_title'}, "");

if ($in{'source'} == 100) {	#download from Oracle site
	my ($jdk_name, $url) = split /=/, $in{'jdk_ver'};
	$in{'url'} = $url;	#set URL to be value of select box
	$in{'source'} = 2;	#install from URL
}

my $jdk_archive = process_file_source();
my $jdk_path    = extract_java_archive($jdk_archive);

if($in{'def_jdk'} == 1){
	set_default_java($jdk_path);
}

print "<hr>Done<br>";

&ui_print_footer("", $text{'index_return'});
