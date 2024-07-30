#!/usr/bin/perl

require './geosuite-lib.pl';
foreign_require('postgresql', 'postgresql-lib.pl');

&ReadParse();

&ui_print_header(undef, $text{'reports_history_title'}, "");

my $db_name = $in{'db_name'};

print generate_icon('images/refresh.png', 'Refresh', "./reports_history.cgi?db_name=${db_name}")."<br>";

my $s = &postgresql::execute_sql_safe($db_name, "SELECT relname, n_tup_ins, n_tup_upd, n_tup_del, last_vacuum, last_analyze, last_autovacuum, last_autoanalyze FROM pg_stat_user_tables");

if (@{$s->{'data'}} ) {
	local @tds = ( "width=5", "align=center");
	print &ui_columns_start([ 'Table',
							  'Inserts',
							  'Updates',
							  'Delete',
							  'Last Vac',
							  'Last Analyze',
							  'Last Auto Vac',
							  'Last Auto Analyze'], 100, 0, \@tds, 'History of '.$db_name);
	foreach $g (@{$s->{'data'}}) {
			local @cols;

			push(@cols, $g->[0]);
			push(@cols, $g->[1]);
			push(@cols, $g->[2]);
			push(@cols, $g->[3]);
			push(@cols, $g->[4]);
			push(@cols, $g->[5]);
			push(@cols, $g->[6]);
			push(@cols, $g->[7]);

			print &ui_columns_row(\@cols, \@tds);
	}
	print &ui_columns_end();
}else{
	print "No results for $db_name";
	&ui_print_footer("", $text{'index_return'});
	exit;
}

&ui_print_footer("", $text{'index_return'});
