.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
Security
**********************

As with JasperReportsIntegration, JRI Publisher should be run behind a firewall with access to port 8080 and 1521 restriced to the database server.

You should also change the Webmin port, as well as disable root access via SSH.

.. note::
    All Tomcat and JRI services are owned and run by user tomcat with minimal privilages. 
