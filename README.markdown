## Overview

Autoloader can load classes from different locations (directories), but only one such location classes can be loaded without the "namespace". Other different places (catalogs of classes) have to use the namespace and also in any one location (directory) all classes have to a common initial namespace prefix. An example of such a common initial prefix are:

      \Zend\...
      \Doctrine\...

Places loading classes (directories) can be specified as a relative path or absolute path:

      'your/path/to/Classes'
      '/your/path/to/Doctrine'

## Usage

#### Example for the classes are placed in the directory "Classes" and "Doctrine":

      require './Autoloader.php';
      Autoloader::startAutoload( array('your/path/to/Classes','/your/path/to/Doctrine') );

#### Example for the classes are on top of the project:

      require './Autoloader.php';
      Autoloader::startAutoload('');
