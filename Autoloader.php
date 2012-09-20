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
     * @var string  nazwa klasy 
     */
    static private $class;
    
    /**
     * @var string  prefiks wyliczany dla wywolywanej klasy przez funkcje "self::prefixClass" np: "Doctrine"
     */
    static private $prefixClass;
    
    /**
     * @var string  sciezka od skryptu PHP do korzenia projektu - wyliczana automatycznie
     */
    static private $prefixFrom;
    
    /**
     * Uzywane do debugowania
     * @var int licznik dla blednych sciezek do class
     */
    static private $missingCounter = 0;
    
    /**
     * Uzywane przy bledzie ladowania klasy - pokazywane sa bledne sciezki do ladowania klas 
     * @var array   tablica obiektow klasy Autoloader w porzadku jak zostaly zaladowane
     */
    static private $loaders = array();
    
    
    
    /**
     * Funkcja wlaczajaca automatyczne ladowanie klas
     * @param string-array $prefix  prefiks lub prefiksy
     * @see self::$prefix
     * @param bool $isIncludePath   czy maja byc czytane sciezki z include_path
     */
    static public function startAutoload($prefix = '', $isIncludePath = false)
    {
        $prefixes = (array) $prefix;
        
        if ($isIncludePath)
        {
            $includePath = get_include_path();
            if ($includePath !== '')
            {
                foreach (explode(PATH_SEPARATOR, $includePath) as $p)
                {
                    if ($p !== '.')
                        $prefixes[] = $p;
                }
            }
        }
        
        //--------------------------
        
        foreach ($prefixes as $prefix)
        {
            new self($prefix);
        }
    }
    
    /**
     * Funkcja dodaje nowa funkcje do automatycznego ladowania klas, np. z Doctrine
     * @param callable $autoload_function
     * @param bool $throw
     * @param bool $prepend
     * @return bool
     */
    static public function spl_autoload_register($autoload_function, $throw = true, $prepend = false)
    {
        return  spl_autoload_register($autoload_function, $throw, $prepend);
    }
    
    
    
    /**
     * Funkcja oblicza prefiks dla wywolywanej klasy np. dla Zend_Db_Table prefiksem bedzie "Zend"
     * a dla wywolania test\test2\Class prefiksem bedzie "test"
     * 
     * Ta funkcja powinna zostac dostosowana do indywidualnych potrzeb
     * 
     * @param string $class
     * @return string   prefiks klasy
     */
    static protected function prefixClass($class)
    {
        return  strstr($class, '\\', true);
        
        /* kod uwzgledniajacy prefiks w Zend */
//        $prefix = strstr($class, '\\', true);
//        if ($prefix === false)
//        {
//            $prefix = strstr($class, '_', true);
//        }
//        return $prefix;
    }
    
    
    
    
    
    /**
     * Okresla sciezke od korzenia projektu do katalogu gdzie ma byc ladowanie klas - domyslnie '',
     * 
     * wartosc '' (pusty string) ozn. ze wszystkie klasy ktore maja byc automatycznie ladowane sa umieszczone
     * na samej gorze projektu a wartosc 'classes/' ozn. ze wszystkie klasy do ladowania sa umieszczone
     * tylko w katalogu 'classes/'
     * 
     * @var string
     */
    private $prefix;
    
    /**
     * Tablica, gdzie klucze to prefiksy, ktore maja byc blokowane np."Doctrine",
     * wartosc jest wyliczana automatycznie, ale mozna ja wpisac recznie
     * @var array
     */
    private $stopAutoload = array();
    
    /**
     * Tablica przechowujaca bledne sciezki do class - uzywane do debugowania
     * @var array
     */
    private $missingScripts = array();
    
    /**
     * Okresla czy dany obiekt jest ostatnim autoloaderem w SPL
     * @var bool
     */
    private $isLastLoaderSPL;
    
    
    
    /**
     * @param string $prefix
     * @see self::$prefix
     */
    public function __construct($prefix = '')
    {
        $prefix = (string) $prefix;
        
        if ($prefix !== '')
        {
            if ($this->isAbsolutePath($prefix))
                $prefix = $this->createRelativePath($prefix);
            if (substr($prefix, -1) !== '/')
                $prefix .= '/';
        }
        $this->prefix = $prefix;
        self::$loaders[] = $this;
        
        spl_autoload_register(array($this, 'autoload'));
    }
    
    public function __destruct()
    {
        if (self::IS_DEBUG)
        {
            echo 'Prefix class: \''.$this->prefix.'\'<br />';
            foreach ($this->missingScripts as $m)
            {
                echo '<pre>'.print_r(++self::$missingCounter.'. '.$m,true).'</pre>';
            }
        }
    }
    
    
    /**
     * Funkcja realizujaca ladowanie klas
     * @param string $class
     */
    public function autoload($class)
    {
        if (self::$class !== $class)
        {
            self::$class = $class;
            self::$prefixClass = self::prefixClass($class);
        }
        
        if (isset($this->stopAutoload[self::$prefixClass]))
        {
            return;
        }
        
        
        $class = str_replace('\\', '/', $class).'.php';
        
        if (isset(self::$prefixFrom))
        {
            if ((@include self::$prefixFrom.$this->prefix.$class) !== false) return;
        }
        else
        {
            /*
             * obliczenie "self::$prefixFrom" - to jest robione tylko raz
             * oblicza sciezka od skryptu PHP do korzenia projektu np. './', '../', '../../'
             */
            for ($i = $this->scriptLevel(); $i >= 0; --$i)
            {
                $prefixFrom = ($i > 0) ? str_repeat('../', $i) : './';
                $path = $prefixFrom.$this->prefix.$class;
                if (file_exists($path))
                {
                    require $path;
                    self::$prefixFrom = $prefixFrom;
                    return;
                }
            }
        }
        
        $this->stopAutoload[self::$prefixClass] = true;
        $this->missingScripts[] = self::$prefixFrom.$this->prefix.$class;
        if (!isset($this->isLastLoaderSPL))
        {
            if (end(self::$loaders) === $this)
            {
                $splLoaders = spl_autoload_functions();
                $lastSpl = end($splLoaders);
                if ($lastSpl[0] === $this)
                {
                    $this->warningWrongPath($class);
                    return;
                }
            }
            
            $this->isLastLoaderSPL = false;
        }
    }
    
    
    /**
     * Funkcja oblicza poziom zagniezdzenia skryptu PHP
     * @return int
     */
    private function scriptLevel()
    {
        $dir = dirname(getenv('SCRIPT_NAME'));
        
        return
            ($dir === '/' || $dir === '\\')
                ?   0
                :   substr_count($dir, '/');
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
       while($i<$end  && $arrP[$i]===$arrPath[$i]) ++$i;

       $p = implode('/', array_slice($arrP, $i));
       $path = implode('/', array_slice($arrPath, $i));

       return
           ($p === '' ? '' : str_repeat('../', substr_count($p, '/')+1))
           .$path;
   }
   
   /**
    * Funkcja pokazuje warning na temat niezaladowanych sciezek do klas
    * @param string $class
    */
   private function warningWrongPath($class)
   {
       foreach (self::$loaders as $l)
       {
           /* @var $l Autoloader */
           trigger_error(
                   "Wrong path to class: '".self::$prefixFrom.$l->prefix.$class."'",
                   E_USER_WARNING
                   );
       }
   }
}
