#!/usr/bin/perl

require './geohelm-lib.pl';
require './pg-lib.pl';
foreign_require('postgresql', 'postgresql-lib.pl');

&ReadParse();

&ui_print_header(undef, $text{'snapshots_clone_title'}, "");

my $db_source = $in{'db_source'};
my $db_target = $in{'db_target'};

#make a timestamp
my ($sec, $min, $hour, $mday, $mon, $year, $wday, $yday, $isdst) = localtime(time);
my $db_timestamp = sprintf("%04d-%02d-%02d-%02d-%02d", $year+1900,$mon+1,$mday,$hour,$min);

my $bkup_dir = get_snapshots_dir();

print "Creating snapshot for <tt>$db_source</tt> ...<br>";

my $filename = $db_timestamp.'_'.$db_source.'.dump';

my $file = $bkup_dir.'/'.$filename;
my $err = &postgresql::backup_database($db_source, $file, 'd');
if($err){
	print "Backup of database $db_source to file $file failed:\n$err";
	&ui_print_footer("", $text{'index_return'});
	exit;
}
print "Snapshot $file saved<br>";

&postgresql::execute_sql_safe(undef, "create database $db_target");

print "Cloning <tt>$db_target</tt> using $file ...<br>";

my $err = &postgresql::restore_database($db_target, $file);
if($err){
	print "Restore of database $db_target from $file failed:\n$err";
	&unlink_file($file);	#remove source snapshot file
	&ui_print_footer("", $text{'index_return'});
	exit;
}
&unlink_file($file);	#remove source snapshot file

print "Done<br>";

&ui_print_footer("", $text{'index_return'});
