<?php

/**
 * For PHP 5 >= 5.3.2
 * 
 * @author Wojciech Brüggemann <wojtek77@o2.pl>
 */
class Autoloader
{
    /**
     * if "true" and the name of the class used underscore then the underscore is replaced to DIRECTORY_SEPARATOR
     * work only if a class don't used namespace
     */
    const IS_UNDERSCORE_TO_PATH = false;
    
    const IS_DEBUG = false;
    
    static private $autoloader;
    
    
    /**
     * The function enabled automatic load classes
     * @param string|array|null $prefixAutoloader  one prefix (as string) or many prefixes (as array)
     * @param bool $isIncludePath   if you want to read paths from include_path
     */
    static public function startAutoload($prefixAutoloader='', $isIncludePath=false)
    {
        if (!isset(self::$autoloader))
            self::$autoloader = new self($prefixAutoloader, $isIncludePath);
    }
    
    
    
    /**
     * @var array   absolute paths to the places where classes are loaded
     */
    private $prefixesAutoloader = array();

    /**
     * @var array   class prefixes (as key) and absolute paths had loaded classes (as value)
     */
    private $prefixesPath;
    
    /**
     * @var array   pseudo class prefixes (as key) and absolute paths had loaded classes (as value)
     */
    private $prefixesPathPseudo;

    /**
     * @var array   missing scripts (paths) of classes - used by debug
     */
    private $missingScripts = array();

    /**
     * @var bool    determines whether the autoloader is the last autoloader in the SPL
     */
    private $isLastLoaderSPL;

    
    /**
     * @param string|array|null $prefixAutoloader  one prefix (as string) or many prefixes (as array)
     * @param bool $isIncludePath   if you want to read paths from include_path
     */
    public function __construct($prefixAutoloader='', $isIncludePath=false)
    {
        $prefixesAutoloader = (array) $prefixAutoloader;

        if ($isIncludePath)
        {
            $includePath = get_include_path();
            if ($includePath !== '')
            {
                foreach (explode(PATH_SEPARATOR, $includePath) as $p)
                {
                    if ($p !== '.') $prefixesAutoloader[] = $p;
                }
            }
        }
        
        //--------------------------

        foreach ($prefixesAutoloader as $prefixAutoloader)
        {
            $absolutePrefixAutoloader = stream_resolve_include_path($prefixAutoloader);
            if ($absolutePrefixAutoloader === false)
            {
                $this->throwWarning("The wrong prefix autoloader: <b>'$prefixAutoloader'</b>");
                continue;
            }
            
            $this->prefixesAutoloader[] = $absolutePrefixAutoloader.DIRECTORY_SEPARATOR;
        }

        spl_autoload_register(array($this, 'autoload'));
    }

    public function __destruct()
    {
        if (self::IS_DEBUG)
        {
            $this->showDebugInfo();
        }
    }

    /**
     * The function implements loading classes
     * @param string    $class  class name
     * @return bool     success
     */
    public function autoload($class)
    {
        $prefixClass = strstr($class, '\\', true);

        if ($prefixClass !== false && isset($this->prefixesPath[$prefixClass]))
        {
            $class = str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php';
            
            /*
             * means that before the autoloader was not able to load a class with this prefix
             * and the prefix is not supported by this autloader
             * and another foreign autloader have to load this class
             */
            if ($this->prefixesPath[$prefixClass] === false) return false;

            if ((@include $this->prefixesPath[$prefixClass].$class) !== false) return true;
        }
        else
        {
            $isNamespace = $prefixClass !== false;
            
            if ($isNamespace)
            {
                $class = str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php';
                
                foreach ($this->prefixesAutoloader as $prefixAutoloder)
                {
                    if ((@include $prefixAutoloder.$class) !== false)
                    {
                        $this->prefixesPath[$prefixClass] = $prefixAutoloder;
                        return true;
                    }

                    $this->missingScripts[$prefixAutoloder][] = $prefixAutoloder.$class;
                }
                
                $this->prefixesPath[$prefixClass] = false;
            }
            else
            {
                $prefixClassPseudo = strstr($class, '_', true);
                if ($prefixClassPseudo === false)
                {
                    preg_match('/.+?(?=[A-Z0-9]|$)/', $class, $w);
                    $prefixClassPseudo = $w[0];
                }
                
                if (self::IS_UNDERSCORE_TO_PATH)
                    $class = str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
                else
                    $class .= '.php';
                
                if (isset($this->prefixesPathPseudo[$prefixClassPseudo])
                        && (@include $this->prefixesPathPseudo[$prefixClassPseudo].$class) !== false)
                    return true;
                
                
                foreach ($this->prefixesAutoloader as $prefixAutoloder)
                {
                    if ((@include $prefixAutoloder.$class) !== false)
                    {
                        $this->prefixesPathPseudo[$prefixClassPseudo] = $prefixAutoloder;
                        return true;
                    }

                    $this->missingScripts[$prefixAutoloder][] = $prefixAutoloder.$class;
                }
            }
        }


        /* unloaded classes go here */

        if (!isset($this->isLastLoaderSPL))
        {
            $splLoaders = spl_autoload_functions();
            $lastSpl = end($splLoaders);

            if ($lastSpl[0] === $this) $this->warningWrongPath($class, $prefixClass);
            else $this->isLastLoaderSPL = false;
        }

        return false;
    }
    
    
    
    private function warningWrongPath($class, $prefixClass)
    {
        if ($prefixClass === false || $this->prefixesPath[$prefixClass] === false)
        {
            if (count($this->prefixesAutoloader) === 1)
            {
                $message = "The wrong path: <b>'".$this->prefixesAutoloader[0].$class."'</b>";
            }
            else
            {
                $paths = array();
                foreach ($this->prefixesAutoloader as $prefixAutoloader)
                {
                    $paths[] = "<b>'".$prefixAutoloader.$class."'</b>";
                }
                $message = "Wrong one path from: ".implode(', ', $paths);
            }
        }
        else
        {
            $message = "The wrong path: <b>'".$this->prefixesPath[$prefixClass].$class."'</b>";
        }
        
        $this->throwWarning($message);
    }
    
    private function throwWarning($message)
    {
        /* display warning without "Call Stack" */
        xdebug_disable();
        trigger_error("&nbsp;&nbsp;&nbsp;$message&nbsp;&nbsp;&nbsp;", E_USER_WARNING);
        xdebug_enable();
        
    }

    private function showDebugInfo()
    {
        echo '<pre>----------------------------------------------------------</pre>';
        echo 'OBJECT AUTOLOAD:';
        echo '<pre>'.print_r($this, true).'</pre>';

        echo '<pre>----------------------------------------------------------</pre>';
        echo 'MISSING SCRIPTS: <br /><br />';
        $i = 0;
        foreach ($this->missingScripts as $prefixAutoloder=> $missingScripts)
        {
            echo 'Prefix autoloader: \''.$prefixAutoloder.'\'<br />';
            foreach ($missingScripts as $m)
            {
                echo '<pre>'.print_r(++$i.'. '.$m, true).'</pre>';
            }
        }
    }
}
