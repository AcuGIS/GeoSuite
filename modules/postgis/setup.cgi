#!/usr/bin/perl

require './geosuite-lib.pl';
require './pg-lib.pl';
require '../webmin/webmin-lib.pl';	#for OS detection

foreign_require('apache', 'apache-lib.pl');
foreign_require('software', 'software-lib.pl');

$crunchy_user = 'pgis';
$crunchy_db = 'postgisftw';
$db_pass = '';
$www_user = 'www-data';

sub get_http_conf_path{
	my $filename = $_[0];
	my $filepath = '';
	my $avail = $_[1];

	if(	( $osinfo{'real_os_type'} =~ /centos/i) or	#CentOS
			($osinfo{'real_os_type'} =~ /fedora/i)	or  #Fedora
			($osinfo{'real_os_type'} =~ /scientific/i)	){
		$filepath = '/etc/httpd/conf.d/'.$filename;

	}elsif( ($osinfo{'real_os_type'} =~ /ubuntu/i) or
					($osinfo{'real_os_type'} =~ /debian/i) 	){	#ubuntu or debian
		if($avail){
			$filepath = '/etc/apache2/conf-available/'.$filename;
		}else{
			$filepath = '/etc/apache2/conf-enabled/'.$filename;
		}
	}
	return $filepath;
}

sub latest_leafletjs_version(){
	my $tmpfile = download_file('https://leafletjs.com/download.html');

	my $latest_url = 'https://leafletjs-cdn.s3.amazonaws.com/content/leaflet/v1.9.3/leaflet.zip';
	open(my $fh, '<', $tmpfile) or die "open:$!";
	while(my $line = <$fh>){
		if($line =~ /(https:\/\/.*leaflet\/v[0-9\.]+\/leaflet\.zip)/){
			$latest_ver = $1;
			last;
		}
	}
	close $fh;

	return $latest_url;
}

sub install_leafletjs{
	if( -d '/var/www/html/leafletjs'){
		return 0;
	}

	my $ll_url = latest_leafletjs_version();

	my $tmpfile = download_file($ll_url);
	my $ll_dir = unzip_me($tmpfile);

	print "Moving to /var/www/html/leafletjs ...";
	rename_file($ll_dir, '/var/www/html/leafletjs');
	exec_cmd("chown -R $www_user:$www_user '/var/www/html/leafletjs'");
}

sub latest_openlayers_version(){
	my $tmpfile = download_file('https://openlayers.org/download');
	my $latest_ver = '0.0.0';
	open(my $fh, '<', $tmpfile) or die "open:$!";
	while(my $line = <$fh>){
		#Downloads for the v4.2.0 release
		if($line =~ /Downloads\sfor\sthe\sv([0-9\.]+)\srelease/){
			$latest_ver = $1;
			last;
		}
	}
	close $fh;

	return $latest_ver;
}

sub install_openlayers{
	if( -d '/var/www/html/OpenLayers'){
		return 0;
	}

	#get OpenLayers version
	my $ol_ver = latest_openlayers_version();
	my $dist = '-package'; #'-site' string for full release or '-package' for libs only
  my $tmpfile = download_file("https://github.com/openlayers/openlayers/releases/download/v${ol_ver}/v${ol_ver}${dist}.zip");
	if(!$tmpfile){
		die "Error: Failed to get latest OpenLayers archive";
	}

	my $ol_dir = unzip_me($tmpfile);

	print "Moving ${ol_dir} to /var/www/html/OpenLayers ...";
	rename_file($ol_dir, '/var/www/html/OpenLayers');
	&exec_cmd("chown -R $www_user:$www_user '/var/www/html/OpenLayers'");
}

sub install_bootstrap_web_app(){

	my $www_dir = '/var/www/html';

	if( ! -d $www_dir){
		print &html_escape("Error: $www_dir is missing");
		return 0;
	}

	#download bootstrap web app zip
	my $tmpfile = download_file("http://cdn.acugis.com/postgis/docs.tar.bz2");
	if(!$tmpfile){
		return 1;
	}

	#unzip extension to temp dir
	print "<hr>Extracting docs ...<br>";
	exec_cmd("tar -x --overwrite -f \"$tmpfile\" -C\"$www_dir\"");
	&exec_cmd("chown -R $www_user:$www_user '$www_dir'");

	open(my $fh, '>', "$module_config_directory/bootstraped.txt") or die "open:$!";
	print $fh "Installed\n";
	close $fh;

	print "Done<br>";

	return 0;
}

sub check_pg_ext_deps{
	my $pg_ver = $_[0];
	my @ext_pkgs;

	if( ($osinfo{'os_type'} =~ /debian/i)){
		@ext_pkgs = ("postgresql-$pg_ver-pgrouting-scripts", "postgresql-$pg_ver-pgrouting");
	}elsif( $osinfo{'os_type'} =~ /redhat/i){
		my $pg_ver2;
		($pg_ver2 = $pg_ver) =~ s/\.//;
		my $postgis_pkg = get_postgis_pkg_name($pg_ver);
		@ext_pkgs = ($postgis_pkg, "pgrouting_$pg_ver2", "postgresql$pg_ver2-contrib");
	}

	my @pkg_missing;
	foreach my $pkg (@ext_pkgs){
		my @pinfo = software::package_info($pkg);
		if(!@pinfo){
			push(@pkg_missing, $pkg);
		}
	}

	if(@pkg_missing){
		my $url_pkg_list = '';
		foreach my $pkg (@pkg_missing){
			$url_pkg_list .= '&u='.&urlize($pkg);
		}
		my $pkg_list = join(', ', @pkg_missing);

		print "<p>Warning: Missing PG package dependencies - $pkg_list packages are not installed. Install them manually or ".
				"<a href='../package-updates/update.cgi?mode=new&source=3${url_pkg_list}&redir=%2E%2E%2Fpostgis%2Fsetup.cgi&redirdesc=PostGIS'>click here</a> to have them installed.</p>";
	}
}

sub get_dir_files{
	my $dirpath = $_[0];
	my $pattern = $_[1];

	opendir(DIR, $dirpath) or die $!;
	my @files = grep {
				/^$pattern\./         # Starts with pattern
				&& -f "$dirpath/$_"  	# and is a file
	} readdir(DIR);
	closedir(DIR);

	return @files;
}

sub crunchy_load_countries(){

	my $filename = download_file('https://www.naturalearthdata.com/http//www.naturalearthdata.com/download/50m/cultural/ne_50m_admin_0_countries.zip');
	my $zip_dir = unzip_me($filename);

	print "Renaming ne_50m_admin_0_countries to countries ...</br>";
	my @data_files = get_dir_files($zip_dir, 'ne_50m_admin_0_countries');
	foreach my $filename (@data_files){
		(my $name, $ext) = split('\.', $filename);
		&rename_file($zip_dir.'/'.$filename, $zip_dir.'/countries.'.$ext);
		&set_ownership_permissions($crunchy_user, $crunchy_user, 0755, $zip_dir.'/countries.'.$ext);
	}

	print 'Importing ne_50m_admin_0_countries ...';
	exec_cmd('shp2pgsql -I -s 4326 -W "latin1" '.$zip_dir.'/countries.shp countries | sudo -u '.$crunchy_user.' psql -d '.$crunchy_db);
	&unlink_file($zip_dir);
}

sub setup_crunchy_db(){

	#add user if it doesn exist
	if(read_file_contents('/etc/passwd') !~ /\n$crunchy_user:/){
		local $out = &backquote_command('useradd -m '.$crunchy_user, 0);
	}

	#generate password
	my @pw_chars = ("A".."Z", "a".."z", "0".."9", "_", "-");
	$db_pass .= $pw_chars[rand @pw_chars] for 1..32;

	#setup database and user for qwc2
	my $s = &postgresql::execute_sql_safe(undef,    "CREATE USER $crunchy_user WITH PASSWORD '$db_pass' SUPERUSER");
		 $s = &postgresql::execute_sql_safe(undef,    "CREATE DATABASE $crunchy_db WITH OWNER = $crunchy_user ENCODING = 'UTF8'");
	   $s = &postgresql::execute_sql_safe($crunchy_db, "CREATE EXTENSION postgis");
	print "<p>Created database $crunchy_db and user $crunchy_user</p>";

	#save password for qgis
	my $pg_pass = '/home/'.$crunchy_user.'/.pgpass';
	open(my $fh, '>', $pg_pass) or return 1;
		print $fh "$crunchy_db:$crunchy_user:$db_pass\n";
	close $fh;
	&set_ownership_permissions($crunchy_user, $crunchy_user, 0600, $pg_pass);

	#load the data
	crunchy_load_countries();
}

sub update_toml{
	my $app_name = $_[0];
	my $toml_file = '/opt/'.$app_name.'/config/'.$app_name.'.toml';

	#create the config file
	&rename_file($toml_file.'.example', $toml_file);

	#update the PG connection string in .toml file
	my $ln = 0;
	$lref = read_file_lines($toml_file);
	foreach $line (@$lref){
		chomp $line;
		if($line =~ /^# DbConnection/){	#if its a section start
			@{$lref}[$ln] = 'DbConnection = "postgresql://'.$crunchy_user.':'.$db_pass.'@localhost/'.$crunchy_db.'"';
		}elsif($line =~ /^AssetsPath /){
			@{$lref}[$ln] = 'AssetsPath = "/opt/'.$app_name.'/assets"';
		}
		$ln=$ln+1;
	}
	&flush_file_lines($toml_file);
}

sub install_pg_tileserv(){
	my $app_home='/opt/pg_tileserv';

	#download app
	print "Downloading app ...</br>";
	my $filename = &download_file('https://postgisftw.s3.amazonaws.com/pg_tileserv_latest_linux.zip');
	my $unzip_dir = unzip_me($filename);
	&rename_file($unzip_dir, $app_home);

	update_toml('pg_tileserv');

	#create the service file
	print "Adding service file <b>pg_tileserv.service</b>...</br>";

	open(my $fh, '>', '/etc/systemd/system/pg_tileserv.service') or die "open:$!";
	print $fh '[Unit]'."\n";
	print $fh 'Description=PG TileServ'."\n";
	print $fh 'After=multi-user.target'."\n";
	print $fh "\n";
	print $fh '[Service]'."\n";
	print $fh 'User='.$crunchy_user."\n";
	print $fh 'WorkingDirectory='.$app_home."\n";
	print $fh 'Type=simple'."\n";
	print $fh 'Restart=always'."\n";
	print $fh 'ExecStart='.$app_home.'/pg_tileserv'."\n";
	print $fh "\n";
	print $fh '[Install]'."\n";
	print $fh 'WantedBy=multi-user.target'."\n";
	close $fh;

	exec_cmd('chown -R '.$crunchy_user.':'.$crunchy_user.' '.$app_home);

	exec_cmd('systemctl daemon-reload');
  exec_cmd('systemctl enable pg_tileserv');
  exec_cmd('systemctl start pg_tileserv');
}

sub install_pg_featureserv(){
	my $app_home='/opt/pg_featureserv';

	#download app
	print "Downloading app ...</br>";
	my $filename = &download_file('https://postgisftw.s3.amazonaws.com/pg_featureserv_latest_linux.zip');
	my $unzip_dir = unzip_me($filename);
	&rename_file($unzip_dir, $app_home);

	#create the environment file
	my $crunchy_env = '/etc/default/crunchy_env';

	if(! -f $crunchy_env){
		print "Adding env file <b>/etc/default/crunchy_env</b>...</br>";
		open(my $fh, '>', $crunchy_env) or die "open:$!";
		print $fh 'DATABASE_URL=postgresql://'.$crunchy_user.'@localhost:5432/'.$crunchy_db."\n";
		close $fh;
	}

	update_toml('pg_featureserv');

	#create the service file
	print "Adding service file <b>pg_featureserv.service</b>...</br>";

	open(my $fh, '>', '/etc/systemd/system/pg_featureserv.service') or die "open:$!";
	print $fh '[Unit]'."\n";
	print $fh 'Description=PG FeatureServ'."\n";
	print $fh 'After=multi-user.target'."\n";
	print $fh "\n";
	print $fh '[Service]'."\n";
	print $fh 'User='.$crunchy_user."\n";
	print $fh 'WorkingDirectory='.$app_home."\n";
	print $fh 'Type=simple'."\n";
	print $fh 'Restart=always'."\n";
	print $fh 'ExecStart='.$app_home.'/pg_featureserv --config '.$app_home.'/config/pg_featureserv.toml'."\n";
	print $fh "\n";
	print $fh '[Install]'."\n";
	print $fh 'WantedBy=multi-user.target'."\n";
	close $fh;

	exec_cmd('chown -R '.$crunchy_user.':'.$crunchy_user.' '.$app_home);

	exec_cmd('systemctl daemon-reload');
  exec_cmd('systemctl enable pg_tileserv');
  exec_cmd('systemctl start pg_featureserv');
}

sub setup_checks{

	if($osinfo{'real_os_type'} =~ /centos/i){	#CentOS
		my @pinfo = software::package_info('epel-release', undef, );
		if(!@pinfo){
			print "<p>Warning: EPEL repository is not installed. Install it manually or ".
					"<a href='../software/install_pack.cgi?source=3&update=epel-release&return=%2E%2E%2Fpostgis%2Fsetup.cgi&redirdesc=PostGIS&caller=postgis'>click here</a> to have it downloaded and installed.</p>";
		}
	}

	my @mod_pkgs;
	if(	( $osinfo{'real_os_type'} =~ /centos/i) or	#CentOS
			($osinfo{'real_os_type'} =~ /fedora/i)	or  #Fedora
			($osinfo{'real_os_type'} =~ /scientific/i)	){
		@mod_pkgs = ('httpd', 'unzip', 'bzip2', 'tar');
	}elsif( ($osinfo{'real_os_type'} =~ /ubuntu/i) or
					($osinfo{'real_os_type'} =~ /debian/i) 	){	#ubuntu or debian
		@mod_pkgs = ('apache2', 'unzip', 'bzip2', 'tar');
	}

	foreach my $pkg (@mod_pkgs){
		my @pinfo = software::package_info($pkg, undef, );
		if(!@pinfo){
			print "<p>Warning: $pkg not found. Install it manually or ".
				  "<a href='../package-updates/update.cgi?mode=new&source=3&u=$pkg&redir=%2E%2E%2Fpostgis%2Fsetup.cgi&redirdesc=PostGIS'>click here</a> to have it downloaded and installed.</p>";
		}
	}

	#Check if bootstrap web application is installed
	if (! -f "$module_config_directory/bootstraped.txt"){
		print '<p>Option: Bootstrap web app is not installed in /var/www/html. '.
			  "<a href='setup.cgi?mode=install_bootstrap_web_app&return=%2E%2E%2Fpostgis%2Fsetup.cgi&redirdesc=PostGIS&caller=postgis'>Click here</a> install it";
	}

	# Check if OpenLayers exists
	if ((! -f "$module_config_directory/dismiss_openlayers.txt") &&
		(! -d "/var/www/html/OpenLayers") ){
		print "<p>Option: The OpenLayers direcrory <tt>/var/www/html/OpenLayers</tt> does not exist. ".
			  "<a href='setup.cgi?mode=install_openlayers&return=%2E%2E%2Fpostgis%2Fsetup.cgi&redirdesc=PostGIS&caller=postgis'>Click here</a> install it";
	}

	# Check if LeafletJS exists
	if ((! -f "$module_config_directory/dismiss_leafletjs.txt") &&
		(! -d "/var/www/html/leafletjs") ){
		print "<p>Option: The LeafletJS directory <tt>/var/www/html/leafletjs</tt> does not exist. ".
			  "<a href='setup.cgi?mode=install_leafletjs&return=%2E%2E%2Fpostgis%2Fsetup.cgi&redirdesc=PostGIS&caller=postgis'>Click here</a> install it";
	}

	my $pg_ver = get_installed_pg_version();
	if(have_pg_repo() == 0){
		print '<p>Warning: PostgreSQL repository not found. Install it from <a href="./pg_install.cgi">'.$text{'pg_inst_title'}.'</a>';
	}else{
		if (!&has_command('shp2pgsql')) {
			my $shp2pg_pkg = get_shp2pgsql_pkg_name($pg_ver);
			if(-f "$module_root_directory/pg_install.cgi"){
				print '<p>Warning: shp2pgsql command is not found.';
				if(!$pg_ver){
					print 'Install PG repo from <a href="./pg_install.cgi">'.$text{'pg_inst_title'}.'</a> and after that ';
				}
				print "<a href='../package-updates/update.cgi?mode=new&source=3&u=$shp2pg_pkg&redir=%2E%2E%2Fpostgis%2Fsetup.cgi&redirdesc=PostGIS'>Click here</a> to have it installed from postgis package.</p>";
			}else{
				print '<p>Warning: shp2pgsql command is not found. '.
				  "<a href='../package-updates/update.cgi?mode=new&source=3&u=$shp2pg_pkg&redir=%2E%2E%2Fpostgis%2Fsetup.cgi&redirdesc=PostGIS'>Click here</a> to have it installed from postgis package.</p>";
			}
		}

		check_pg_ext_deps($pg_ver);

		my $pg_ver2;
		($pg_ver2 = $pg_ver) =~ s/\.//;

		my %cmd_pkg;

		if(	( $osinfo{'real_os_type'} =~ /centos/i) or	#CentOS
				($osinfo{'real_os_type'} =~ /fedora/i)	or  #Fedora
				($osinfo{'real_os_type'} =~ /scientific/i)	){
			$cmd_pkg{'osm2pgsql'} = 'osm2pgsql';
			$cmd_pkg{'osm2pgrouting'} = 'osm2pgrouting_'.$pg_ver2;

		}elsif( ($osinfo{'real_os_type'} =~ /ubuntu/i) or
						($osinfo{'real_os_type'} =~ /debian/i) 	){	#ubuntu or debian
			$cmd_pkg{'osm2pgsql'} = 'osm2pgsql';
			$cmd_pkg{'osm2pgrouting'} = 'osm2pgrouting';
		}

	 	foreach my $cmd (keys %cmd_pkg){
			if(!has_command($cmd)){
				my $pkg = $cmd_pkg{$cmd};
				print "<p>Warning: $cmd not found. Install it manually or ".
							"<a href='../package-updates/update.cgi?mode=new&source=3&u=$pkg&redir=%2E%2E%2Fpostgis%2Fsetup.cgi&redirdesc=PostGIS'>click here</a> to have it downloaded and installed.</p>";
			}
	 	}

		if(foreign_installed('postgresql', 1) != 2){
			print '<p>Warning: Webmin Postgresql module is not installed! Set it up from <a href="../postgresql/">here</a><p>';
		}
	}

	if(! -f '/home/'.$crunchy_user.'/.pgpass'){
		print "<p>Option: Crunchy PostgreSQL Database doesn't exist. ".
			  "<a href='setup.cgi?mode=setup_crunchy_db&return=%2E%2E%2Fpostgis%2Fsetup.cgi&redirdesc=PostGIS&caller=postgis'>Click here</a> create it";
	}

	if(! -d '/opt/pg_tileserv'){
		print "<p>Option: pg_tileserv not installed. ".
			  "<a href='setup.cgi?mode=install_pg_tileserv&return=%2E%2E%2Fpostgis%2Fsetup.cgi&redirdesc=PostGIS&caller=postgis'>Click here</a> install it";
	}

	if(! -d '/opt/pg_featureserv'){
		print "<p>Option: pg_featureServ not installed. ".
			  "<a href='setup.cgi?mode=install_pg_featureserv&return=%2E%2E%2Fpostgis%2Fsetup.cgi&redirdesc=PostGIS&caller=postgis'>Click here</a> install it";
	}

		print '<p>If you don\'t see any warnings above, you can complete setup by clicking '.
		  "<a href='setup.cgi?mode=cleanup&return=%2E%2E%2Facugis_es%2F&returndesc=AcuGIS%20ES&caller=acugis_es'>here</a></p>";
}

#Remove all setup files
sub setup_cleanup{
	my $file = $module_root_directory.'/setup.cgi';
	print "Completing Set Up....</br>";
	&unlink_file($file);
	print &js_redirect("index.cgi");
}


&ui_print_header(undef, $text{'setup_title'}, "");

if($ENV{'CONTENT_TYPE'} =~ /boundary=(.*)$/) {
	&ReadParseMime();
}else {
	&ReadParse(); $no_upload = 1;
}

my $mode = $in{'mode'} || "checks";
%osinfo = &detect_operating_system();

if(	( $osinfo{'real_os_type'} =~ /centos/i) or	#CentOS
		($osinfo{'real_os_type'} =~ /fedora/i)	or  #Fedora
		($osinfo{'real_os_type'} =~ /scientific/i)	){
	$www_user = 'apache';
}

if($mode eq "checks"){							setup_checks();
	&ui_print_footer('', $text{'index_return'});
	exit 0;
}elsif($mode eq "cleanup"){						setup_cleanup();
	&ui_print_footer('', $text{'index_return'});
	exit 0;
}elsif($mode eq "install_bootstrap_web_app"){	install_bootstrap_web_app();
}elsif($mode eq "install_openlayers"){				install_openlayers();
}elsif($mode eq "install_leafletjs"){					install_leafletjs();
}elsif($mode eq "setup_crunchy_db"){					setup_crunchy_db();
}elsif($mode eq "install_pg_tileserv"){				install_pg_tileserv();
}elsif($mode eq "install_pg_featureserv"){		install_pg_featureserv();
}else{
	print "Error: Invalid setup mode\n";
}

&ui_print_footer('setup.cgi', $text{'setup_title'});
