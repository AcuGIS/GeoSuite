#!/usr/bin/perl

require './geosuite-lib.pl';
require './pg-lib.pl';
use File::Basename;
use File::Path 'rmtree';

foreign_require('postgresql', 'postgresql-lib.pl');

&ui_print_header(undef, $text{'add_shape_title'}, "");

print <<EOF;
<script type="text/javascript">
function update_select(){
	var dbnameSel = document.getElementById('db_name');
	var dbname    = dbnameSel.options[dbnameSel.selectedIndex].value;

	get_pjax_content('/geosuite/add_shape.cgi?db_name='+dbname);
}
</script>
EOF

print "$text{'add_shape_desc1'}<p>\n";

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

sub build_shp2pgsql_opt(){
	my $opt = '';
	if($in{'load_type'}){
		$opt .= ' '.$in{'load_type'};
	}
	if($in{'srid'} && ($in{'srid'} =~ /^[0-9]+$/)){
		$opt .= ' -s '.$in{'srid'};
	}

	my @optChars = ('D', 'G', 'k', 'I', 'S','n');
	foreach my $c (@optChars){
		if($in{'opt'.$c}){
			$opt .= ' -'.$c;
		}
	}
	return $opt;
}

sub add_shape{
	my $shp2pgsql_opt = $_[0];
	my $zipname = $_[1];

	my $shp_dir = unzip_me($zipname);

	#open unzipped dir, find all shape files
	opendir(DIR, $shp_dir);
	my @files = grep(/\.shp$/,readdir(DIR));
	closedir(DIR);

	my $shp_name = $files[0];	#return first shape file
	my $shp_path = $shp_dir.'/'.$shp_name;

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
			my $shp_name = file_basename($shp_path);
			$table_name .= substr($shp_name, 0, -4);
		}else{
			$table_name .= $in{'db_table'};
		}
	}else{
		$table_name .= $in{'db_table_new'};
	}

	#build command line
	my $cmd = "$config{'shp2pgsql_path'} $shp2pgsql_opt $shp_path $table_name | psql -U $in{'db_user'} -d $in{'db_name'}";

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

	my $zipname = process_file_source();
	if($zipname ne ""){
		my $shp2pgsql_opt = build_shp2pgsql_opt();
		add_shape($shp2pgsql_opt, $zipname);
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

my @load_type_opt = ();
push(@load_type_opt, ['-c', '-c Creates a new table and populates it']);
push(@load_type_opt, ['-d', '-d Drops the table, then recreates it and populates it with current shape file data']);
push(@load_type_opt, ['-a', '-a Appends shape file into current table, must be eaxctly the same table schema']);
push(@load_type_opt, ['-p', '-p Prepare mode, only creates the table']);

print &ui_form_start("add_shape.cgi", "form-data");
print &ui_table_start($text{'shape_install'}, undef, 2);

print &ui_table_row($text{'pg_ext_database'}, &ui_select("db_name", $sel_db, \@opt_dbs, 1, 0,
														undef, undef, 'id="db_name" onchange="update_select()"'), 2);
print &ui_table_row($text{'load_type'}, &ui_select("load_type", undef, \@load_type_opt, 1, 0), 2);
print &ui_table_row($text{'set_srid'}, &ui_textbox("srid", '0', 10), 2);
print &ui_table_row($text{'db_user'}, &ui_select("db_user", undef, \@opt_users, 1, 0), 2);
print &ui_table_row($text{'db_schema'}, &ui_select("db_schema", 'public', \@opt_schemas, 1, 0).
										' or '.&ui_textbox("db_schema_new", '', 10).
										' <b>New schema name</b>'
										, 2);
print &ui_table_row($text{'db_table'},  &ui_select("db_table", undef, \@opt_tbls, 1, 0).
										' or '.&ui_textbox("db_table_new", '', 10).
										' <b>New table name</b>',
										2);

print &ui_table_row($text{'shape_source'},
			&ui_radio_table("source", 0,
					[
						[ 0, $text{'source_local'},   &ui_textbox("file", undef, 40)." ". &file_chooser_button("file", 0) ],
						[ 1, $text{'source_uploaded'},&ui_upload("upload", 40) ],
						[ 2, $text{'source_ftp'},     &ui_textbox("url", undef, 40) ]
					]),
			2);

print &ui_table_row(undef, '<b>Load options:</b>', 2);

print &ui_table_row(undef, &ui_checkbox('optD', '-D', 'Load Using Copy rather than Insert', 1), 2);
print &ui_table_row(undef, &ui_checkbox('optG', '-G', 'Use Geography Type', 0), 2);
print &ui_table_row(undef, &ui_checkbox('optk', '-k', 'Preserve Case of Column Names', 0), 2);
print &ui_table_row(undef, &ui_checkbox('optI', '-I', 'Create a spatial index on the geocolumn', 1), 2);
print &ui_table_row(undef, &ui_checkbox('optS', '-S', 'Create Simple Geometries', 0), 2);
print &ui_table_row(undef, &ui_checkbox('optn', '-n', 'Only import DBF file', 0), 2);

print &ui_table_end();
print &ui_form_end([ [ "", $text{'shape_load'} ] ]);


if($output ne ""){

	print ui_hr().'<br>'.$output;
}

&ui_print_footer("", $text{'index_return'});

