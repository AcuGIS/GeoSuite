**********************
CSRF Whitlist
**********************

To whitelist your domain in GeoServer, you can use our included file.

This file is located under /scripts/csrf-whitelist.txt

.. code-block:: css
   :linenos:
   
   	    <context-param>
          <param-name>GEOSERVER_CSRF_WHITELIST</param-name>
          <param-value>yourdomain.com</param-value>
        </context-param>
      
 You must restart Tomcat for the changes to register.
 
  .. note:: Be sure to replace yourdomain.com with your own domain above.

