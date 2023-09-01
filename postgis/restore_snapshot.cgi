#!/usr/bin/perl

require './geohelm-lib.pl';
require './pg-lib.pl';
foreign_require('postgresql', 'postgresql-lib.pl');

&ReadParse();

&ui_print_header(undef, $text{'snapshots_create_title'}, "");

my $db_name = $in{'ext_db'};
my $snapshot_file = get_snapshots_dir().'/'.$in{'db_snapshot'};

#if snapshot is archived
if($snapshot_file =~ /.gz$/){

	print "Extracting snapshot $snapshot_file ...<br>";
	my $extracted_file = substr($snapshot_file, 0, -3);
	exec_cmd("gzip -f -c -d \"$snapshot_file\" >$extracted_file");

	$snapshot_file = substr($snapshot_file, 0, -3);
}

#drop the old database
&postgresql::execute_sql_safe(undef, "drop database $db_name");
&postgresql::execute_sql_safe(undef, "create database $db_name");

print "Restoring <tt>$db_name</tt> using $snapshot_file ...<br>";
if($snapshot_file =~ /.sql$/){
	my ($err, $out) = &postgresql::execute_sql_file($db_name, $snapshot_file);
	if($err){
		&error($out);
	}
}else{
	my $err = &postgresql::restore_database($db_name, $snapshot_file);
	if($err){
		print "Restore of database $db_name from  $snapshot_file failed:\n";
	    print $err;
		&ui_print_footer("", $text{'index_return'});
		exit;
	}
}

if($in{'db_snapshot'} =~ /.gz$/){
	&unlink_file($snapshot_file);	#remove extracted file
}

print "Done<br>";
&ui_print_footer("", $text{'index_return'});
