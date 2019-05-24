=head1 java-lib.pl

Functions for managing Oracle JDK installations.

  foreign_require("tomcat", "tomcat-lib.pl");
  @sites = tomcat::list_tomcat_websites()

=cut

BEGIN { push(@INC, ".."); };
use warnings;
use WebminCore;

foreign_require('software', 'software-lib.pl');

sub get_openjdk_versions(){
	my $search = 'openjdk-[0-9]+-jdk$';
	if (defined(&software::update_system_search)) {
  	# Call the search function
    @avail = &software::update_system_search($search);
  } else {
  	# Scan through list manually
  	@avail = &software::update_system_available();
  	@avail = grep { $_->{'name'} =~ /\Q$search\E/i } @avail;
  }

	my %openjdk_versions;
	foreach $a (@avail) {
		if($a->{'name'} =~ /openjdk-([0-9]+)-jdk/){
			$openjdk_versions{$1} = $a->{'name'};
		}
	}
	return %openjdk_versions;
}

sub get_latest_jdk_version(){
	my $error;
	my $url = 'https://www.oracle.com/technetwork/java/javase/downloads/index.html';
	$tmpfile = &transname("javase.html");
	&error_setup(&text('install_err3', $url));
	&http_download("www.oracle.com", 443, "/technetwork/java/javase/downloads/index.html", $tmpfile, \$error,
					undef, 1, undef, 0, 0, 1);

	my $jdk_mv = 12;	#JDK major version
	my $download_num = '';
	open(my $fh, '<', $tmpfile) or die "open:$!";
	while(my $line = <$fh>){
		if($line =~ /\/technetwork\/java\/javase\/downloads\/jdk([0-9]+)-downloads-([0-9]+)\.html/){
			$jdk_mv = $1;
			$download_num = $2;
			last;
		}
	}
	close $fh;

	$url = "https://www.oracle.com/technetwork/java/javase/downloads/jdk$jdk_mv-downloads-$download_num.html";
	$tmpfile = &transname("sdk.html");
	&error_setup(&text('install_err3', $url));
	my %cookie_headers = ('Cookie'=> 'oraclelicense=accept-securebackup-cookie');
	&http_download("www.oracle.com", 443,"/technetwork/java/javase/downloads/jdk$jdk_mv-downloads-$download_num.html",
					$tmpfile, \$error, undef, 1, undef, undef, 0, 0, 1, \%cookie_headers);

	my %java_tar_gz;
	open($fh, '<', $tmpfile) or die "open:$!";
	while(my $line = <$fh>){

		if($line =~ /"filepath":"(https:\/\/download.oracle.com\/otn-pub\/java\/jdk\/([a-z0-9-\.+]+)\/[a-z0-9]+\/jdk-[a-z0-9-\.]+_linux-x64_bin.tar.gz)/){
			$java_tar_gz{$2} = $1;
			last;
		}
	}
	close $fh;

	return %java_tar_gz;
}

sub get_installed_jdk_versions(){
	my @jdks = get_installed_oracle_jdk_versions();

	push(@jdks, get_installed_openjdk_versions());
	return @jdks;
}

sub get_installed_openjdk_versions{

	my @pkgs = ();

	my $cmd_out='';
	my $cmd_err='';
	if(has_command('rpm')){
		local $out = &execute_command("rpm -q --queryformat \"%{NAME}\n\" $pkg_list", undef, \$cmd_out, \$cmd_err, 0, 0);

		my @lines = split /\n/, $cmd_out;
		foreach my $line (@lines){
			if($line =~ /^(java-[0-9\.]+-openjdk)-.*/i){	#package pgdg96-centos is not installed
				push(@pkgs, $1);
			}
		}
	}elsif(has_command('dpkg')){
		local $out = &execute_command("dpkg -l \"*openjdk*\"", undef, \$cmd_out, \$cmd_err, 0, 0);

		my %all_pkgs;
		my @lines = split /\n/, $cmd_out;
		foreach my $line (@lines){
			if($line =~ /^(..)\s+(openjdk-[0-9\.]*)-.*:.*/i){
				my $pkg = $2;
				if($1 =~ /[uirph]i/){
					$all_pkgs{$pkg} = 1;
				}
			}
		}
		@pkgs = keys %all_pkgs;
	}else{
		my @dirs;
	    opendir(DIR, '/usr/java/') or return @dirs;
	    @dirs
	        = grep {
		    /^jdk-[0-9\.]+/
	          && -d "/usr/java/$_"
		} readdir(DIR);
	  closedir(DIR);
	}

	return sort @pkgs;
}

sub get_installed_oracle_jdk_versions{
	my @dirs;
  opendir(DIR, '/usr/java/') or return @dirs;
  @dirs	= grep {
			/^jdk-[0-9\.]+/
			&& -d "/usr/java/$_"
	} readdir(DIR);
  closedir(DIR);

  return sort @dirs;
}

sub is_default_jdk{
	my $jdk_dir = $_[0];

	my %os_env;
	if(-f '/etc/profile.d/jdk.sh'){
		read_env_file('/etc/profile.d/jdk.sh', \%os_env);
	}elsif(-f '/etc/environment'){
		read_env_file('/etc/environment', \%os_env);
	}

	if($os_env{'JAVA_HOME'} eq $jdk_dir){
		return 1;
	}else{
		return 0;
	}
}

sub get_java_version(){
	local %version;
	local $out = &backquote_command('java \-version 2>&1');

	if ($out =~ /java\sversion\s\"([0-9]\.([0-9])\.[0-9]_[0-9]+)\"/) {
		$version{'major'} = $2;
		$version{'full'} = $1;
	}else {
		$version{'major'} = 0;
		$version{'full'} = $out;
	}
	return %version;
}

sub get_java_home(){
	my %jdk_ver = get_java_version();
	return '/usr/java/jdk-'.$jdk_ver{'full'};
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
		write_env_file('/etc/profile.d/jdk.sh', \%os_env, 1);
	}elsif(-e '/etc/environment'){
		read_env_file('/etc/environment', \%os_env);
		$os_env{'PATH'}		 = "$os_env{'PATH'}:$jdk_dir/bin:$jdk_dir/db/bin:$jdk_dir/jre/bin";
		write_env_file('/etc/environment', \%os_env, 0);
	}
}

sub unset_default_java{
	my $jdk_dir = $_[0];
	print "Removing Java environment variables ...<br>";
	if(-f '/etc/profile.d/jdk.sh'){
		unlink_file('/etc/profile.d/jdk.sh');
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

sub get_jdk_dir_by_name{
	my $jdk_name = $_[0];
	if($jdk_name =~ /.*openjdk.*/){

		my $jdk_ver = (split /-/, $jdk_name)[1];	#get version from name
		$jdk_dir = '/usr/lib/jvm/java-'.$jdk_ver.'-openjdk';

		#On Ubuntu the arch is appended to jdk dir
		if(! -d $jdk_dir){
			$jdk_dir = $jdk_dir."-amd64";
		}

	}else{
		$jdk_dir = '/usr/java/'.$jdk_name;
	}
	return $jdk_dir;
}

1;
