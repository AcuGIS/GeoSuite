BEGIN { push(@INC, ".."); };
use WebminCore;

require '../webmin/webmin-lib.pl';	#require
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

	my @pinfo = software::package_info("pgdg-$distro$pg_ver2", undef, );
	if(@pinfo){
		return 1;
	}
	return 0;
}

sub save_repo_ver(){
	my $pg_ver=$_[0];

	open(my $fh, '>', $module_config_directory.'/repo_ver.txt') or die "open:$!";
	print $fh "repo_ver=$pg_ver\n";
	close $fh;
}

sub get_installed_pg_version(){
	my %pg_env;

	if(! -f $module_config_directory.'/repo_ver.txt'){
		return undef;	#no repo file
	}

	read_env_file($module_config_directory.'/repo_ver.txt', \%pg_env);
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

sub check_pg_ext_deps{
	my $pg_ver = $_[0];

	my @ext_pkgs;

	my %osinfo = &detect_operating_system();
	if( ($osinfo{'os_type'} =~ /debian/i)){
		@ext_pkgs = ("postgresql-$pg_ver-postgis-scripts", "postgresql-$pg_ver-pgrouting-scripts", "postgresql-$pg_ver-pgrouting");
	}elsif( $osinfo{'os_type'} =~ /redhat/i){
		my $pg_ver2;
		($pg_ver2 = $pg_ver) =~ s/\.//;
		@ext_pkgs = ("postgis23_$pg_ver2", "pgrouting_$pg_ver2", "postgresql$pg_ver2-contrib");
	}

	foreach my $pkg (@ext_pkgs){
		my @pinfo = software::package_info($pkg);
		if(!@pinfo){
			print "<p>Warning: $pkg package is not installed. Install it manually or ".
				  "<a href='../software/install_pack.cgi?source=3&update=$pkg&return=%2E%2E%2Fgeohelm%2F&returndesc=GeoHelm&caller=geohelm'>click here</a> to have it downloaded and installed.</p>";
		}
	}
}
