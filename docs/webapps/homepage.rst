.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
Home Page
**********************

.. contents:: Table of Contents

Web App
========

A simple Boostrap web application is installed to /var/www/html during the set up Wizard.

The web application contains links to the Login page, GeoServer, OpenLayers Demo, LeafletJS Demo, and Docs.

.. image:: _static/GeoHelm-Main.png

https://yourdomain.com/LeafletDemo.html


Structure and Code
==================

.. code-block:: HTML
   :linenos:
   
   
   	<!DOCTYPE html>
	<html lang="en">
  	<head>
    	<!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="keywords" content="Bootstrap, Landing page, Template, Business, Service">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="author" content="AcuGIS">
    <title>AcuGIS - AcuGIS GeoHelm</title>
    <!--====== Favicon Icon ======-->
    <link rel="shortcut icon" href="img/2.png" type="image/png">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/animate.css">
    <link rel="stylesheet" href="css/LineIcons.css">
   
    <link rel="stylesheet" href="css/main.css">    
    <link rel="stylesheet" href="css/responsive.css">

  </head>
  
  <body>

    <!-- Header Section Start -->
    <header id="home" class="hero-area">    
      
      <nav class="navbar navbar-expand-md bg-inverse fixed-top scrolling-navbar">
        <div class="container">
          <a href="index.html" class="navbar-brand"><img src="img/logo.png" alt=""></a>       
          <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <i class="lni-menu"></i>
          </button>
          <div class="collapse navbar-collapse" id="navbarCollapse">
            <ul class="navbar-nav mr-auto w-100 justify-content-end">
              <li class="nav-item">
                <a class="nav-link page-scroll" href="#features">Home</a>
              </li>
              <li class="nav-item">
                <a class="nav-link page-scroll" href="https://acugis.com/acugis-suite/docs" target="_blank">Docs</a>
              </li>  
                                         
              
              <li class="nav-item">
                <a class="btn btn-singin" href=":10000">LOGIN</a>
              </li>
            </ul>
          </div>
        </div>
      </nav>  
      
    </header>
    <!-- Header Section End --> 
    <section id="features" class="section">
      <div class="container">
        <!-- Start Row -->
        <div class="row">
          <div class="col-lg-12">
            </div>
        </div>
        <!-- End Row -->
        <!-- Start Row -->
        <div class="row featured-bg">
         <!-- Start Col -->
          <div class="col-lg-6 col-md-6 col-xs-12 p-0">
             <!-- Start Fetatures -->
            <div class="feature-item featured-border1">
               <div class="feature-icon float-left">
                 <i class="lni-laptop"></i>
               </div>
               <div class="feature-info float-left">
                 <h4>Get Started</h4>
                 <p>Your GeoHelm instance is now ready for use. <br>You can remove this application at any time.</p>
               </div>
            </div>
            <!-- End Fetatures -->
          </div>
           <!-- End Col -->
          <!-- Start Col -->
          <div class="col-lg-6 col-md-6 col-xs-12 p-0">
             <!-- Start Fetatures -->
            <div class="feature-item featured-border2" onclick="location.href='/geoserver';" style="cursor: pointer;">
               <div class="feature-icon float-left">
                 <i class="lni-map"></i>
               </div>
               <div class="feature-info float-left">
                 <h4>GeoServer</h4>
                 <p>Your GeoServer instance <br> can now be accessed.</p>
               </div>
            </div>
            <!-- End Fetatures -->
          </div>
           <!-- End Col -->
          <!-- Start Col -->
          <div class="col-lg-6 col-md-6 col-xs-12 p-0">
             <!-- Start Fetatures -->
            <div class="feature-item featured-border1" onclick="location.href='/OpenLayersDemo.html';" style="cursor: pointer;">
               <div class="feature-icon float-left">
                 <i class="lni-layers"></i>
               </div>
               <div class="feature-info float-left">
                 <h4>OpenLayers Demo</h4>
                 <p>We have created an OpenLayers demo for you. <br> This example uses your GeoServer instance.</p>
               </div>
            </div>
            <!-- End Fetatures -->
          </div>
           <!-- End Col -->
          <!-- Start Col -->
          <div class="col-lg-6 col-md-6 col-xs-12 p-0">
             <!-- Start Fetatures -->
            <div class="feature-item featured-border2" onclick="location.href='/LeafletJSDemo.html';" style="cursor: pointer;">
               <div class="feature-icon float-left">
                <i class="lni-leaf"></i>
               </div>
               <div class="feature-info float-left">
                 <h4>Leaflet Demo</h4>
                 <p>We have created a Leafelt demo for you. <br> This example uses your GeoServer instance.</p>
               </div>
            </div>
            <!-- End Fetatures -->
          </div>
           <!-- End Col -->
          
         	<!-- Start Col -->
          <div class="col-lg-6 col-md-6 col-xs-12 p-0">
             <!-- Start Fetatures -->
            <div class="feature-item featured-border3" onclick="location.href=':10000';" style="cursor: pointer;">
               <div class="feature-icon float-left">
                 <i class="lni-control-panel"></i>
               </div>
               <div class="feature-info float-left">
                 <h4>Suite Login</h4>
                 <p>You can access your AcuGIS Suite control panel <br> here or via the Login button at top.</p>
               </div>
            </div>
            <!-- End Fetatures -->
          </div>
           <!-- End Col -->
          
         <!-- Start Col -->
         	 <div class="col-lg-6 col-md-6 col-xs-12 p-0" onclick="location.href='https://www.acugis.com/acugis-suite/docs';" style="cursor: pointer;">
             <!-- Start Fetatures -->
            <div class="feature-item featured-border2" onclick="location.href='https://www.acugis.com/acugis-suite/docs';" style="cursor: pointer;">
               <div class="feature-icon float-left">
                 <i class="lni-graduation"></i>
               </div>
               <div class="feature-info float-left">
                 <h4>Docs</h4>
                 <p>GeoHelm documentation and tutorials.</p>
               </div>
	            </div>
            <!-- End Fetatures -->
          </div>
           <!-- End Col -->
        </div>
        <!-- End Row -->
      </div>
    </section>
    <footer>
      <!-- Footer Area Start -->
      <section id="footer-Content">
               
        <!-- Copyright Start  -->

        <div class="copyright">
          <div class="container">
            <!-- Star Row -->
            <div class="row">
              <div class="col-md-12">
                <div class="site-info text-center">
                  <p>Copyright 2019 <a href="https://citedcorp.com" rel="nofollow">Cited, Inc.</a></p>
                </div>              
                
              </div>
              <!-- End Col -->
          
            <!-- End Row -->
      
        </div>
      <!-- Copyright End -->
     </div> </section>
      <!-- Footer area End -->
      
    </footer>
    <!-- Footer Section End --> 


    <!-- Go To Top Link -->
    <a href="#" class="back-to-top">
      <i class="lni-chevron-up"></i>
    </a> 

    <!-- Preloader -->
    <div id="preloader">
      <div class="loader" id="loader-1"></div>
    </div>
    <!-- End Preloader -->

    <!-- jQuery first, then Tether, then Bootstrap JS. -->
    <script src="js/jquery-min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/owl.carousel.js"></script>      
    <script src="js/jquery.nav.js"></script>    
    <script src="js/scrolling-nav.js"></script>    
    <script src="js/jquery.easing.min.js"></script>     
    <script src="js/nivo-lightbox.js"></script>     
    <script src="js/jquery.magnific-popup.min.js"></script>      
    <script src="js/main.js"></script>
    
  	</body>
	</html>
	

Troubleshooting
===============

If links on the home page do not function properly, check the index.html page to verify that links are pointing to your IP or hostname.




