CSS
=======


Quartz provides CSS overrides at map level.

To override the default CSS, enter your CSS into the Map CSS box on the map edit page.


   .. image:: images/CSS.png


Example: Image Sizing
--------------------------------

To change pop-up image sizing, you can use something like below.

.. code-block:: css

  .leaflet-popup-content > table img {width: 300px;}
  .leaflet-popup-content > img { width: 300px;}


Example: Modal Info Box
------------------------------------------

To change Modal Info Box, you can use something like below.

.. code-block:: css

  .modal-content {
  position: relative;
  display: flex;
  flex-direction: column;
  width: fit-content;
  pointer-events: auto;
  background-clip: padding-box;
  border-radius: 20px;
  outline: 0;
  background-color: cadetblue;
  color: #fff;
  }



There is no need to add "!important" to CSS elements as map.css is loaded last and has precendence.



  