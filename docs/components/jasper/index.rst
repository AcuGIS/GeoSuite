.. _jri-label:
.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
Jasper
**********************

.. contents:: Table of Contents

JasperReportsIntegration
========================

The information below pertains to the deployment of JasperReportsIntegration in JRI Publisher.  For full documentation of JasperReportsIntegration, please see http://www.opal-consulting.de/downloads/free_tools/JasperReportsIntegration/


File Locations
==============

On installation, the JRI files are saved to::

   /home/tomcat/apache-tomcat-v/jasper_reports
   
Here, you will find the following::

   /home/tomcat/apache-tomcat-v/jasper_reports/conf
   
   /home/tomcat/apache-tomcat-v/jasper_reports/schedules
   
   /home/tomcat/apache-tomcat-v/jasper_reports/reports
   
   /home/tomcat/apache-tomcat-v/jasper_reports/logs
   
** reports ** contains your Jasper report files.

** conf ** contains the application.properties file

** schedules ** contains the .sh files for the Scheduler


Gen Script
==========
The Report Scheduler script is located under /etc/init.d/gen_jri_report.sh and can be customized to suit and extend your requirements.

.. code-block:: bash
   :linenos:



	#!/bin/bash -e

	source /etc/environment

  	JRI_HOME="${CATALINA_HOME}/jasper_reports/"

  	#source the report environment
  	source "${JRI_HOME}/schedules/${1}_env.sh"

  	DONT_MAIL="${2}"

  	#set who is sending the mail
  	export EMAIL='root@localhost'
  	REPORT_FOLDER=$(dirname ${REP_ID})

  	#encode the / in report id
  	REP_ID=$(echo "${REP_ID}" | sed 's/\//%2F/g')

  	if [ "${OPT_PARAMS}" ]; then
  	OPT_PARAMS="&${OPT_PARAMS}"
  	fi

  	URL="http://localhost:8080/JasperReportsIntegration/report?_repName=${REP_ID}&_repFormat=${REP_FORMAT}&	_dataSource=${REP_DATASOURCE}&_outFilename=${REP_FILE}${OPT_PARAMS}"

  	TSTAMP=$(date '+%Y%m%d_%H%M%S')
  	REP_FILEPATH="${JRI_HOME}/reports/${REPORT_FOLDER}/${TSTAMP}_${REP_FILE}"

  	wget -O"${REP_FILEPATH}" "${URL}"
  	if [ $? -ne 0 ]; then
  	rm -f "${REP_FILEPATH}"
  	fi


JRI Module Files
================

On installation, the JRI Module files are saved to::

   /usr/libexec/webmin/jri_publisher (CentOS)
   /usr/share/webmin/jri_publisher (Ubuntu)
   
The JRI Module configuration files are located at /etc/webmin/jri_publisher::

   
   /etc/webmin/jri_publisher/config
   /etc/webmin/jri_publisher/openjdk_version_cache
   /etc/webmin/jri_publisher/oracle_version_cache
      
reports contains your Jasper report files.

conf contains the application.properties file

schedules contains the .sh files for the Scheduler


Version
=======

The JasperReportsIntegration version is the one selected while using the install Wizard.

Schedule Files
==============

Each schedule creates a numeric file under::

	/home/tomcat/apache-tomcat-version/jasper_reports/schedules

The file has the following structure:

.. code-block:: bash
   :linenos:

   REP_FORMAT=pdf
   REP_ID=NewReports/StateInfo
   OPT_PARAMS="StateID=51"
   REP_DATASOURCE="Demo DS"
   EMAIL_SUBJ="State Report"
   REP_FILE=State-Info.pdf
   EMAIL_BODY="Please find the State Reports attached."
   SCH_ID=12
   RECP_EMAIL=user@company.com

The above parameters are passed to the Jasper url as well as to MUTT for email delivery.


MUTT Parameters
===============
JRI Publisher uses MUTT in conjuction with Postfix to deliver reports via email.

The final input has the form::

	./etc/init.d/gen_jri_report.sh schedules.{1}
	
Where schedules.{1} is passed to gen_jri_report.sh
