<?php

namespace PhpJs;

class PhpJs {

    /**
     * @var V8Js A v8js object, held in cache
     */
    protected static $v8;
    /**
     * @var array A cache for JS files
     */
    protected static $file_cache = array();
    /**
     * @var array The list of things we want V8Js to execute
     */
    protected $commands = array();

    public function __construct() {
        if (!isset(self::$v8)) {
            self::$v8 = new V8Js();
        }
        $this->commands = $this->getDefaultArgs();
    }

    /**
     * Adds inline JS to the current stream of commands
     *
     * @param string $inline_js
     */
    public function addInline($inline_js) {
        $this->commands[] = $inline_js;
    }

    /**
     * Add a single JS command to the current command stream
     *
     * @param string $command
     */
    public function addCommand($command) {
        $this->addInline($command);
    }

    /**
     * Execute the current stream of JS commands
     *
     * @return mixed
     */
    public function execute() {
        try {
            return self::$v8->executeString(implode("\n", $this->commands));
        } catch(V8JsException $e) {
            echo '<pre>', htmlentities(print_r($e, true)), '</pre>';
            die;
        }
    }

    /**
     * Get the stream of commands that would be sent to V8Js, as a string
     *
     * @return string All of the current commands, as a string
     */
    public function getCommandStream() {
        return implode("\n", $this->commands);
    }

    /**
     * Add JavaScript plugins. Here's an inline example:
     * <code>
     * addPlugins([
     * ['file'=>'/path/to/jquery/jquery.min.js'], 'globals'=>['jQuery', '$']
     * ['file'=>'/path/to/react/react.min.js'], 'globals'=>'React']
     * ])
     * </code>
     *
     * You can also add a concatenated file and expose multiple globals:
     * <code>
     * addPlugins([
     *  ['file'=>'/path/to/file/plugins.min.js', 'globals'=>['jQuery', '$', 'React']
     * ]);
     * </code>
     *
     * @param array $plugins
     */
    public function addFiles($plugins) {
        foreach ($plugins as $plugin) {
            $file = $plugin['file'];
            $globals = empty($plugin['globals']) ? null : $plugin['globals'];
            $this->addFile($file, $globals);
        }
    }

    /**
     * Adds a file to the current stream to be executed
     *
     * @param string $uri The path to the resource (file or HTTP/HTTPs URL)
     * @param string|array $globals A JS variable to be pushed to the global JS scope, or an array of such JS variables
     */
    public function addFile($uri, $globals=null) {
        $file_exists = file_exists($uri);
        if (!$file_exists && filter_var($uri, FILTER_VALIDATE_URL) === false) {
            echo '<pre>File ', $uri, ' not found</pre>'; // Neither a file nor a valid url
            die;
        } elseif (!isset(self::$file_cache[$uri])) {
            if ($file_exists) {
                self::$file_cache[$uri] = file_get_contents($uri);
            } elseif (ini_get('allow_url_fopen') == true) {
                // If file_get_contents can retrieve URLs, just go for it
                self::$file_cache[$uri] = file_get_contents($uri);
            } else {
                $ch = curl_init($uri);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $response = curl_exec($ch);
                if ($response === false) {
                    echo "<pre> Resource $uri could not be loaded.\nServer Response: ", htmlentities($response), '</pre>';
                    die;
                }
                self::$file_cache[$uri] = $response;
            }
        }
        $this->addCachedResource($uri, $globals);
    }

    /**
     * An internal utility to add files from the internal plugin cache and expose
     * globals.
     *
     * @param array $cache_key
     * @param string|array $globals
     */
    private function addCachedResource($cache_key, $globals=null) {
        $this->commands[] = self::$file_cache[$cache_key];
        foreach ((array)$globals as $global) {
            $this->commands[] = "var {$global} = global.{$global}";
        }
    }

    /**
     * The first two lines of all JS streams; defines the console and
     * global JS objects
     *
     * @return array
     */
    private function getDefaultArgs() {
        return array(
            "var console = {warn: function(){}, log: print, error: print}",
            "var global = {}"
        );
    }

}
