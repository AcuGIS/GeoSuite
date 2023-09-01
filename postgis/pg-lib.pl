BEGIN { push(@INC, ".."); };
use WebminCore;

require '../webmin/webmin-lib.pl';	#for OS detection
foreign_require('software', 'software-lib.pl');
foreign_require('postgresql', 'postgresql-lib.pl');

sub check_pg_repo_apt(){
	if( -f '/etc/apt/sources.list.d/pgdg.list'){
		return 1;
	}
	return 0;
}

sub check_pg_repo_yum{
	my $pg_ver = $_[0];
	my $distro = lc $_[1];
	my $pg_ver2;
	($pg_ver2 = $pg_ver) =~ s/\.//;

	if($distro eq 'fedora'){
		$distro = "fedora";
	}else{
		$distro = "redhat";	#centos, redhat, scientific
	}

	my @pinfo = software::package_info("pgdg-$distro-repo", undef, );
	if(@pinfo){
		return 1;
	}
	return 0;
}

sub save_repo_ver{
	my $pg_ver=$_[0];

	open(my $fh, '>', $module_config_directory.'/repo_ver.txt') or die "open:$!";
	print $fh "repo_ver=$pg_ver\n";
	close $fh;
}

sub get_installed_pg_version(){
	my %pg_env;

	if(! -f $module_config_directory.'/repo_ver.txt'){
		&open_execute_command(CMD, 'psql -V', 1);
	  while(my $line = <CMD>) {
	    if ($line =~ /psql \(PostgreSQL\) ([0-9]+)/i) {
	  		$pg_env{'repo_ver'} = $1;
				save_repo_ver($1);
	      last;
	  	}
	  }
	  close(CMD);
	}else{
		read_env_file($module_config_directory.'/repo_ver.txt', \%pg_env);
	}

	return $pg_env{'repo_ver'};
}

sub have_pg_repo(){
	my $found = 0;	#1 if repo is found

	my $pg_ver = get_installed_pg_version();
	if (!$pg_ver){
		return 0;
	}

	my %osinfo = &detect_operating_system();
	if( $osinfo{'os_type'} =~ /redhat/i){	#other redhat

		my @temp = split /\s/, $osinfo{'real_os_type'};
		my $distro = $temp[0];
		if($distro eq "Scientific"){
			$distro = 'sl';
		}

		$found = check_pg_repo_yum($pg_ver, $distro);
	}elsif( $osinfo{'os_type'} =~ /debian/i){
		$found = check_pg_repo_apt();
	}

	return $found;
}

sub get_snapshots_dir(){
	my $bkup_dir = '/opt/snapshots';
	if(! -d $bkup_dir){
		&postgresql::make_backup_dir($bkup_dir);
	}
	return $bkup_dir;
}


sub get_db_snapshots(){
	my $db_name = $_[0];
	#show readme
	opendir(DIR, get_snapshots_dir()) or die $!;
	my @snapshots = grep{/^\d{4}\-\d{2}\-\d{2}\-\d{2}\-\d{2}_${db_name}\./}readdir DIR;
	closedir(DIR);

	return sort @snapshots;
}

sub get_shp2pgsql_pkg_name{
	my $pg_ver = $_[0];

	my %osinfo = &detect_operating_system();
	if( $osinfo{'os_type'} =~ /debian/i){	#debian, ubuntu, etc
		return 'postgis';
	}elsif( $osinfo{'os_type'} =~ /arch/i){	#Arch
		return 'postgis';
	}elsif($osinfo{'os_type'} =~ /suse/i){	#Suse
		my $pg_ver2;
		($pg_ver2 = $pg_ver) =~ s/\.//;
		return "postgresql$pg_ver2-postgis postgresql$pg_ver2-postgis-utils";
	}elsif( $osinfo{'os_type'} =~ /redhat/i){	#other redhat

		return "postgis30_$pg_ver-client";
	}

	return undef;
}

sub get_postgis_pkg_name{
	my $pg_ver = $_[0];

	my %osinfo = &detect_operating_system();
	if( $osinfo{'os_type'} =~ /debian/i){	#debian, ubuntu, etc
		return 'postgis';
	}elsif( $osinfo{'os_type'} =~ /arch/i){	#Arch
		return 'postgis';
	}elsif($osinfo{'os_type'} =~ /suse/i){	#Suse
		my $pg_ver2;
		($pg_ver2 = $pg_ver) =~ s/\.//;
		return "postgresql$pg_ver2-postgis";
	}elsif( $osinfo{'os_type'} =~ /redhat/i){	#other redhat

		return "postgis30_$pg_ver";
	}

	return undef;
}

sub pg_list_databases{
	local $t = &postgresql::execute_sql_safe('template1', 'select datname from pg_database order by datname');
	return sort { lc($a) cmp lc($b) } map { $_->[0] } @{$t->{'data'}};
}
