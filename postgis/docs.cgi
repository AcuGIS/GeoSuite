#!/usr/bin/perl

require './geohelm-lib.pl';

&ReadParse();

&ui_print_header(undef, $text{'docs_title'}, "");
print "Documentaion:";
print "<br><br>";
print "Module Docs:  <a href='https://postgis-module.docs.acugis.com' target='_blank'>https://postgis-module.docs.acugis.com</a>";
print "<br><br>";
print "PostGIS Docs:  <a href='https://postgis.net/documentation/' target='_blank'>https://postgis.net/documentation/</a>";
print "<br><br>";

print "PgRouting Docs:  <a href='http://docs.pgrouting.org/' target='_blank'>http://docs.pgrouting.org/</a>";
print "<br><br>";

print "osm2pgsql Docs:  <a href='https://github.com/openstreetmap/osm2pgsql/blob/master/docs/usage.md
' target='_blank'>https://github.com/openstreetmap/osm2pgsql/blob/master/docs/usage.md
</a>";
print "<br><br>";

print "raster2pgsql Docs:  <a href='http://postgis.refractions.net/docs/using_raster.xml.html
' target='_blank'>http://postgis.refractions.net/docs/using_raster.xml.html
</a>";
print "<br><br>";

print "GDAL Docs:  <a href='https://gdal.org/' target='_blank'>https://gdal.org/</a>";
print "<br><br>";
print "You can add docs and other links via /postgis/docs.cgi"



&ui_print_footer("", $text{'index_return'});