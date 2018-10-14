#!/usr/bin/perl

require './geohelm-lib.pl';
require './tomcat-lib.pl';
require './pg-lib.pl';
require './geoserver-lib.pl';
require '../webmin/webmin-lib.pl';	#for OS detection

sub latest_tomcat_version(){
	#get latest version from Apache Tomcat webpage
	my %version;
	if(-f "$module_config_directory/version"){
		read_file_cached("$module_config_directory/version", \%version);

		if(	$version{'updated'} >= (time() - 86400)){	#if last update was less than a day ago
			return $version{'latest'} if ($version{'latest'} ne '0.0.0');
		}
	}

	my $url = 'http://archive.apache.org/dist/tomcat/tomcat-8/';
	&error_setup(&text('install_err3', $url));
	my $error = '';
	my $tmpfile = &transname('tomcat.html');


	&http_download('archive.apache.org', 80, '/dist/tomcat/tomcat-8/', $tmpfile, \$error);
	if($error){
		print &html_escape($error);
		die "Error: Failed to get Apache Tomcat webpage";
	}

	my @latest_versions;
	open(my $fh, '<', $tmpfile) or die "open:$!";
	while(my $line = <$fh>){
		if($line =~ /<a\s+href="v(8\.[0-9\.]+)\/">v[0-9\.]+\/<\/a>/){
			push(@latest_versions, $1);
		}
	}
	close $fh;

	my @result = sort sort_version @latest_versions;
	my $latest_ver = $result[$#result];

	#renew the updated timestamp and latest version
	$version{'updated'} = time();
	$version{'latest'} = $latest_ver;
	&write_file("$module_config_directory/version", \%version);

	return $latest_ver;
}

sub add_tomcat_user{
	#check if tomcat user exists
	if(read_file_contents('/etc/passwd') !~ /\ntomcat:/){
		#add tomcat user
		local $out = &backquote_command('useradd -m tomcat', 0);
	}elsif(! -d '/home/tomcat'){
		&make_dir("/home/tomcat", 0755, 1);
		&set_ownership_permissions('tomcat','tomcat', undef, '/home/tomcat');
	}
}

sub download_and_install{
	my $tomcat_ver = $_[0];

	#download tomcat archive
	my $url = "http://archive.apache.org/dist/tomcat/tomcat-8/v$tomcat_ver/bin/apache-tomcat-$tomcat_ver.tar.gz";
	$progress_callback_url = $url;
	&error_setup(&text('install_err3', $url));
	my $tmpfile = &transname("apache-tomcat-$tomcat_ver.tar.gz");
	&http_download('archive.apache.org', 80, "/dist/tomcat/tomcat-8/v$tomcat_ver/bin/apache-tomcat-$tomcat_ver.tar.gz",
					$tmpfile, \$error, \&progress_callback, 0);

	if($error){
		print &html_escape($error);
		return 1;
	}

	#extract tomcat archive
	my $cmd_out='';
	my $cmd_err='';
	print "<hr>Extracting to /home/tomcat/apache-tomcat-$tomcat_ver/ ...<br>";
	local $out = &execute_command("tar -x --overwrite -f \"$tmpfile\" -C/home/tomcat/", undef, \$cmd_out, \$cmd_err, 0, 0);

	if($cmd_err ne ""){
		&error("Error: tar: $cmd_err");
	}else{
		$cmd_out = s/\r\n/<br>/g;
		print &html_escape($cmd_out);
		print "Done<br>";
	}

	#folder is created after tomcat is started, but we need it now
	&make_dir("/home/tomcat/apache-tomcat-$tomcat_ver/conf/Catalina/localhost/", 0755, 1);

	open(my $fh, '>', "/home/tomcat/apache-tomcat-$tomcat_ver/conf/Catalina/localhost/manager.xml") or die "open:$!";
	print $fh <<EOF;
<Context privileged="true" antiResourceLocking="false" docBase="\${catalina.home}/webapps/manager">
	<Valve className="org.apache.catalina.valves.RemoteAddrValve" allow="^.*\$" />
</Context>
EOF
	close $fh;

	
	&execute_command("chown -R tomcat:tomcat /home/tomcat/apache-tomcat-$tomcat_ver");
}

sub setup_catalina_env{
	my $tomcat_ver = $_[0];

	my %os_env;

	print "<hr>Setting CATALINA environment...";

	read_env_file('/etc/environment', \%os_env);
	$os_env{'CATALINA_HOME'} = "/home/tomcat/apache-tomcat-$tomcat_ver/";
	$os_env{'CATALINA_BASE'} = "/home/tomcat/apache-tomcat-$tomcat_ver/";
	write_env_file('/etc/environment', \%os_env, 0);
}

sub setup_tomcat_users{
	my $tomcat_ver = $_[0];
	my @pw_chars = ("A".."Z", "a".."z", "0".."9", "_", "-");
	my $manager_pass;
	my $admin_pass;

	$manager_pass .= $pw_chars[rand @pw_chars] for 1..32;
	$admin_pass   .= $pw_chars[rand @pw_chars] for 1..32;

	#Save tomcat-users.xml
	open(my $fh, '>', "/home/tomcat/apache-tomcat-$tomcat_ver/conf/tomcat-users.xml") or die "open:$!";
	print $fh <<EOF;
<?xml version='1.0' encoding='utf-8'?>
<tomcat-users>
<role rolename="manager-gui" />
<user username="manager" password="$manager_pass" roles="manager-gui" />

<role rolename="admin-gui" />
<user username="admin" password="$admin_pass" roles="manager-gui,admin-gui" />
</tomcat-users>
EOF
	close $fh;
	print "<hr>Setting Tomcat users...";
}

sub setup_tomcat_service{
	my $tomcat_ver = $_[0];
	copy_source_dest("$module_root_directory/tomcat.service", '/etc/init.d/tomcat');
	&set_ownership_permissions('root','root', 0555, "/etc/init.d/tomcat");
	print "<hr>Setting Tomcat service ...";
}

sub install_tomcat_from_archive{
	my $tomcat_ver = latest_tomcat_version();

	add_tomcat_user();
	download_and_install($tomcat_ver);

	setup_catalina_env($tomcat_ver);
	setup_tomcat_users($tomcat_ver);
	setup_tomcat_service($tomcat_ver);
}

sub migrate_settings_and_apps{
	my $old_ver = $_[0];
	my $new_ver = $_[1];
	my $apps_ref = $_[2];

	#copy settings
	my @files = ('bin/setenv.sh', 'conf/tomcat-users.xml');
	foreach my $file (@files){
		if( -f "/home/tomcat/apache-tomcat-$old_ver/$file"){
			copy_source_dest("/home/tomcat/apache-tomcat-$old_ver/$file",
							 "/home/tomcat/apache-tomcat-$new_ver/$file");
			print "Copying $file to /home/tomcat/apache-tomcat-$new_ver/$file<br>";
		}
	}

	#make a list of installed apps
	my @exclude_apps = ('docs', 'examples', 'host-manager', 'manager', 'ROOT');
	#move apps
	print "Moving apps ...<br>";
	foreach my $app (@$apps_ref){
		next if (grep $_ == $app, @exclude_apps);

		#move
		if(!move(	"/home/tomcat/apache-tomcat-$old_ver/webapps/$app",
					"/home/tomcat/apache-tomcat-$old_ver/webapps/$app")){
			&error("Error: Can't move $app: $!");
		}else{
			print "$app moved<br>";
		}
	}
}

sub install_geoexplorer{
	#setup geoexplorer in apache configure file
	my $gs_proxy_file = '';
	my %osinfo = &detect_operating_system();
	if( $osinfo{'real_os_type'} =~ /centos/i){	#CentOS
		$gs_proxy_file = '/etc/httpd/conf.d/geoserver_proxy.conf';

	}elsif( $osinfo{'os_type'} =~ /debian/i){	#ubuntu
		$gs_proxy_file = '/etc/apache2/conf-enabled/geoserver_proxy.conf';
	}

	if(-f $gs_proxy_file){
		open(my $fh, '>>', $gs_proxy_file) or die "open:$!";

		print $fh "ProxyPass /geoexplorer http://localhost:8080/geoexplorer\n";
		print $fh "ProxyPassReverse /geoexplorer http://localhost:8080/geoexplorer\n";

		close $fh;
	}

	#call tomcat install war page
	my $url = &urlize("http://cdn.acugis.com/geohelm/external/geoexplorer.war");
	print "Goexplorer is configured. <a href='./install_war.cgi?source=2&url=$url&return=%2E%2E%2Fgeohelm%2F&returndesc=Geohelm&caller=geohelm'>Click here</a> to install the WAR file";
}

sub latest_leafletjs_version(){
	my $tmpfile = transname('download.html');
	&error_setup(&text('install_err3', "http://leafletjs.com/download.html"));
	&http_download('leafletjs.com', 80, '/download.html', $tmpfile, \$error);

	if($error){
		print &html_escape($error);
		die "Error: Failed to get latest version of LeafletJS";
	}

	my $latest_ver = '0.0.0';
	open(my $fh, '<', $tmpfile) or die "open:$!";
	while(my $line = <$fh>){
		if($line =~ /cdn\.leafletjs\.com\/leaflet\/v([0-9\.]+)\/leaflet\.zip/){
			$latest_ver = $1;
			last;
		}
	}
	close $fh;

	return $latest_ver;
}

sub install_leafletjs{
	if( -d '/var/www/html/leafletjs'){
		return 0;
	}

	my $ll_ver = latest_leafletjs_version();

	my $tmpfile = transname("leaflet.zip");
	my $url = "http://cdn.leafletjs.com/leaflet/v${ll_ver}/leaflet.zip";
	$progress_callback_url = $url;

	&error_setup(&text('install_err3', $url));
	&http_download('cdn.leafletjs.com', 80, "/leaflet/v${ll_ver}/leaflet.zip", $tmpfile, \$error, \&progress_callback);

	if($error){
		print &html_escape($error);
		die "Error: Failed to get latest LeafletJS archive";
	}

	my $ll_dir = unzip_me($tmpfile);

	print "Moving to /var/www/html/leafletjs ...";
	rename_file($ll_dir, '/var/www/html/leafletjs');
	&execute_command("chown -R root:root '/var/www/html/leafletjs'");
}

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

sub install_bootstrap_web_app(){

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

sub setup_apache_for_geoserver(){
	my $gs_proxy_file = '';
	my %osinfo = &detect_operating_system();
	if(	( $osinfo{'real_os_type'} =~ /centos/i) or	#CentOS
		($osinfo{'real_os_type'} =~ /fedora/i)	or  #Fedora
		($osinfo{'real_os_type'} =~ /scientific/i)	){
		if( ! -d '/etc/httpd/'){
			return 0;
		}
		$gs_proxy_file = '/etc/httpd/conf.d/geoserver_proxy.conf';

	}elsif( ($osinfo{'real_os_type'} =~ /ubuntu/i) or
			($osinfo{'real_os_type'} =~ /debian/i) 	){	#ubuntu or debian
		if( ! -d '/etc/apache2/'){
			return 0;
		}
		$gs_proxy_file = '/etc/apache2/conf-enabled/geoserver_proxy.conf';
	}

	if(! -f $gs_proxy_file){
		open(my $fh, '>', $gs_proxy_file) or die "open:$!";

		if(	($osinfo{'real_os_type'} =~ /centos/i) or	#CentOS
			($osinfo{'real_os_type'} =~ /fedora/i)	){	#Fedora

			&execute_command('setsebool httpd_can_network_connect 1');

			print $fh "LoadModule proxy_module 		modules/mod_proxy.so\n";
			print $fh "LoadModule proxy_http_module modules/mod_proxy_http.so\n";
			print $fh "LoadModule rewrite_module  	modules/mod_rewrite.so\n";

		}elsif( $osinfo{'os_type'} =~ /debian/i){	#ubuntu or debian

			print $fh "LoadModule proxy_module /usr/lib/apache2/modules/mod_proxy.so\n";
			print $fh "LoadModule proxy_http_module  /usr/lib/apache2/modules/mod_proxy_http.so\n";
			print $fh "LoadModule rewrite_module  /usr/lib/apache2/modules/mod_rewrite.so\n";
		}

		print $fh "ProxyRequests Off\n";
		print $fh "ProxyPreserveHost On\n";
		print $fh "    <Proxy *>\n";
		print $fh "       Order allow,deny\n";
		print $fh "       Allow from all\n";
		print $fh "    </Proxy>\n";
		print $fh "ProxyPass /geoserver http://localhost:8080/geoserver\n";
		print $fh "ProxyPassReverse /geoserver http://localhost:8080/geoserver\n";

		close $fh;
	}
	print "Wrote configuration to $gs_proxy_file\n";
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
				  "<a href='../software/install_pack.cgi?source=3&update=$pkg&return=%2E%2E%2Fgeohelm%2Fsetup.cgi&returndesc=Setup&caller=geohelm'>click here</a> to have it downloaded and installed.</p>";
		}
	}
}


sub setup_checks{
	#Check for commands
	if (!&has_command('java')) {
		print '<p>Warning: Java is not found. Install it manually or from the '.
			  "<a href='./edit_java.cgi?return=%2E%2E%2Fgeohelm%2Fsetup.cgi&returndesc=Setup&caller=geohelm'>Java tab</a></p>";
	}

	my $tomcat_ver = installed_tomcat_version();
	if(!$tomcat_ver){
		my $latest_ver = latest_tomcat_version();
		print "<p><a href='setup.cgi?mode=tomcat_install&return=%2E%2E%2Fgeohelm%2Fsetup.cgi&returndesc=Setup&caller=geohelm'>Click here</a> to install Tomcat $latest_ver from Apache site.</p>";
	}

	if (!&has_command('unzip')) {
		print '<p>Warning: unzip command is not found. Install it manually or '.
			  "<a href='../software/install_pack.cgi?source=3&update=unzip&return=%2E%2E%2Fgeohelm%2Fsetup.cgi&returndesc=Setup&caller=geohelm'>click here</a> to have it downloaded and installed.</p>";
	}

	foreign_require('software', 'software-lib.pl');
	my @pinfo = software::package_info('haveged', undef, );
	if(!@pinfo){
		print "<p>Warning: haveged package is not installed. Install it manually or ".
			  "<a href='../software/install_pack.cgi?source=3&update=haveged&return=%2E%2E%2Fgeohelm%2Fsetup.cgi&returndesc=Setup&caller=geohelm'>click here</a> to have it downloaded and installed.</p>";
	}

	#Check if bootstrap web application is installed
	if (! -f "$module_config_directory/bootstraped.txt"){
		print '<p>Warning: Bootstrap web app is not installed in /var/www/html. '.
			  "<a href='setup.cgi?mode=install_bootstrap_web_app&return=%2E%2E%2Fgeohelm%2Fsetup.cgi&returndesc=Setup&caller=geohelm'>Click here</a> install it";
	}

	# Check if OpenLayers exists
	if ((! -f "$module_config_directory/dismiss_openlayers.txt") &&
		(! -d "/var/www/html/OpenLayers") ){
		print "<p>The OpenLayers direcrory <tt>/var/www/html/OpenLayers</tt> does not exist. ".
			  "<a href='setup.cgi?mode=install_openlayers&return=%2E%2E%2Fgeohelm%2Fsetup.cgi&returndesc=Setup&caller=geohelm'>Click here</a> install it";
	}

	# Check if LeafletJS exists
	if ((! -f "$module_config_directory/dismiss_leafletjs.txt") &&
		(! -d "/var/www/html/leafletjs") ){
		print "<p>The LeafletJS direcrory <tt>/var/www/html/leafletjs</tt> does not exist. ".
			  "<a href='setup.cgi?mode=install_leafletjs&return=%2E%2E%2Fgeohelm%2Fsetup.cgi&returndesc=Setup&caller=geohelm'>Click here</a> install it";
	}

	# Check if GeoExplorer webapp exists
	if($tomcat_ver){
		my $catalina_home = get_catalina_home();
		if ((! -f "$module_config_directory/dismiss_geoexplorer.txt") &&
			(! -d "$catalina_home/webapps/geoexplorer/") 				){
			if( -f "$catalina_home/webapps/geoexplorer.war"){
				print "<p>The GeoExplorer webapp is not deployed yet!";
			}else{
				print "<p>The GeoExplorer webapp direcrory <tt>$catalina_home/webapps/geoexplorer/</tt> does not exist. ".
					  "<a href='./setup.cgi?mode=install_geoexplorer&return=%2E%2E%2Fgeohelm%2Fsetup.cgi&returndesc=Setup&caller=geohelm'>Click here</a> to have it downloaded and installed";
			}
		}

		# Check if geoserver webapp exists
		if (! -d "$catalina_home/webapps/geoserver/") {
			my $geo_ver = get_latest_geoserver_ver();
			my $url = &urlize("http://sourceforge.net/projects/geoserver/files/GeoServer/$geo_ver/geoserver-$geo_ver-war.zip");
			print "<p>The Geoserver webapp direcrory <tt>$catalina_home/webapps/geoserver/</tt> does not exist. ".
				  "<a href='./install_war.cgi?source=2&url=$url&return=%2E%2E%2Fgeohelm%2Fsetup.cgi&returndesc=Setup&caller=geohelm'>Click here</a> to have it downloaded and installed.</p>";
		}
	}

	#check for GeoServer Apache config
	my %osinfo = &detect_operating_system();
	if(	( $osinfo{'real_os_type'} =~ /centos/i) or	#CentOS
		($osinfo{'real_os_type'} =~ /fedora/i)	or  #Fedora
		($osinfo{'real_os_type'} =~ /scientific/i)	){
		$gs_proxy_file = '/etc/httpd/conf.d/geoserver_proxy.conf';

	}elsif( ($osinfo{'real_os_type'} =~ /ubuntu/i) or
			($osinfo{'real_os_type'} =~ /debian/i) 	){	#ubuntu or debian
		$gs_proxy_file = '/etc/apache2/conf-enabled/geoserver_proxy.conf';
	}
	if(! -f $gs_proxy_file){
		print "<p>The GeoServer Apache config <tt> $gs_proxy_file</tt> does not exist. ".
			  "<a href='./setup.cgi?mode=setup_geoserver_apache&return=%2E%2E%2Fgeohelm%2Fsetup.cgi&returndesc=Setup&caller=geohelm'>Click here</a> to create it.";
	}

	my $pg_ver;
	if(have_pg_repo() == 0){
		print '<p>Warning: PostgreSQL repository is not found. Install it from <a href="./pg_install.cgi">'.$text{'pg_inst_title'}.'</a>';
	}else{
		$pg_ver = get_installed_pg_version();
		if(!$pg_ver){
			print '<p>Warning: PostgreSQL is not installed. Install it from <a href="./pg_install.cgi">'.$text{'pg_inst_title'}.'</a>';
		}
	}

	if (!&has_command('shp2pgsql')) {
		if(-f "$module_root_directory/pg_install.cgi"){
			if($pg_ver){
				print '<p>Warning: shp2pgsql command is not found.'.
					"<a href='../software/install_pack.cgi?source=3&update=$config{'shp2pgsql_pkg'}&return=%2E%2E%2Fgeohelm%2Fsetup.cgi&returndesc=Setup&caller=geohelm'>Click here</a> to have it installed from postgis package.</p>";
			}
		}else{
			print '<p>Warning: shp2pgsql command is not found. '.
			  "<a href='../software/install_pack.cgi?source=3&update=$config{'shp2pgsql_pkg'}&return=%2E%2E%2Fgeohelm%2Fsetup.cgi&returndesc=Setup&caller=geohelm'>Click here</a> to have it installed from postgis package.</p>";
		}
	}

	check_pg_ext_deps($pg_ver) if($pg_ver);

	if(foreign_installed('postgresql', 1) != 2){
		print '<p>Warning: Webmin Postgresql module is not installed! Set it up from <a href="../postgresql/">here</a><p>';
	}

	print '<p>If you don\'t see any warnings above, you can complete setup by clicking '.
		  "<a href='setup.cgi?mode=cleanup&return=%2E%2E%2Fgeohelm%2F&returndesc=Geohelm&caller=geohelm'>here</a></p>";
}

#Remove all setup files
sub setup_cleanup{
	my $file = $module_root_directory.'/setup.cgi';
	print "Completing Set Up....";
	&unlink_file($file);
}


&ui_print_header(undef, $text{'setup_title'}, "");

&ReadParse();

my $mode = $in{'mode'} || "checks";

if($mode eq "checks"){							setup_checks();
	&ui_print_footer('', $text{'index_return'});
	exit 0;
}elsif($mode eq "cleanup"){						setup_cleanup();
	&ui_print_footer('', $text{'index_return'});
	exit 0;
}elsif($mode eq "tomcat_install"){				install_tomcat_from_archive();
}elsif($mode eq "install_bootstrap_web_app"){	install_bootstrap_web_app();
}elsif($mode eq "install_openlayers"){			install_openlayers();
}elsif($mode eq "install_leafletjs"){			install_leafletjs();
}elsif($mode eq "install_geoexplorer"){			install_geoexplorer();
}elsif($mode eq "setup_geoserver_apache"){		setup_apache_for_geoserver();
}else{
	print "Error: Invalid setup mode\n";
}

&ui_print_footer('setup.cgi', $text{'setup_title'});
