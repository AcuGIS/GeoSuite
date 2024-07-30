#!/usr/bin/perl

require './geosuite-lib.pl';
require './pg-lib.pl';
foreign_require('postgresql', 'postgresql-lib.pl');

&ReadParse();

&ui_print_header(undef, $text{'reports_title'}, "");

# Show tabs
@tabs = ( [ "drill", $text{'reports_tab_drill'},   	"pg_reports.cgi?mode=drill" ],
		  [ "ratio", $text{'reports_tab_ratio'}, 	"pg_reports.cgi?mode=ratio" ],
		  [ "history", $text{'reports_tab_history'},"pg_reports.cgi?mode=history" ]
		);

print &ui_tabs_start(\@tabs, "mode", $in{'mode'} || "drill", 1);

# Display drill form
print &ui_tabs_start_tab("mode", "drill");
print "$text{'drill_desc'}<p>\n";

print &ui_form_start("reports_drill.cgi", "post");
print &ui_table_start($text{'drill_options'}, undef, 2);



my @pg_dbs = pg_list_databases();
my @opt_dbs = ();
foreach my $db_name (@pg_dbs) {
	push(@opt_dbs, [ $db_name, $db_name]);
}
print &ui_table_row($text{'snapshot_db'}, &ui_select("db_name", undef, \@opt_dbs, 1, 0));

print &ui_table_end();
print &ui_form_end([ [ "", "Submit" ] ]);
print &ui_tabs_end_tab();


# Display ratio form
print &ui_tabs_start_tab("mode", "ratio");
print "$text{'ratio_desc'}<p>\n";

print &ui_form_start("reports_cache.cgi", "post");
print &ui_table_start($text{'ratio_options'}, undef, 2);

print &ui_table_row($text{'snapshot_db'}, &ui_select("db_name", undef, \@opt_dbs, 1, 0), 2);

print &ui_table_end();
print &ui_form_end([ [ "", "Submit" ] ]);
print &ui_tabs_end_tab();

# Display history form
print &ui_tabs_start_tab("mode", "history");
print "$text{'history_desc'}<p>\n";

print &ui_form_start("reports_history.cgi", "post");
print &ui_table_start($text{'history_options'}, undef, 2);

print &ui_table_row($text{'snapshot_db'}, &ui_select("db_name", $undef, \@opt_dbs, 1, 0), 2);

print &ui_table_end();
print &ui_form_end([ [ "", "Submit" ] ]);
print &ui_tabs_end_tab();

print &ui_tabs_end(1);

&ui_print_footer("", $text{'index_return'});
