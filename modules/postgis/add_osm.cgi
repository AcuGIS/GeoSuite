#!/usr/bin/perl

require './geosuite-lib.pl';
require './pg-lib.pl';

use File::Basename;
use File::Path 'rmtree';

foreign_require('postgresql', 'postgresql-lib.pl');
foreign_require('proc', 'proc-lib.pl');

&ui_print_header(undef, $text{'add_osm_title'}, "");

print <<EOF;
<script type="text/javascript">
function update_select(){
	var dbnameSel = document.getElementById('db_name');
	var dbname    = dbnameSel.options[dbnameSel.selectedIndex].value;

	get_pjax_content('/postgis/add_osm.cgi?db_name='+dbname);
}
</script>
EOF

print "$text{'add_osm_desc1'}<p>\n";

my $output = '';

my $sel_db = '';
my @pg_dbs = pg_list_databases();
my @opt_dbs = ();
foreach my $db_name (@pg_dbs) {
	push(@opt_dbs, [ $db_name, $db_name]);
}

sub inst_error{
	print "<b>$main::whatfailed : $_[0]</b> <p>\n";
	&ui_print_footer("", $text{'index_return'});
	exit;
}

sub build_osm2pgsql_opt(){
	my $opt = '';

	$opt = $in{'db_mode'};
	$opt .= ' '.$in{'osm_coor_fmt'};
	$opt .= ' -C '.$in{'osm_mem_cache'};
	$opt .= ' --number-processes '.$in{'osm_cpu_cores'};

	if($in{'optE'} && ($in{'optE'} =~ /^[0-9]+$/)){
		$opt .= ' -E '.$in{'optE'};
	}

	my @optChars = ('s', 'k');
	foreach my $c (@optChars){
		if($in{'opt'.$c}){
			$opt .= ' -'.$c;
		}
	}
	return $opt;
}

sub add_osm{
	my $osm2pgsql_opt = $_[0];
	my $pbf_path = $_[1];
	my $pbf_name = file_basename($pbf_path);


	my $table_schema;
	if($in{'db_schema_new'} eq ""){
		$table_schema = $in{'db_schema'};
	}else{ #create new schema
		$table_schema = $in{'db_schema_new'};
		&error_setup(&text('db_err1', 'Creating new schema'));
		local $t = postgresql::execute_sql_safe($sel_db, "CREATE SCHEMA $table_schema AUTHORIZATION $in{'db_user'}");
	}

	my $table_name = $table_schema.'.';
	if($in{'db_table_new'} eq ""){
		if($in{'db_table'} eq ""){

			$table_name .= substr($pbf_name, 0, rindex($pbf_name, '.'));	#drop file extension
		}else{
			$table_name .= $in{'db_table'};
		}
	}else{
		$table_name .= $in{'db_table_new'};
	}

	$osm2pgsql_opt .= " -U $in{'db_user'} -d $in{'db_name'}";

	#build command line
	my $cmd = &has_command('osm2pgsql')." $osm2pgsql_opt $pbf_path";

	#insert shape in db
	my $cmd_out;
	my $cmd_err;
	local $out = &execute_command($cmd, undef, \$cmd_out, \$cmd_err, 0, 0);

	rmtree($shp_dir);	#remove temp dir

	$output .= "<b>Loader Results:</b>";
	$output .= "<br><tt>$cmd</tt><br>";
	$output .= &html_escape($cmd_err);
	$output .= &html_escape($cmd_out);

	$output =~ s/(\r?\n)+/<br>/g;
}

if($ENV{'CONTENT_TYPE'} =~ /boundary=(.*)$/) {
	&ReadParseMime();
	$sel_db = $in{'db_name'};

	my $pbfname = process_file_source();
	if($pbfname ne ""){
		my $osm2pgsql_opt = build_osm2pgsql_opt();
		add_osm($osm2pgsql_opt, $pbfname);
	}
}else {
	&ReadParse();
	$no_upload = 1;
	$sel_db = $in{'db_name'} ? $in{'db_name'} : 'postgres';
}

local $t = postgresql::execute_sql_safe($sel_db, 'select usename from pg_user');
my @pg_users = sort { lc($a) cmp lc($b) } map { $_->[0] } @{$t->{'data'}};
my @opt_users = ();
foreach my $name (@pg_users) {
	push(@opt_users, [ $name, $name]);
}

$t = postgresql::execute_sql_safe($sel_db, 'select schema_name from information_schema.schemata');
my @pg_schemas = sort { lc($a) cmp lc($b) } map { $_->[0] } @{$t->{'data'}};
my @opt_schemas = ();
push(@opt_schemas, ['public', 'public']);
foreach my $name (@pg_schemas) {
	push(@opt_schemas, [ $name, $name]);
}

my @db_tables = postgresql::list_tables($sel_db);
my @opt_tbls = ();
foreach my $name (@db_tables) {
	push(@opt_tbls, [ $name, $name]);
}
print &ui_form_start("add_osm.cgi", "form-data");
print &ui_table_start($text{'shape_install'}, undef, 2);

print &ui_table_row($text{'pg_ext_database'}, &ui_select("db_name", $sel_db, \@opt_dbs, 1, 0,
														undef, undef, 'id="db_name" onchange="update_select()"'), 2);
print &ui_table_row($text{'db_user'}, &ui_select("db_user", undef, \@opt_users, 1, 0), 2);
print &ui_table_row($text{'db_schema'}, &ui_select("db_schema", 'public', \@opt_schemas, 1, 0).
										' or '.&ui_textbox("db_schema_new", '', 10).
										' <b>New schema name</b>'
										, 2);

print &ui_table_row($text{'osm_source'},
			&ui_radio_table("source", 0,
					[
						[ 0, $text{'source_local'},   &ui_textbox("file", undef, 40)." ". &file_chooser_button("file", 0) ],
						[ 1, $text{'source_uploaded'},&ui_upload("upload", 40) ],
						[ 2, $text{'source_ftp'},     &ui_textbox("url", undef, 40) ]
					]),
			2);

print &ui_table_row(undef, '<b>Load options:</b>', 2);

print &ui_table_row($text{'slim_mode'}, &ui_checkbox('opts', '-s', '( --slim )', 0), 2);
print &ui_table_row($text{'osm_hstore'}, &ui_checkbox('optk', '-k', '( --hstore )', 0), 2);

my @opt_coor_opt = (['-m', 'mercator'], ['-l', 'lat&long']);
print &ui_table_row($text{'osm_coor_fmt'}, &ui_select("osm_coor_fmt", undef, \@opt_coor_opt, 1, 0), 2);

print &ui_table_row($text{'osm_epsg'}, &ui_textbox("optE", undef, 10), 2);

my @opt_mem_opt = (['200', '200MB'], ['800', '800MB']);
@m = &proc::get_memory_info();
if(@m){
	my $free_mem_gb = $m[1] / (1024*1024);
	for my $n (1..$free_mem_gb){
		push(@opt_mem_opt, [$n*1000, $n.'GB']);
	}
}
print &ui_table_row($text{'osm_mem_cache'}, &ui_select("osm_mem_cache", undef, \@opt_mem_opt, 1, 0), 2);

my @opt_cpu_opt = ([1,1]);
@c = &proc::get_cpu_info();
if(@c){
	for my $n (1..$c[7]){
		push(@opt_mem_opt, [$n, $n]);
	}
}
print &ui_table_row($text{'osm_cpu_cores'}, &ui_select("osm_cpu_cores", undef, \@opt_cpu_opt, 1, 0), 2);

print &ui_table_end();
print &ui_form_end([ [ "", $text{'shape_load'} ] ]);


if($output ne ""){

	print ui_hr().'<br>'.$output;
}

&ui_print_footer("", $text{'index_return'});
