<?php

/**
 * Dla PHP 5.3 i wyzej
 * 
 * Klasa tworzaca automatyczne ladowanie klas
 * wg zasady, klasa umieszczona w katalogu musi byc w namespace o tej samej nazwie co katalog
 * np. klasa umieszczona w katalogu "xxx" musi byc w namespace "xxx"
 * 
 * @author Wojciech Brüggemann <wojtek77@o2.pl>
 */
class Autoloader
{
    /**
     * Stala okresla sciezke od korzenia projektu do katalogu gdzie ma byc ladowanie klas
     * domyslnie '', katalog nalezy podawac dodajac na koncu slash np. 'classes/'
     * 
     * np. wartosc '' (pusty string) ozn. ze wszystkie klasy ktore maja byc automatycznie ladowane sa umieszczone
     * na samej gorze projektu a wartosc 'classes/' ozn. ze wszystkie klasy do ladowanie sa umieszczone tylko w katalogu 'classes/'
     */
    const PREFIX = '';
    
    /**
     * Tablica, gdzie klucze to prefiksy, ktore maja byc blokowane np."Doctrine",
     * wartosc jest wyliczana automatycznie, ale mozna ja wpisac recznie
     * @var array   
     */
    static private $stopAutoload = array();
    
    static private $stop;
    static private $prefixClass;
    
    
    
    /**
     * Funkcja wlaczajaca automatyczne ladowanie klas
     */
    static public function startAutoload()
    {
        spl_autoload_register(array('self', 'autoloadMaster'));
        spl_autoload_register(array('self', 'autoloadSlave'));
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
    
    
    
    // PRIVATE
    
    /**
     * Funkcja realizujaca ladowanie klas
     * @param string $class
     */
    static private function autoloadMaster($class)
    {
        self::$prefixClass = self::prefixClass($class);
        self::$stop = isset(self::$prefixClass) && isset(self::$stopAutoload[self::$prefixClass]);
        if (self::$stop)
        {
            return;
        }
        
        //------------------
        
        /* sciezka od skryptu PHP do korzenia projektu np. '../', '' */
        static $prefixFrom;
        
        $class = str_replace('\\', '/', $class).'.php';
        
        if (isset($prefixFrom))
        {
            @include ($prefixFrom.self::PREFIX.$class);
        }
        else
        {
            for ($i = self::scriptLevel(); $i >= 0; --$i)
            {
                $prefixFrom = ($i > 0) ? str_repeat('../', $i) : './';
                $path = $prefixFrom.self::PREFIX.$class;
                if (file_exists($path))
                {
                    require $path;
                    return;
                }
            }
            $prefixFrom = null;
        }
    }
    
    /**
     * Funkcja pomocnicza dla glownej funkcji do ladowania klas
     * @param string $class
     */
    static private function autoloadSlave($class)
    {
        if (self::$stop)
        {
            return;
        }
        
        if (isset(self::$prefixClass))
            self::$stopAutoload[self::$prefixClass] = true;
    }
    
    
    
    /**
     * Funkcja oblicza poziom zagniezdzenia skryptu PHP
     * @return int
     */
    static private function scriptLevel()
    {
        $dir = dirname(getenv('SCRIPT_NAME'));
        
        return
            ($dir === '/' || $dir === '\\')
                ?   0
                :   substr_count($dir, '/');
    }
    
    /**
     * Funkcja oblicza prefiks dla wywolywanej klasy np. dla Zend_Db_Table prefiksem bedzie "Zend"
     * a dla wywolania test\test2\Class prefiksem bedzie "test"
     * @param string $class
     * @return string-null  w przypadku niepowodzenia zwracany jest NULL
     */
    static private function prefixClass($class)
    {
        $prefix = strstr($class, '\\', true);
        return
            ($prefix !== false) ? $prefix : null;
    }
}
