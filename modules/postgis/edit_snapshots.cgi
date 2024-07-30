#!/usr/bin/perl

require './geosuite-lib.pl';
require './pg-lib.pl';
foreign_require('postgresql', 'postgresql-lib.pl');

&ReadParse();

&ui_print_header(undef, $text{'snapshots_title'}, "");

# Show tabs
@tabs = ( [ "create", $text{'snapshots_tab_create'},   "edit_snapshots.cgi?mode=create" ],
		  [ "restore", $text{'snapshots_tab_restore'}, "edit_snapshots.cgi?mode=restore" ],
		  [ "clone", $text{'snapshots_tab_clone'}, "edit_snapshots.cgi?mode=clone" ]
		);

print &ui_tabs_start(\@tabs, "mode", $in{'mode'} || "create", 1);

# Display create form
print &ui_tabs_start_tab("mode", "create");
print "$text{'snapshots_desc1'}<p>\n";

print &ui_form_start("create_snapshot.cgi", "post");
print &ui_table_start($text{'snapshot_options'}, undef, 2);

my @pg_dbs = pg_list_databases();
my @opt_dbs = ();
foreach my $db_name (@pg_dbs) {
	push(@opt_dbs, [ $db_name, $db_name]);
}
print &ui_table_row($text{'snapshot_db'}, &ui_select("db_name", undef, \@opt_dbs, 1, 0));
print &ui_table_row($text{'snapshot_format'},
								&ui_checkbox("dump_sql", 	1,$text{'snapshot_dump_sql'},    1).
								&ui_checkbox("dump_custom", 1,$text{'snapshot_dump_custom'}, 1)		);
print &ui_table_row($text{'snapshot_compress'},
								&ui_checkbox("dump_compress",1,$text{'snapshot_compress_gz'},    1));

print &ui_table_end();
print &ui_form_end([ [ "", $text{'snapshots_createok'} ] ]);
print &ui_tabs_end_tab();


# Display restore form
print &ui_tabs_start_tab("mode", "restore");
print "$text{'snapshots_desc2'}<p>\n";

print &ui_form_start("restore_snapshot.cgi", "post");
print &ui_table_start($text{'snapshot_restore'}, undef, 2);


my $sel_db = $in{'ext_db'} || 'postgres';

print <<EOF;
<script type="text/javascript">
function update_select(){
	var extSel = document.getElementById('ext_db');
	var db_name = extSel.options[extSel.selectedIndex].value;

	get_pjax_content('/postgis/edit_snapshots.cgi?ext_db='+db_name+'&mode=restore');
}
</script>
EOF

my @opt_dbs = ();
foreach my $db_name (@pg_dbs) {
	push(@opt_dbs, [ $db_name, $db_name]);
}
print &ui_table_row($text{'snapshot_db'},
						&ui_select("ext_db", $sel_db, \@opt_dbs, 1, 0, undef, undef, 'id="ext_db" onchange="update_select()"'),
						2);


#make a list of snapshots for the selected db
my @db_snapshots = get_db_snapshots($sel_db);
my @opt_db_snapshots = ();
foreach my $name (@db_snapshots) {
	push(@opt_db_snapshots, [ $name, $name]);
}

print &ui_table_row($text{'snapshot_available'}, &ui_select("db_snapshot", undef, \@opt_db_snapshots, 1, 0), 2);

print &ui_table_end();
print &ui_form_end([ [ "", $text{'snapshots_restoreok'} ] ]);
print &ui_tabs_end_tab();

# Display clone form
print &ui_tabs_start_tab("mode", "clone");
print "$text{'snapshots_desc3'}<p>\n";

print &ui_form_start("clone_db.cgi", "post");
print &ui_table_start($text{'snapshot_clone'}, undef, 2);

print &ui_table_row($text{'snapshot_source'},
						&ui_select("db_source", $sel_db, \@opt_dbs, 1, 0),
						2);
print &ui_table_row($text{'snapshot_target'}, &ui_textbox("db_target", '', 10),	2);

print &ui_table_end();
print &ui_form_end([ [ "", $text{'snapshots_cloneok'} ] ]);
print &ui_tabs_end_tab();

print &ui_tabs_end(1);

&ui_print_footer("", $text{'index_return'});
