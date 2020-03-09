.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
Report Dashboard
**********************

.. contents:: Table of Contents
Overview
==================

Click the Reports tab to open the Reports Dashboard.

.. image:: _static/reports-tab.png

This will open the screen below.  

.. image:: _static/report-dashboard.png

As we can see above, the creation of our NewReports Directory has been added to the directory structure.  This is true for all directories and sub directories added.

Dashboard Layout
================

Expanding the NewReports directory, we see below:

.. image:: _static/report-dashboard-item.png


Dashboard Functions
===================

**Name**::

 Clicking on the report name will open the .jrxml file for editing, as shown below:
 
.. image:: _static/reports-edit-jrxml.png
 
 
**Actions**::
      
Run:  Runs the report on demand.

.. image:: _static/reports-actions.gif


Clean: Opens a new window to delete any reports you may wish to delete

.. image:: _static/reports-cleaner.png 	

Download:  Opens a new window to download selected report(s) in .zip or .bgz format.

.. image:: _static/reports-downloader.png 	


**SchID**::

Link to edit the Schedule for the report

**Cron**::

Displays the cron in use for the Schedule
 
**Format**::

Displays the report format (e.g. pdf, csv, etc...)

**Data Source**::

Displays report Data Source

**Output**::

Clicking the Browse button will open the report directory in the File Manager as shown below:

.. image:: _static/reports-browse.png


**Email**::

Displays report email recipient(s).

**Optional Params**::

Displays any URL Parameters the report is using.


No Schedule
===========

.. note::
    Any report that does not have a schedule will show the Scheduler icon in the Actions menu.  To add a Schedule, click the icon as shown below.

 
.. image:: _static/reports-no-schedule.png

   

