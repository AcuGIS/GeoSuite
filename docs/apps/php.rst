
**********************
Leaflet and PHP
**********************

.. contents:: Table of Contents


Access
=================

A web application employing PostGIS, PHP, and LeafletJS.

This application uses the PHP-Database-GeoJSON file from https://github.com/bmcbride/PHP-Database-GeoJSON

It can be access via http://domain.com/LeafletPHPDemo.html

.. image:: _static/leaflet-geojson-europe.png

 
   

Initialize
=================

If you any issues connecting to PostGIS, check line 12 of get-json.phpbelow:


	$conn = new PDO('pgsql:host=localhost;dbname=postgisftw','pgis','<YourPgisPassword>');
	
Replace <YourPgisPassword> with the password for user pgis.

The password for user pgis is auto-generated and can be found at /home/pgis/.pgpass

Security
=================

This map loads GeoJson generated from PostGIS directly and does not employ the pg_featurserv or GeoServer urls.

As you may have noticed, it is identical to our LeafletGeoJson.html map except for the GeoJson source url.

While urls for pg_featurserv and GeoServer can be secured, the url will always be exposed to your users as it is required for map rendering.

Conversely, the PostGIS PHP application uses the get-json.php file to establish a connection to PostGIS in the background.

You can employ the same method for loading GeoJson from pg_featurserv, GeoServer, or any other json url.

There is performance penalty, however, as the GeoJson is loaded into the end users browser and for large data sets, this can become extremely slow.

Structure
=============

The app is located at::

	/vaw/www/html/LeafletPHPDemo.html
		
This is just a basic Leafletjs map in which we are pulling in geojson from get-json.php::

	$.getJSON("get-json.php", function(data) {
	

Content
=========

The content of the html page is displayed below.

.. code-block:: console
   :linenos:

	<!doctype html>
	<html>
	<head>
  	<style type="text/css">
    	body {
      	padding: 0;
      	margin: 0;
    	}

    	html, body, #map {
      	height: 100%;
    	}

  	</style>

	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.1.0/dist/leaflet.css"
   	integrity="sha512-wcw6ts8Anuw10Mzh9Ytw4pylW8+NAD4ch3lqm9lzAsTxg0GFeJgoAtxuCLREZSC5lUXdVyo/7yfsqFjQ4S+aKw=="
   	crossorigin=""/>

    	<script src="https://unpkg.com/leaflet@1.1.0/dist/leaflet.js"
   	integrity="sha512-mNqn2Wg7tSToJhvHcqfzLMU6J4mkOImSPTxVZAdo+lcPlk+GhZmYgACEe0x35K7YzW1zJ7XyJV/TT1MrdXvMcA=="
   	crossorigin=""></script>
  	</head> 
  
	<script src="http://code.jquery.com/jquery-2.1.0.min.js"></script>
	</head>
	<body>
  	<div id="map"></div>
  	<script>


	var osmUrl='http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  	var osmAttrib='Data &copy <a href="http://openstreetmap.org">OpenStreetMap</a> contributors';
  	var osm = new L.TileLayer(osmUrl, {minZoom: 2, maxZoom: 8, attribution: osmAttrib});
    
    	$.getJSON("get-json.php", function(data) {

    	function onEachFeature(feature, layer) {
          layer.bindPopup("Name: " + feature.properties.name + "<br>" + "Abbreviation: " + feature.properties.abbrev);
  	  }   

    	var geojson = L.geoJson(data, {
      	onEachFeature: onEachFeature
    	});

    	var map = L.map('map').fitBounds(geojson.getBounds()); 
    
    	osm.addTo(map);
    	geojson.addTo(map);
  	});
  	</script>

	</body>
	</html>


Documentation
==============
https://leafletjs.com/

https://leafletjs.com/examples/geojson/
   
