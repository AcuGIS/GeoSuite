#!/usr/bin/perl

require './geosuite-lib.pl';
&ReadParse();
&error_setup($text{'stop_err'});

my $svc = 'none';
my $ctl = 'none';

if($in{'app_pg_tileserv'}){ #Tile
  $svc = 'pg_tileserv';
  $ctl = $in{'app_pg_tileserv'};

}elsif($in{'app_pg_featureserv'}){ #Feature
  $svc = 'pg_featureserv';
  $ctl = $in{'app_pg_featureserv'};
}

my $cmd_out = "";
my $cmd_err = "";
my $out = &execute_command("/bin/systemctl $ctl $svc", undef, \$cmd_out, \$cmd_err, 0, 0);
if($cmd_err ne ""){
  &error($cmd_err)
}

&redirect("");
