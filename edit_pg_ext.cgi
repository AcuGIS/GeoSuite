#!/usr/bin/perl

require './geohelm-lib.pl';
foreign_require('postgresql', 'postgresql-lib.pl');


my %ext_info  = (	'postgis' 	=> ['PostGIS', 	 'http://postgis.net', 	0, undef, undef],
					'pgrouting' => ['PgRouting', 'http://pgrouting.org',0, undef, 'postgis'],
					'hstore'	=> ['hStore',	 'https://www.postgresql.org/docs/9.0/static/hstore.html', 0, undef, undef],
					'postgis_topology'		=> ['PostGIS Topology', 	'http://postgis.net', 	0, undef, 'postgis'],
					'fuzzystrmatch'			=> ['FuzzyStringMatch', 	'http://postgis.net', 	0, undef, undef],
					'address_standardizer'	=> ['Address Standardizer', 'http://postgis.net', 	0, undef, undef],
					);

&ui_print_header(undef, $text{'pg_ext_title'}, "");

#TODO: Check if packages are installed
my @pg_dbs = postgresql::list_databases();

&ReadParse();

my $sel_db = $in{'ext_db'} || 'postgres';


foreach my $ename (keys %ext_info){
	my $t = postgresql::execute_sql_safe($sel_db, "select extversion from pg_extension where extname = '$ename'");
	$ext_info{$ename}[3] = $t->{'data'}->[0]->[0];
	$ext_info{$ename}[2]  = $ext_info{$ename}[3] ? 1 : 0;
}

if ($ENV{REQUEST_METHOD} eq "POST") {

	foreach my $ename (keys %ext_info){	#for each extension
		if($in{$ename.'_status'} != $ext_info{$ename}[2]){	#if extension status changed

			if($in{$ename.'_status'} == 1){	#yes = Install
				my $t = postgresql::execute_sql_safe($sel_db, "CREATE EXTENSION $ename");
			}elsif($in{$ename.'_status'} == 0){
				my $drop_sql = "DROP EXTENSION $ename";
				if($in{'ext_cascade'}){
					$drop_sql .= " CASCADE";
				}
				my $t = postgresql::execute_sql_safe($sel_db, $drop_sql);
			}
		}
	}

	#populate ext_info
	foreach my $ename (keys %ext_info){
		my $t = postgresql::execute_sql_safe($sel_db, "select extversion from pg_extension where extname = '$ename'");
		$ext_info{$ename}[3] = $t->{'data'}->[0]->[0];
		$ext_info{$ename}[2]  = $ext_info{$ename}[3] ? 1 : 0;
	}
}

print &ui_form_start("edit_pg_ext.cgi", "post");
print &ui_table_start($text{'pg_ext_edit'}, "width=100%", 2);

print <<EOF;
<script type="text/javascript">
function update_select(){
	var extSel = document.getElementById('ext_db');
	var db_name = extSel.options[extSel.selectedIndex].value;

	window.location='edit_pg_ext.cgi?ext_db='+db_name;
}
</script>
EOF

my @opt_dbs = ();
foreach my $db_name (@pg_dbs) {
	push(@opt_dbs, [ $db_name, $db_name]);
}
print &ui_table_row($text{'pg_ext_database'},
						&ui_select("ext_db", $sel_db, \@opt_dbs, 1, 0, undef, undef, 'id="ext_db" onchange="update_select()"'),
						2);
print &ui_table_row($text{'extensions_cascade'}, &ui_checkbox("ext_cascade", 1, undef, 0), 2);

print ui_table_hr();

foreach my $ename (sort keys %ext_info){

	my $row_label = $ext_info{$ename}[0];
	if($ext_info{$ename}[3]){
		$row_label .= '    (ver. '.$ext_info{$ename}[3].')';
	}elsif($ext_info{$ename}[4]){

		$row_label .= '    (<tt>requires ';

		@deps = split(/,/, $ext_info{$ename}[4]);
		foreach my $dep (@deps) {
			$row_label .= $ext_info{$dep}[0];
		}
		$row_label .= ')</tt>';
	}

	print &ui_table_row($row_label,
			ui_yesno_radio($ename.'_status', $ext_info{$ename}[2]),
			2);
}

print &ui_table_end();
print &ui_form_end([ [ "", $text{'pg_ext_save'} ] ]);

&ui_print_footer("", $text{'index_return'});
