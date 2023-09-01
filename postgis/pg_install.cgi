#!/usr/bin/perl

require './geohelm-lib.pl';
require './pg-lib.pl';
require '../webmin/webmin-lib.pl';	#for OS detection
foreign_require('software', 'software-lib.pl');
foreign_require('postgresql', 'postgresql-lib.pl');


sub get_versions(){
	my @versions;
	my %pg_ver_cache;
	my $cache_file = "$module_config_directory/pg_version_cache";
  if(-f $cache_file){
  	read_file_cached($cache_file, \%pg_ver_cache);
		if($pg_ver_cache{'updated'} >= (time() - 86400)){        #if last update was less than a day ago
			@versions = split(',', $pg_ver_cache{'versions'});
      return @versions;
    }
  }

	#download versioning page
	my $tmpfile = download_file('https://www.postgresql.org/support/versioning/');
	if(!$tmpfile){
		return @versions;
	}

	#<td class="colFirst">9.5</td>
	my $version = '';
	$start_matching = 0;

	open(my $fh, '<', $tmpfile) or die "open:$!";
	while(my $line = <$fh>){
		if($start_matching == 1){
			if(	$line =~ /<td>([0-9\.]+)<\/td>/){	#Version column
				if($version == ''){	#match only first column
					$version = $1;
				}
			}elsif($line =~ /<td>Yes<\/td>/){
				push(@versions, $version);
				$version=''
			}elsif($line =~ /<td>No<\/td>/){
				last;
			}
		}
		if($line =~ /<th>Version<\/th>/){
			$start_matching = 1;
		}
	}
	close $fh;

	$pg_ver_cache{'updated'} = time();
	$pg_ver_cache{'versions'} = join(',', @versions);
  &write_file($cache_file, \%pg_ver_cache);

	return @versions;
}

sub add_pg_repo_apt{
	#get ubuntu release
	my $release = '';
	if(-f '/etc/lsb-release'){
		read_env_file('/etc/lsb-release', \%info);
		$release = $info{'DISTRIB_CODENAME'};
	}elsif(-f '/etc/os-release'){
		read_env_file('/etc/os-release', \%info);
		$release = $1 if $info{'VERSION'} =~ /[0-9]+\s\(([a-z]+)\)/i;
	}

	#add repo to APT
	open(my $fh, '>', '/etc/apt/sources.list.d/pgdg.list') or die "open:$!";
	print $fh "deb http://apt.postgresql.org/pub/repos/apt/ $release-pgdg main";
	close $fh;

	#download repo key
	my $tmpfile = download_file('https://www.postgresql.org/media/keys/ACCC4CF8.asc');
	if(!$tmpfile){
		return 1;
	}

	#add repo key
	$SIG{'TERM'} = 'ignore';
	print '<pre>';
	my %cmds = ('Adding PG apt key'=> "apt-key add $tmpfile",
				'Updating apt' => 'apt-get -y update');
	foreach my $label (sort keys %cmds){
		print "<hr>$label...<br>";
		if(exec_cmd($cmds{$label}) != 0){
			last;
		}
	}
	print '</pre>';

	return 0;
}

sub enable_repo_yum{
	my $repo_file = $_[0];
	my $section = $_[1];

	my $ln = 0;
	my $found = 0;
	$lref = read_file_lines($repo_file);
	foreach $line (@$lref){
		chomp $line;
		if($line =~ /\[$section\]/){	#if its a section start
			$found = 1;
		}elsif($line =~ /^enabled=/){
			if($found){	#if its not our section
				@{$lref}[$ln] = "enabled=1";	#enable it
				last;
			}
		}
		$ln=$ln+1;
	}
	&flush_file_lines($repo_file);
}

sub disable_pg_repo_yum{
	my $repo_file = $_[0];
	my $pg_ver = $_[1];
	my $keep_repo = "[pgdg$pg_ver]";

	#gather all pg repo versions
	my $section = '';
	my $ln = 0;
	$lref = read_file_lines($repo_file);
	foreach $line (@$lref){
		chomp $line;
		if($line =~ /^\[pgdg/){	#if its a section start
			$section = $line;
		}elsif($line =~ /^enabled=/){
			if($section =~ /^\[pgdg[0-9]+/ and $section ne $keep_repo){	#if its not our section
				@{$lref}[$ln] = "enabled=0";	#disable it
			}
		}
		$ln=$ln+1;
	}
	&flush_file_lines($repo_file);
}

sub disable_base_repo_yum{
	my $repo_file = $_[0];

	$lref = read_file_lines($repo_file, 1);
	foreach $line (@$lref){
		if($line eq "exclude=postgresql*"){
			return 0;
		}
	}

	my $ln=0;
	foreach $line (@$lref){
		if($line eq "[base]"){
			replace_file_line($repo_file, $ln, "[base]\n", "exclude=postgresql*\n");
			$ln=$ln+1;
		}elsif($line eq "[updates]"){
			replace_file_line($repo_file, $ln, "[updates]\n", "exclude=postgresql*\n");
			$ln=$ln+1;
			last;
		}elsif($line eq "[fedora]"){
			replace_file_line($repo_file, $ln, "[fedora]\n", "exclude=postgresql*\n");
			$ln=$ln+1;
			last;
		}
		$ln=$ln+1;
	}
}

sub add_pg_repo_yum{
	my $pg_ver = $_[0];
	my $distro = lc $_[1];
	my $distro_ver = $_[2];
	my $pg_ver2;
	($pg_ver2 = $pg_ver) =~ s/\.//;

	if(	$distro eq 'centos'){
		disable_base_repo_yum('/etc/yum.repos.d/CentOS-Base.repo');
	}elsif($distro eq 'fedora'){
		disable_base_repo_yum('/etc/yum.repos.d/fedora.repo');
		disable_base_repo_yum('/etc/yum.repos.d/fedora-updates.repo');
	}

	#download versioning page
	my $tmpfile = download_file('https://yum.postgresql.org/repopackages.php');
	if(!$tmpfile){
		return 1;
	}

	my $label = '';
	if(	$distro eq 'centos'){
		$label = "CentOS $distro_ver";

	}elsif( $distro eq 'scientific'){
		$label = "Scientific Linux $distro_ver";

	}elsif($distro eq 'fedora'){
		$label = "Fedora $distro_ver";
	}

	my $rpm_filename="";
	open(my $fh, '<', $tmpfile) or die "open:$!";
	while(my $line = <$fh>){
		if($line =~ /<a\shref="(.*\.rpm)">$label\s-\sx86_64\s*<\/a><\/li>/i){
			$rpm_filename = $1;
			last;
		}
	}
	close $fh;

	if(($disto == 'centos') && ($distro_ver == '8')){
		my $tmpfile = download_file($rpm_filename);
		if(!$tmpfile){
			return 1;
		}
		#exec_cmd('dnf config-manager --add-repo '.$rpm_filename);	#doesn't work on CentOS 8
		exec_cmd('rpm -i '.$tmpfile);
		exec_cmd('dnf module disable -y postgresql');
		enable_repo_yum('/etc/yum.repos.d/CentOS-PowerTools.repo', 'PowerTools');
	}else{
		software::update_system_install("$rpm_filename", undef);
	}

	disable_pg_repo_yum('/etc/yum.repos.d/pgdg-redhat-all.repo', $pg_ver);

	return 0;
}

sub get_version_packages_yum{
	my $pg_ver = $_[0];
	my $pg_ver2;
	($pg_ver2 = $pg_ver) =~ s/\.//;

	my $cmd_out='';
	my $cmd_err='';
	my $out;
	if(has_command('dnf')){
		$out = &execute_command("dnf search postgresql", undef, \$cmd_out, \$cmd_err, 0, 0);
	}else{
		$out = &execute_command("yum --disablerepo=* --enablerepo=epel --enablerepo=pgdg$pg_ver2 search postgresql", undef, \$cmd_out, \$cmd_err, 0, 0);
	}

	if($out ne 0){
		&error("Error: yum: $cmd_err");
		return 1;
	}

	my %pkgs;
	my @lines = split /\n/, $cmd_out;
	foreach my $line (@lines){
		if($line =~ /^([a-z0-9\._-]+)\.(noarch|x86_64)+ : (.*)/i){
			$pkgs{$1} = $3;
		}
	}
	return %pkgs;
}

sub get_packages_installed_yum{
	my $pg_ver = $_[0];
	my $href = $_[1];

	my $pkg_list = "";
	foreach my $pkg (keys %$href){
		$pkg_list .= " $pkg";
	}

	my $cmd_out='';
	my $cmd_err='';
	my $out = &execute_command("rpm -q --queryformat \"%{NAME}\n\" $pkg_list", undef, \$cmd_out, \$cmd_err, 0, 0);

	my %pkgs;
	my @lines = split /\n/, $cmd_out;
	foreach my $line (@lines){
		if($line =~ /^package\s+([a-z0-9_\.-]+)\s/i){	#package pgdg96-centos is not installed
			$pkgs{$1} = 0;
		}else{
			$pkgs{$line} = 1;
		}
	}
	return %pkgs;
};

sub get_version_packages_apt{
	my $pg_ver = $_[0];

	my $cmd_out='';
	my $cmd_err='';
	my $out = &execute_command("apt-cache search '^postgresql'", undef, \$cmd_out, \$cmd_err, 0, 0);

	if($out ne 0){
		&error("Error: apt-cache: $cmd_err");
		return 1;
	}

	my %pkgs;
	my @lines = split /\n/, $cmd_out;
	#print "Packages for $pg_ver<br>";
	foreach my $line (@lines){
		if($line =~ /^(postgresql-[a-z0-9\._-]*$pg_ver[a-z0-9\._-]*) - (.*)/i){
			#print "$1<br>";
			$pkgs{$1} = $2;
		}
	}
	return %pkgs;
}

sub get_packages_installed_apt{
	my $pg_ver = $_[0];
	my $href = $_[1];	#package names

	my $cmd_out='';
	my $cmd_err='';
	my $out = &execute_command("dpkg -l postgresql*", undef, \$cmd_out, \$cmd_err, 0, 0);

	if($out ne 0){
		&error("Error: dpkg: $cmd_err");
		return 1;
	}

	my %pkgs;

	#set all packages to not installed, since dpkg won't list them
	foreach my $name (keys %$href){
		$pkgs{$name} = 0;
	}

	my @lines = split /\n/, $cmd_out;
	foreach my $line (@lines){
		if($line =~ /^(..)\s+(postgresql-[a-z0-9\._-]*$pg_ver[a-z0-9\._-]*)/i){
			my $pkg = $2;
			if($1 =~ /[uirph]i/){
				$pkgs{$pkg} = 1;
			}
		}
	}
	return %pkgs;
};

sub update_packages{
	my $pkgs_install = $_[0];
	my $pkgs_remove  = $_[1];	#\@lref

	if($pkgs_install ne ""){
		$pkgs_install =~ s/\s+$//;
		software::update_system_install($pkgs_install, undef);
	}

	if(@$pkgs_remove){
		print "<br><p>Removing packages</p>";
		my %opts = ('depstoo'=>1);
		my $error = "";
		if (defined(&delete_packages)) {
			$error = software::delete_packages($pkgs_remove, \%opts, undef);
		}else{
			foreach my $pkg (@$pkgs_remove){
				$error .= software::delete_package($pkg, \%opts, undef)
			}
		}

		if($error ne ""){
			&error($error);
		}else{
			foreach my $pkg (@$pkgs_remove){
				print "<tt>Deleted $pkg</tt><br>"
			}
		}

	}
}

sub pg_initdb{
	my $cmd = $_[0];
	my $data = $_[1];

	#add repo key
	$SIG{'TERM'} = 'ignore';
	print '<pre>';

	print "<hr>Initializing database ...<br>";
	exec_cmd("su - postgres -c '$cmd -D $data'");
	print '</pre>';
}

sub pg_listen_all{
	my $pgconf_dir = $_[0];

	$lref = read_file_lines($pgconf_dir."/postgresql.conf", 1);
	my $ln=-1;
	foreach $line (@$lref){
		$ln = $ln + 1;
		if($line =~ /^#?listen_addresses[\s=]+'(.*)'/){	#if ssl is enabled
			if($1 eq '*'){
				$ln = -1;
			}else{
				print "Setting PG to listen on all interfaces<br>";
				replace_file_line($pgconf_dir."/postgresql.conf", $ln, "listen_addresses = '*'\n");
			}
			last;
		}
	}
}

sub pg_enable_ssl{
	my $pg_ver = $_[0];
	my $pgconf_dir = $_[1];
	my @pw_chars = ("A".."Z", "a".."z", "0".."9", "_", "-");
	my $ssl_pass;

	$lref = read_file_lines($pgconf_dir."/postgresql.conf", 1);
	my $ln=-1;
	foreach $line (@$lref){
		$ln = $ln + 1;
		if($line =~ /^#?ssl[\s=]+(on|off)/){	#if ssl is enabled
			if($1 eq 'on'){
				$ln = -1;
			}else{
				print "Enabling SSL in $pgconf_dir/postgresql.conf<br>";
				replace_file_line($pgconf_dir."/postgresql.conf", $ln, "ssl = on\n");
			}
			last;
		}
	}

	if( ! -f $pgconf_dir.'/server.key' ||
		! -f $pgconf_dir.'/server.crt'){

		$ssl_pass .= $pw_chars[rand @pw_chars] for 1..32;

		my $keyfile = &transname('server.key');
		my $crtfile = &transname('server.crt');

		#add repo key
		$SIG{'TERM'} = 'ignore';
		my @cmds =	("openssl genrsa -des3 -passout pass:$ssl_pass -out $keyfile 2048",
					 "openssl rsa -in $keyfile -passin pass:$ssl_pass -out $keyfile",
					 "chmod 400 $keyfile",
					 "openssl req -new -key $keyfile -days 3650 -out $crtfile -passin pass:$ssl_pass -x509 -subj '/C=CA/ST=Frankfurt/L=Frankfurt/O=acuciva-de.com/CN=acuciva-de.com/emailAddress=info\@acugis.com'",
					 "chown postgres.postgres $keyfile $crtfile",
					 "mv $keyfile $crtfile $pgconf_dir"
					);
		print 'Generating SSL key/cert<br><pre>';
		foreach my $cmd (@cmds){
			print "<hr>$cmd...<br>";
			if(exec_cmd($cmd) != 0){
				last;
			}
		}
		print '</pre>';
	}

	return 0;
};

sub setup_pg_hba{
	my $pgconf_dir = $_[0];

	open(my $fh, '>', $pgconf_dir.'/pg_hba.conf') or die "open:$!";
	print $fh <<EOF;
local	all all 							trust
host	all all 127.0.0.1	255.255.255.255	trust
host	all all 0.0.0.0/0					md5
host	all all ::1/128						md5
hostssl all all 127.0.0.1	255.255.255.255	md5
hostssl all all 0.0.0.0/0					md5
hostssl all all ::1/128						md5
EOF
	close $fh;
}

sub create_compat_symlinks{
	my $pg_ver = $_[0];
	my $distro = $_[1];

	print "<hr>Creating compatibility symlinks<br>";

	if($distro =~ /redhat/i){
		symlink_file("/usr/lib/systemd/system/postgresql-$pg_ver.service", '/usr/lib/systemd/system/postgresql.service');
		symlink_file("/usr/pgsql-$pg_ver/bin/pg_config",	'/usr/bin/pg_config');
		symlink_file("/var/lib/pgsql/$pg_ver/data", 		'/var/lib/pgsql/data');
		symlink_file("/var/lib/pgsql/$pg_ver/backups",		'/var/lib/pgsql/backups');

	}elsif($distro =~ /ubuntu/i){
		make_dir('/var/lib/pgsql', 0754, 0);
		symlink_file("/var/lib/postgresql/$pg_ver/main",	'/var/lib/pgsql/main');
		symlink_file("/var/lib/postgresql/$pg_ver/backups", '/var/lib/pgsql/backups');
	}
}

&ui_print_header(undef, $text{'pg_inst_title'}, "", "intro", 1, 1);

&ReadParse();
my $post_flag = $in{'post_flag'} || 0;

my @pg_versions = get_versions();
my $pg_ver = $pg_versions[0];
if($in{'pg_ver'}){
	$pg_ver = $in{'pg_ver'};
}elsif(get_installed_pg_version()){
	$pg_ver = get_installed_pg_version();
}

my %pkgs;
my %pkgs_installed;
my $srv_pkg;
my $show_repo_install_info = 0;
my $pgconf_dir;	#PG conifguration directory

my %osinfo = &detect_operating_system();
if( $osinfo{'os_type'} =~ /redhat/i){	#other redhat
	my @temp = split /\s/, $osinfo{'real_os_type'};
	my $distro = $temp[0];
	if($distro eq "Scientific"){
		$distro = 'sl';
	}

	@temp = split /\./, $osinfo{'real_os_version'};
	my $distro_ver = $temp[0];

	if($in{'install_repo'} == 1){
		add_pg_repo_yum($pg_ver, $distro, $distro_ver);
		save_repo_ver($pg_ver);

	}elsif(check_pg_repo_yum($pg_ver, $distro) == 0){	#if repo is not installed
		$show_repo_install_info = 1;
	}else{
		%pkgs 			= get_version_packages_yum($pg_ver);
		%pkgs_installed = get_packages_installed_yum($pg_ver, \%pkgs);

		my $pg_ver2;
		($pg_ver2 = $pg_ver) =~ s/\.//;
		$srv_pkg = "postgresql$pg_ver2-server";
		$pgconf_dir = "/var/lib/pgsql/$pg_ver/data/";
	}

}elsif( $osinfo{'os_type'} =~ /debian/i){
	if($in{'install_repo'} == 1){
		add_pg_repo_apt();
		save_repo_ver($pg_ver);

	}elsif(check_pg_repo_apt() == 0){	#if repo is not installed
		$show_repo_install_info = 1;
	}else{
		%pkgs 			= get_version_packages_apt($pg_ver);
		%pkgs_installed = get_packages_installed_apt($pg_ver, \%pkgs);

		$srv_pkg = "postgresql-$pg_ver";
		$pgconf_dir = "/etc/postgresql/$pg_ver/main/";
	}
}

if($in{'install_repo'} == 1){
	&ui_print_footer("/postgis/pg_install.cgi", $text{'pg_inst_title'});
	exit;
}

#find what was changed
my @pkgs_remove;
my $pkgs_install="";
if($post_flag){
	foreach my $pkg (sort keys %pkgs_installed){
		if($in{$pkg.'_status'} != $pkgs_installed{$pkg}){
			if($in{$pkg.'_status'} == 1){
				$pkgs_install .= "$pkg ";
			}elsif($in{$pkg.'_status'} == 0){
				push(@pkgs_remove, $pkg);
			}
		}
	}
}

#Check what is updated
if ($pkgs_install or @pkgs_remove) {	#if pkgs were edited

	if($pkgs_install =~ /$srv_pkg /){	#if we are installing server
		if(($osinfo{'os_type'} =~ /redhat/i) && (-d $pgconf_dir)){
			print "PG PGDATA=$pgconf_dir exists! Aborting install!<br>";
			&ui_print_footer("", $text{'index_return'});
			exit;
		}
	}

	update_packages($pkgs_install, \@pkgs_remove);

	if($pkgs_install =~ /$srv_pkg /){	#if we have installed the server package
		#check if db is initialized
		if( ! -f $pgconf_dir.'/PG_VERSION'){
			if( $osinfo{'os_type'} =~ /redhat/i){	#other redhat
				pg_initdb("/usr/pgsql-$pg_ver/bin/initdb", $pgconf_dir);
			}
		}

		if($in{'pg_ssl'} == 1){	#if user checked 'Enable SSL'
			pg_enable_ssl($pg_ver, $pgconf_dir);
		}
		if($in{'pg_listen_all'} == 1){
			pg_listen_all($pgconf_dir);
		}

		create_compat_symlinks($pg_ver, $osinfo{'os_type'});
		setup_pg_hba($pgconf_dir);

		my $pg_status = postgresql::is_postgresql_running();
		if($pg_status == 1){
			&postgresql::stop_postgresql();
			&postgresql::start_postgresql();
		}elsif($pg_status == 0){
			&postgresql::start_postgresql();
		}
	}

	if($in{'install_repo'} == 1){
		&ui_print_footer("/postgis/pg_install.cgi", $text{'pg_inst_title'});
	}else{
		&ui_print_footer("", $text{'index_return'});
	}
	exit;
}

print <<EOF;
<script type="text/javascript">
function update_select(){
	var pgverSel = document.getElementById('pg_ver');
	var pg_ver = pgverSel.options[pgverSel.selectedIndex].value;

	get_pjax_content('/postgis/pg_install.cgi?pg_ver='+pg_ver);
}
</script>
EOF

print &ui_form_start("pg_install.cgi", "post");
print &ui_hidden("install_repo", $show_repo_install_info);
print &ui_hidden("post_flag", 1);

print &ui_table_start($text{'pg_inst_edit'}, "width=100%", 3);

print &ui_table_row($text{'pg_versions'},
						&ui_select("pg_ver", $pg_ver, \@pg_versions, 1, 0, undef, undef, "id='pg_ver' onchange='update_select()'"),
						3);

#print server package before everything else
if($srv_pkg ne ""){
	print	&ui_table_hr()
		   .&ui_table_row($srv_pkg, ui_yesno_radio($srv_pkg.'_status', $pkgs_installed{$srv_pkg}).$pkgs{$srv_pkg}, 3);
	if($pkgs_installed{$srv_pkg} == 0){	#if server package is not installed
		#show 'Enable SSL' option
		print &ui_table_row(" ", &ui_checkbox("pg_ssl", 1, $text{'pg_enable_ssl'}, 0), 3);
		print &ui_table_row(" ", &ui_checkbox("pg_listen_all", 1, $text{'pg_listen_all'}, 0), 3);
	}
	print &ui_table_hr();
}

foreach my $pkg (sort keys %pkgs){
	next if($pkg eq $srv_pkg);
	print &ui_table_row($pkg, ui_yesno_radio($pkg.'_status', $pkgs_installed{$pkg}).$pkgs{$pkg} ,3);
}

print &ui_table_end();
print &ui_form_end([ [ "", $text{'pg_inst_save'} ] ]);

if($show_repo_install_info == 1){
	print "PosgreSQL $pg_ver repo is not installed. Select your version and click update to install it!";
}

&ui_print_footer("", $text{'index_return'});
