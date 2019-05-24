#!/usr/bin/perl

require './java-lib.pl';
require './tomcat-lib.pl';
require '../webmin/webmin-lib.pl';	#require
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
	local $out = &execute_command("tar -x -v --overwrite -f \"$jdk_archive\" -C/usr/java", undef, \$cmd_out, \$cmd_err, 0, 0);

	my $jdk_tar_first_line = ( split /\n/, $cmd_out )[0];
	my $jdk_dir = $java_dir."/".(split /\//, $jdk_tar_first_line)[0];

	if($cmd_err){
		$cmd_err = s/\n/<br>/g;
		&error("Error: tar: $cmd_err");
	}else{
		$cmd_out = s/\n/<br>/g;
		print &html_escape($cmd_out);
	}

	&set_ownership_permissions('root','root', 0755, $jdk_dir);
	&execute_command("chown -R root:root $jdk_dir", undef, \$cmd_out, \$cmd_err, 0, 0);
	if($cmd_err){
		$cmd_err = s/\n/<br>/g;
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
				$cmd_err = s/\n/<br>/g;
				&error("Error: $alt_cmd: $cmd_err");
			}else{
				$cmd_out = s/\n/<br>/g;
				print &html_escape($cmd_out);
			}
		}
	}

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

if($ENV{'CONTENT_TYPE'} =~ /boundary=(.*)$/) { &ReadParseMime(); }
else { &ReadParse(); $no_upload = 1; }

&ui_print_header(undef, $text{'java_title'}, "");

if ($in{'source'} == 100) {
	my ($jdk_name, $url) = split /=/, $in{'jdk_ver'};
	$in{'url'} = $url;
	$in{'source'} = 2;
}

my $jdk_archive = process_file_source();
my $jdk_path    = extract_java_archive($jdk_archive);

if($in{'def_jdk'} == 1){
	set_default_java($jdk_path);
}

print "<hr>Done<br>";

&ui_print_footer("", $text{'index_return'});
