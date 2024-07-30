#!/usr/bin/perl

require './geosuite-lib.pl';
foreign_require('postgresql', 'postgresql-lib.pl');

&ReadParse();

&ui_print_header(undef, $text{'reports_drill_title'}, "");

my $db_name = $in{'db_name'};
print generate_icon('images/refresh.png', 'Refresh', "./reports_drill.cgi?db_name=${db_name}")."<br>";

my $s = &postgresql::execute_sql_safe($db_name, "SELECT  relname, heap_blks_hit, idx_blks_read, idx_blks_hit, idx_blks_hit FROM pg_statio_all_tables where schemaname not in ('information_schema', 'pg_catalog', 'pg_toast') order by heap_blks_hit desc");

if (! @{$s->{'data'}} ) {
	print "No I/O for $db_name";
	&ui_print_footer("", $text{'index_return'});
	exit;
}

local @tds = ("width=5" );
print &ui_columns_start([ 'Table',
						  'Blocks Read',
						  'Blocks Hit',
						  'Toast Read',
						  'Toast Hit' ], 100, 0, \@tds);
foreach $g (@{$s->{'data'}}) {
		local @cols;

		push(@cols, $g->[0]);
		push(@cols, $g->[1]);
		push(@cols, $g->[2]);
		push(@cols, $g->[3]);
		push(@cols, $g->[4]);

		print &ui_columns_row(\@cols, \@tds);
		}
print &ui_columns_end();

&ui_print_footer("", $text{'index_return'});
