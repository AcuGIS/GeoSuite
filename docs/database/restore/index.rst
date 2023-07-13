.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

******************
Restore Database
******************

.. contents:: Table of Contents

Restore
=============

To restore a snapshot, click the Restore tab as shown below.

      .. image:: _static/restore-tab.png

      
From here, select the database you wish to restore from the drop down.  Next, select the Snapshot you wish to use for the restore from the drop-down of available Snapshots as shown below and click the Restore button.  


      .. image:: _static/restore-panel.png
      
      
      
Snapshot Location
===================
      
Snapshots are saved to /opt/snapshots/

The Snapshots are taken in both sql and dump formats.

A a timestap is added in the format YYYY-MM-DD-HR-MM-database.  An example is shown below::

   /opt/2020-05-10-08-55_demodb.sql.gz




