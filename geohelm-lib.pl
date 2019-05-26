=head1 geohelm-lib.pl

Functions for managing OpenGeo tasks.

  foreign_require("geohelm", "geohelm-lib.pl");
  @sites = geohelm::list_geohelm_websites()

=cut

BEGIN { push(@INC, ".."); };
use WebminCore;
init_config();

require './tomcat-lib.pl';
require './geoserver-lib.pl';
use File::Basename;

foreign_require('software', 'software-lib.pl');

sub get_geohelm_config
{
my $lref = &read_file_lines($config{'geohelm_conf'});
my @rv;
my $lnum = 0;
foreach my $line (@$lref) {
    my ($n, $v) = split(/\s+/, $line, 2);
    if ($n) {
      push(@rv, { 'name' => $n, 'value' => $v, 'line' => $lnum });
      }
    $lnum++;
    }
return @rv;
}

sub get_acugeo_versions{
	my %tver = &get_catalina_version();
	my %gver = &get_geoserver_version();


	$tver{'geoserver_ver'}	 = $gver{'number'};
	$tver{'geoserver_build'} = $gver{'jdk'};
	return %tver;
}

sub process_file_source{
	my $file = '';

	if (($in{'source'} == 0) && ($in{'file'} ne "")) {	# from local file
		&error_setup(&text('source_err0', $in{'file'}));
		$file = $in{'file'};
		if (!(-r $file)){
			&inst_error($text{'source_err0'});
		}

	}elsif (($in{'source'} == 1) && ($in{'upload_filename'} ne "")) {	# from uploaded file
		&error_setup($text{'source_err1'});
		$need_unlink = 1;
		if ($no_upload) {
			&inst_error($text{'source_err1.2'});
		}
		$file = transname(file_basename($in{'upload_filename'}));
		open(MOD, ">$file");
		binmode(MOD);
		print MOD $in{'upload'};
		close(MOD);

	}elsif ($in{'source'} == 2 and $in{'url'} ne '') {	# from ftp or http url (possible third-party)
		$url = $in{'url'};
		&error_setup(&text('source_err2', $url));
		$file = &transname(file_basename($url));
		$need_unlink = 1;
		my $error;
		$progress_callback_url = $url;
		if ($url =~ /^(http|https):\/\/([^\/]+)(\/.*)$/) {
			$ssl = $1 eq 'https';
			$host = $2; $page = $3; $port = $ssl ? 443 : 80;
			if ($host =~ /^(.*):(\d+)$/) { $host = $1; $port = $2; }
			my %cookie_headers = ('Cookie'=>'oraclelicense=accept-securebackup-cookie');
			&http_download($host, $port, $page, $file, \$error,
				       \&progress_callback, $ssl, undef, undef, 0, 0, 1, \%cookie_headers);
		} elsif (
			$url =~ /^ftp:\/\/([^\/]+)(:21)?\/(.*)$/) {
			$host = $1; $ffile = $3;
			&ftp_download($host, $ffile, $file, \$error, \&progress_callback);
		}else {
			&inst_error($text{'source_err3'});
		}
		&inst_error($error) if ($error);
	}
	return $file;
}

sub unzip_me{
	my $file  = $_[0];
	my @suffixlist = ('\.zip');
	($file_name,$path,$lib_suffix) = fileparse($file,@suffixlist);

	my $unzip_dir = "/tmp/.webmin/$file_name";

	&make_dir($unzip_dir, 0754, 1);

	my $unzip_out;
	my $unzip_err;
	print "<hr>Unzipping to $unzip_dir ...<br>";
	local $out = &execute_command("unzip -u \"$file\" -d \"$unzip_dir\"", undef, \$unzip_out, \$unzip_err, 0, 0);

	if($unzip_err){
		&error("Error: unzip: $unzip_err");
	}else{
		$unzip_out = s/\r\n/<br>/g;
		print &html_escape($unzip_out);
	}
	return $unzip_dir;
}

sub search_pkg{
  my $pattern  = $_[0];

  my @avail = ();
  if (defined(&software::update_system_search)) {
  	# Call the search function
    @avail = &software::update_system_search($pattern);
  } else {
  	# Scan through list manually
  	@avail = &software::update_system_available();
  	@avail = grep { $_->{'name'} =~ /\Q$pattern\E/i } @avail;
  }
  return sort @avail;
}
