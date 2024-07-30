#!/usr/bin/perl

require './geoserver-lib.pl';
&ui_print_header($text{'editor_desc1'}, $text{'editor_title'}, "");

my $files_home = '/var/www/html';
my $home_path   = ($in{'home_path'})   ? $in{'home_path'} : $files_home;

if($ENV{'CONTENT_TYPE'} =~ /boundary=(.*)$/) {
	&ReadParseMime();

	$home_path = $in{'home_path'};

	if(-d $home_path.'/'.$in{'file'}){
		$home_path .= $in{'file'};
	}else{
		$in{'file'} = $home_path. '/'.$in{'file'};
	}
}else{
	&ReadParse();
	$home_path   = $files_home;
}

print &ui_form_start("edit_files.cgi", 'form-data');
print &ui_hidden("home_path"  , $home_path);

print "<b>$text{'manual_file'}</b>\n";
if(-f $in{'file'}){
	print &ui_textbox("file", $in{'file'},   40)." ".&file_chooser_button("file", 0, undef, $home_path, '1');
}else{
	print &ui_textbox("file", $home_path,   40)." ".&file_chooser_button("file", 0, undef, $home_path, '1');
}
print &ui_submit($text{'load_ok'});
print &ui_form_end();

# Show the file contents
print &ui_form_start("save_files.cgi", "form-data");
print &ui_hidden("file", $in{'file'}),"\n";
if(-f $in{'file'}){
	$data = &read_file_contents($in{'file'});
	print &ui_textarea("data", $data, 20, 80),"\n";
	print &ui_form_end([["save", $text{'save'} ]]);
}else{
	print &ui_form_end();
}

&ui_print_footer("", $text{'index_return'});
