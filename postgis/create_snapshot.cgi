#!/usr/bin/perl

require './geohelm-lib.pl';
require './pg-lib.pl';
foreign_require('postgresql', 'postgresql-lib.pl');

&ReadParse();


&ui_print_header(undef, $text{'snapshots_create_title'}, "");

my $db_name = $in{'db_name'};

#-F option for pg_dump
my @formats = ();
push(@formats, 'p') if($in{'dump_sql'});
push(@formats, 'c') if($in{'dump_custom'});

#make a timestamp
my ($sec, $min, $hour, $mday, $mon, $year, $wday, $yday, $isdst) = localtime(time);
my $db_timestamp = sprintf("%04d-%02d-%02d-%02d-%02d", $year+1900,$mon+1,$mday,$hour,$min);

my $bkup_dir = get_snapshots_dir();
my %pg_conf = foreign_config('postgresql');

print "Creating snapshots for <tt>$db_name</tt> ...<br>";
foreach my $fmt (@formats){
	my $filename = $db_timestamp.'_'.$db_name;

	$filename .= '.sql' if $fmt eq 'p';
	$filename .= '.dump' if $fmt eq 'c';

	my $file = $bkup_dir.'/'.$filename;
	my $err = &postgresql::backup_database($db_name, $file, $fmt);
	if($err){
		print "Backup of database $db_name to file $file failed:\n";
        print $err;
		&ui_print_footer("", $text{'index_return'});
		exit;
	}

	if($in{'dump_compress'}){

		exec_cmd("gzip -c \"$file\" >$file.gz");

		print "Snapshot $file.gz saved<br>";
		&unlink_file($file);
		&set_ownership_permissions($pg_conf{'login'},$pg_conf{'login'}, 0444, $file.'.gz');
	}else{
		print "Snapshot $file saved<br>";
	}
}

print "Done<br>";
&ui_print_footer("", $text{'index_return'});
