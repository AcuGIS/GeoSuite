#!/usr/bin/perl
# Update a manually edited config file

require './geohelm-lib.pl';
&error_setup($text{'manual_err'});
&ReadParseMime();

my $files_home = '/var/www/html';

if( (index($in{'file'}, $files_home) != 0) || (! -f $in{'file'}) ){
	&error($text{'source_err0'});
}

$in{'data'} =~ s/\r//g;
$in{'data'} =~ /\S/ || &error($text{'manual_edata'});

# Write to it
&open_lock_tempfile(DATA, ">$in{'file'}");
&print_tempfile(DATA, $in{'data'});
&close_tempfile(DATA);
&redirect("");

