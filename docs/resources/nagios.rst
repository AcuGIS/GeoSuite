Nagios
------

If you wish to set up monitoring for your instance, you can do so with our Nagios Module:

https://github.com/cited/nagios-webmin-module

Nagios and nrpe client are produced by `Nagios`_

.. _`Nagios`: https://www.nagios.com/

`Nagios Exchange`_ provides plugins, addons, docs, extensions and other tools

.. _`Nagios Exchange`: https://exchange.nagios.org/

Below are some of the main sections for setting up basic monitoring.

nagiso.cfg to register our geosuite.cfg file

.. code-block:: console

    # You can specify individual object config files as shown below:
    cfg_file=/usr/local/nagios/etc/objects/commands.cfg
       
    # Definitions for monitoring the local (Linux) host
    cfg_file=/usr/local/nagios/etc/objects/geosuite.cfg


commands.cfg

.. code-block:: console

    # Monitor GeoServer: Monitor web page in addition to Tomcat to insure GeoServer is accessible

    define command {
        command_name   geoserver_URL
        command_line   /usr/local/nagios/libexec/check_http_content -U "http://domain.com/geoserver/web/" -m "Welcome"

    }

    # Monitor PostgreSQL using the check_postgres.pl plugin:

    define command {
         command_name    check_postgres_connection
         command_line    /usr/local/nagios/libexec/check_postgres.pl  --dbservice=$HOSTADDRESS$ --action=connection
     }

    # Monitor disk usage using custom script, check_diskspace (script is below)
    
    define command {
        command_name    check_diskspace
        command_line    $USER1$/check_nrpe -H $HOSTADDRESS$ -c check_diskspace




geosuite.cfg file

.. code-block:: console

    # Using Slack plugin to get notifications on Slack channel
    # Using check_diskspace (see next section)

    define host {
        use                          linux-server
        host_name                    geosuite
        alias                        geosuite
        address                      192.1.2.3
        register                     1
    }

    define service {
      host_name                       geosuite
      service_description             PING
      check_command                   check_ping!100.0,20%!500.0,60%
      max_check_attempts              2
      check_interval                  2
      retry_interval                  2
      check_period                    24x7
      check_freshness                 1
      contact_groups                  admins,slack
      notification_interval           2
      notification_period             24x7
      notifications_enabled           1
      register                        1
    }

    define service {
      host_name                       geosuite
      service_description             Disk Space
      check_command                   check_diskspace
      max_check_attempts              2
      check_interval                  2
      retry_interval                  2
      check_period                    24x7
      check_freshness                 1
      contact_groups                  admins,slack
      notification_interval           2
      notification_period             24x7
      notifications_enabled           1
      register                        1
    }

    define service {
      host_name                       geosuite
      service_description             Check SSH
      check_command                   check_ssh!-p 48316
      max_check_attempts              2
      check_interval                  2
      retry_interval                  2
      check_period                    24x7
      check_freshness                 1
      contact_groups                  admins,slack
      notification_interval           2
      notification_period             24x7
      notifications_enabled           1
      register                        1
    }

    define service {
      host_name                       geosuite
      service_description             Check HTTP
      check_command                   check_http
      max_check_attempts              2
      check_interval                  2
      retry_interval                  2
      check_period                    24x7
      check_freshness                 1
      contact_groups                  admins,slack
      notification_interval           2
      notification_period             24x7
      notifications_enabled           1
      register                        1
    }

    define service {
      host_name                       geosuite
      service_description             Check PostgreSQL
      check_command                   check_postgres_connection
      max_check_attempts              2
      check_interval                  2
      retry_interval                  2
      check_period                    24x7
      check_freshness                 1
      contact_groups                  admins,slack
      notification_interval           2
      notification_period             24x7
      notifications_enabled           1
      register                        1
    }


    define service {
      host_name                       geosuite
      service_description             GeoServer Status
      check_command                   geoserver_URL
      max_check_attempts              2
      check_interval                  2
      retry_interval                  2
      check_period                    24x7
      check_freshness                 1
      contact_groups                  admins,slackmins
      notification_interval           2
      notification_period             24x7
      notifications_enabled           1
      register                        1
    }


You can find the PostgreSQL monitor plugin on Nagios Exchange.

For disk usage monitoring, you can use our check_diskspace script below:

.. code-block:: console


    #!/bin/bash

    chkuse=$(df -h |grep '/' |grep -v 'VolGroup' |awk '{if ($6 == "") print $4,$5; else print $5,$6;}')

    echo "${chkuse}" | grep -o '[0-9]*' | while read mounts
    do
        if [ $mounts -gt 98 ]
          then
          echocrit=$(echo "${chkuse}" | grep $mounts)
          echo $crit "CRITICAL"
          exit 2
        elif [ $mounts -gt 95 ]
          then
          echowarn=$(echo "${chkuse}" | grep $mounts)
          echo $warn "WARNING"
          exit 1
        fi
    done

    wait
    rc=$?

    if [ $rc -eq 0 ]; then
        echo "OK"
    fi

    exit ${rc}


