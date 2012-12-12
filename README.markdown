## Overview

Autoloader can load classes from different locations (directories) even if many of them don't use namespace. This additional functionality is at the cost of reduced performance.

Autoloader can also load class where underscore is converted to a slash (eg Zend_Acl), but you need to set a constant in the class:

*Autoloader.php*

      const IS_UNDERSCORE_TO_PATH = true;

Places loading classes (directories) can be specified as a relative path or absolute path:

      'your/path/to/Classes'
      '/your/path/to/Doctrine'

## Usage

#### Example for classes which are placed in the directory "Classes" and "Doctrine":

      require './Autoloader.php';
      Autoloader::startAutoload( array('your/path/to/Classes','/your/path/to/Doctrine') );

#### Example for classes which are in the same place where the program is run:

      require './Autoloader.php';
      Autoloader::startAutoload('');
