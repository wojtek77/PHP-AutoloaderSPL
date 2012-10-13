<?php

/**
 * 
 * Uzycie (usage):
 * Autoloader::startAutoload();
 * 
 * Dla PHP 5.3 i wyzej (for PHP 5.3 and higher)
 * 
 * Uwaga! Autoloader jest w stanie utworzyc wiele funkcji autoload ale tylko jedna z tych funkcji
 * moze obslugiwac przestrzen globalna, pozostale musza byc przykryte przez "prefixClass"
 * (Note! Autoloader is able to create multiple autoload functions but only one of these functions
 * can support the global space, the rest must be covered by the "prefixClass")
 * 
 * Klasa tworzaca automatyczne ladowanie klas
 * wg zasady, klasa umieszczona w katalogu musi byc w namespace o tej samej nazwie co katalog
 * np. klasa umieszczona w katalogu "xxx" musi byc w namespace "xxx"
 * (Class creates automatic loading of classes
 * according to the rules, the class placed in the directory must be in a namespace with the same name as the catalog
 * of such class placed in the "xxx" must be in the namespace "xxx")
 * 
 * @author Wojciech Brüggemann <wojtek77@o2.pl>
 */
class Autoloader
{
    /**
     * wlacza debugowanie
     */
    const IS_DEBUG = false;
    
    
    
    /**
     * Funkcja wlaczajaca automatyczne ladowanie klas
     * @param string-array $prefixAutoloader  prefiks lub prefiksy
     * @param bool $isIncludePath   czy maja byc czytane sciezki z include_path
     */
    static public function startAutoload($prefixAutoloader = '', $isIncludePath = false)
    {
        new self($prefixAutoloader, $isIncludePath);
    }
    
    
    
    /**
     * @var array   tablica sciezek dla autoloader (od korzenia projektu do miejsca ladowania klas)
     */
    private $prefixesAutoloader;

    /**
     * @var array   tablica sciezek dla prefix-class (od skryptu PHP do miejsca ladowania klas)
     */
    private $prefixesPath;

    /**
     * @var string  od skryptu PHP do korzenia projektu 
     */
    private $prefixFrom;

    /**
     * @var array   tablica przechowujaca bledne sciezki do class - uzywane do debugowania
     */
    private $missingScripts = array();

    /**
     * @var bool    okresla czy ten autoloader jest ostatnim autoloaderem w SPL
     */
    private $isLastLoaderSPL;
    
    
    
    /**
     * @param string-array $prefixAutoloader  prefiks lub prefiksy
     * @param bool $isIncludePath   czy maja byc czytane sciezki z include_path
     */
    private function __construct($prefixAutoloader, $isIncludePath)
    {
        $prefixesAutoloader = (array) $prefixAutoloader;

        if ($isIncludePath)
        {
            $includePath = get_include_path();
            if ($includePath !== '')
            {
                foreach (explode(PATH_SEPARATOR, $includePath) as $p)
                {
                    if ($p !== '.')
                        $prefixesAutoloader[] = $p;
                }
            }
        }

        //--------------------------

        foreach ($prefixesAutoloader as $prefixAutoloader)
        {
            $prefixAutoloader = (string) $prefixAutoloader;

            if ($prefixAutoloader !== '')
            {
                if ($this->isAbsolutePath($prefixAutoloader))
                    $prefixAutoloader = $this->createRelativePath($prefixAutoloader);
                if (substr($prefixAutoloader, -1) !== '/')
                    $prefixAutoloader .= '/';
            }

            $this->prefixesAutoloader[] = $prefixAutoloader;
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
     * Funkcja realizujaca ladowanie klas
     * @param string    $class  nazwa klasy
     * @return bool     czy sukces
     */
    public function autoload($class)
    {
        $prefixClass = strstr($class, '\\', true);

        $class = str_replace('\\', '/', $class) . '.php';

        if (isset($this->prefixesPath[$prefixClass]))
        {
            /*
             * oznacza ze ten autoloader wczesniej nie byl stanie zaladowac klasy z tego prefiksu
             * co oznacza, ze ten prefiks nie jest obslugiwany przed ten autloader
             * i jakis inny obcy autloader bedzie musial zaladowac ta klase
             */
            if ($this->prefixesPath[$prefixClass] === false)
                return false;

            if ((@include $this->prefixesPath[$prefixClass] . $class) !== false)
                return true;
        }
        else
        {
            foreach ($this->prefixesAutoloader as $prefixAutoloder)
            {
                if (isset($this->prefixFrom))
                {
                    if ((@include $this->prefixFrom . $prefixAutoloder . $class) !== false)
                    {
                        $this->prefixesPath[$prefixClass] = $this->prefixFrom . $prefixAutoloder;
                        return true;
                    }
                }
                else
                {
                    /*
                     * obliczenie "$this->prefixFrom" - to jest robione tylko raz
                     * oblicza sciezke od skryptu PHP do korzenia projektu np. './', '../', '../../'
                     */
                    for ($i = $this->scriptLevel(); $i >= 0; --$i)
                    {
                        $prefixFrom = ($i > 0) ? str_repeat('../', $i) : './';
                        $path = $prefixFrom . $prefixAutoloder . $class;
                        if (file_exists($path))
                        {
                            require $path;
                            $this->prefixFrom = $prefixFrom;
                            $this->prefixesPath[$prefixClass] = $prefixFrom . $prefixAutoloder;

                            return true;
                        }
                    }
                }

                $this->missingScripts[$prefixAutoloder][] = $this->prefixFrom . $prefixAutoloder . $class;
            }
            
            $this->prefixesPath[$prefixClass] = false;
        }


        /* tu trafiaja niezaladowne klasy */

        if (!isset($this->isLastLoaderSPL))
        {
            $splLoaders = spl_autoload_functions();
            $lastSpl = end($splLoaders);

            if ($lastSpl[0] === $this)
                $this->warningWrongPath($class, $prefixClass);
            else
                $this->isLastLoaderSPL = false;
        }

        return false;
    }
    
    /**
     * Funkcja oblicza poziom zagniezdzenia skryptu PHP
     * @return int
     */
    private function scriptLevel()
    {
        $dir = dirname(getenv('SCRIPT_NAME'));

        return
                ($dir === '/' || $dir === '\\') ? 0 : substr_count($dir, '/');
    }
    
    /**
     * Funkcja sprawdza czy sciezka jest bezwgledna
     * @param string $path
     * @return bool
     */
    private function isAbsolutePath($path)
    {
        $p = __DIR__;
        if ($p{0} === '/')  // Linux
        {
            return $path{0} === '/';
        }
        else                // Windows
        {
            return (bool) preg_match('/^[c-z]:/i', $path);
        }
    }
    
    /**
     * Funkcja tworzy sciezke wzgledna od miejsca wykonywania skryptu do miejsca wskazanego przez sciezke bezwgledna
     * @param string $path  sciezka bezwgledna
     * @return string   sciezka wzgledna
     * @throws Exception
     */
    private function createRelativePath($path)
    {
        $p = realpath($path);
        if ($p === false)
        {
            throw new Exception("path: $path does not exist");
        }

        $path = $p;
        $p = __DIR__;

        if ($path{0} === '/')  // Linux
        {
            $arrP = explode('/', $p);
            $arrPath = explode('/', $path);
        }
        else                    // Windows
        {
            $arrP = explode('\\', $p);
            $arrPath = explode('\\', $path);
        }

        $i = 0;
        $end = min(count($arrP), count($arrPath));
        while ($i < $end && $arrP[$i] === $arrPath[$i])
            ++$i;

        $p = implode('/', array_slice($arrP, $i));
        $path = implode('/', array_slice($arrPath, $i));

        return
                ($p === '' ? '' : str_repeat('../', substr_count($p, '/') + 1))
                . $path;
    }
    
    /**
     * Funkcja pokazuje warning na temat niezaladowanych sciezek do klas
     * @param string $class
     * @param mixed $prefixClass
     */
    private function warningWrongPath($class, $prefixClass)
    {
        if ($this->prefixesPath[$prefixClass] === false)
        {
            if (count($this->prefixesAutoloader) === 1)
            {
                $message = "The wrong path: <b>'" . $this->prefixFrom . $this->prefixesAutoloader[0] . $class . "'</b>";
            }
            else
            {
                $paths = array();
                foreach ($this->prefixesAutoloader as $prefixAutoloader)
                {
                    $paths[] = "<b>'" . $this->prefixFrom . $prefixAutoloader . $class . "'</b>";
                }
                $message = "Wrong one path from: " . implode(', ', $paths);
            }
        }
        else
        {
            $message = "The wrong path: <b>'" . $this->prefixesPath[$prefixClass] . $class . "'</b>";
        }
        
        /* wyswietlenie wiadomosci bez "Call Stack" */
        xdebug_disable();
        trigger_error($message, E_USER_WARNING);
        xdebug_enable();
    }
    
    /**
     * Funkcja pokazuje informacje z debugowania
     */
    private function showDebugInfo()
    {
        echo '<pre>----------------------------------------------------------</pre>';
        echo 'OBJECT AUTOLOAD:';
        echo '<pre>' . print_r($this, true) . '</pre>';
        
        echo '<pre>----------------------------------------------------------</pre>';
        echo 'MISSING SCRIPTS: <br /><br />';
        $i = 0;
        foreach ($this->missingScripts as $prefixAutoloder => $missingScripts)
        {
            echo 'Prefix autoloader: \'' . $prefixAutoloder . '\'<br />';
            foreach ($missingScripts as $m)
            {
                echo '<pre>' . print_r(++$i . '. ' . $m, true) . '</pre>';
            }
        }
    }
}
