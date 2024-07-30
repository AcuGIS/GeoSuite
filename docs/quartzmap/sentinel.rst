Sentinel Hub
=====

QuartzMap supports dates, date ranges, and date comparison for Sentinel Hub.

You can use either the Sentinel Hub QGIS plugin or standard WMS.

Publish your map as normal:

1. In QGIS, open the Project you wish to publish and start qgis2web

2. FTP the map using FTP or use the "Upload" function to upload.

   .. image:: images/sentinel-1.jpg

QuartzMap will detect the date and range and use the start/end as default.

Users can then select data comparisons to view.

You can also limit the range via the Map setting as shown below

   .. image:: images/sentinel-2.jpg.png

You can also enable proxy of your Sentinel Hub url to protect the url.