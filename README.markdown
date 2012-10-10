## Overview

This is the first version of the autoloader. This autoloader can load classes from only one location (directory). Such a place can be a directory "Classes" or any other name, or just the class can be placed on top of the project.

This place (directory) is set in the class **Autoloader** in constant **PREFIX**, for example:

      const PREFIX = 'Classes/';  // for the directory "Classes"
      const PREFIX = '';          // for classes located at the top of the project

if **PREFIX** is not empty at the end of the directory must be specified slash "/"



## Usage

#### Example for the classes are placed in the directory "Classes":

*Autoloader.php*

      const PREFIX = 'Classes/';

*index.php*

      require './Autoloader.php';
      Autoloader::startAutoload();

#### Example for the classes are on top of the project:

*Autoloader.php*

      const PREFIX = '';

*index.php*

      require './Autoloader.php';
      Autoloader::startAutoload();
