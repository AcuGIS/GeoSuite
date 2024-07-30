#!/usr/bin/perl

require './geosuite-lib.pl';
foreign_require('postgresql', 'postgresql-lib.pl');

&ReadParse();

&ui_print_header(undef, $text{'reports_ratio_title'}, "");

print generate_icon('images/refresh.png', 'Refresh', "./reports_cache.cgi?db_name=${db_name}")."<br>";

my $db_name = $in{'db_name'};

my $s = &postgresql::execute_sql_safe(undef, "select datname, blks_read, blks_hit, (blks_read/blks_hit), tup_fetched as some FROM pg_stat_database WHERE datname = '$dbname'");

if (@{$s->{'data'}} ) {
	local @tds = ( "width=5" );
	print &ui_columns_start([ 'Database',
							  'Blocks Read',
							  'Blocks Hit',
							  '% From Cache',
							  'tup_fetched' ], 100, 0, \@tds);
	foreach $g (@{$s->{'data'}}) {
			local @cols;

			push(@cols, $g->[0]);
			push(@cols, $g->[1]);
			push(@cols, $g->[2]);
			#push(@cols, $g->[3]);
			my $temp = sprintf "%.4f", $g[1]/($g[1]+$g[2]+1);
			my $perc_from_cache = (100-($temp*(100)));
			push(@cols, $perc_from_cache);

			push(@cols, $g->[4]);

			print &ui_columns_row(\@cols, \@tds);
			}
	print &ui_columns_end();
}else{
	print "No results for $db_name";
	&ui_print_footer("", $text{'index_return'});
	exit;
}

&ui_print_footer("", $text{'index_return'});
