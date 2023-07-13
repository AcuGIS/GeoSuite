
**********************
Leaflet GeoJson
**********************

.. contents:: Table of Contents


Access
=================

A basic leafletjs application employing pg_featurserv is available at http://yourdomain.com/LeafletGeoJson.html

.. image:: _static/leaflet-geojson-europe.png

Like our Choropleth map, this map leverages GeoJson from pg_featurserv and Query Features, but does not employ the Choropleth plugin

 
pg_featurserv URL
=================

If you have issues with the pg_featurserv url, it can be edited at line 41 below

.. code-block:: console

	$.getJSON("http://domain.com:9000/collections/public.countries/items.json?limit=100&continent=Europe&properties=name,gdp_md", function(data) {

	
Content
=========

The content of the html page is displayed below.

.. code-block:: console

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
    
    	$.getJSON("$.getJSON("http://domain.com:9000/collections/public.countries/items.json?limit=100&continent=Europe&properties=name,gdp_md", function(data) {", function(data) {

    	function onEachFeature(feature, layer) {
          layer.bindPopup("Name: " + feature.properties.name + "<br>" + "Abbreviation: " + feature.properties.gdp_md);
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
   
