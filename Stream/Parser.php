<?php // vi: set encoding=utf-8 expandtab shiftwidth=4 tabstop=4:

define('T_STREAM_LINE', -1); // Generic tag matching any single line of the input stream

class Stream_Parser
{
    protected

    $dependencyName = null,    // Fully qualified class identifier, defaults to get_class($this)
    $dependencies   = array(), // (dependencyName => shared properties) map before instanciation, then (dependencyName => dependency object) map after
    $callbacks      = array(), // Callbacks to be registered
    $nextLine       = false;   // Next line of the input stream

    private

    $parent,
    $parents = array(),
    $registryIndex = 0,
    $callbackRegistry = array(),
    $nextRegistryIndex = 0;

    protected static

    $tagNames = array(T_STREAM_LINE => 'T_STREAM_LINE');


    function __construct(self $parent = null)
    {
        $parent || $parent = __CLASS__ === get_class($this) ? $this : new self;

        $this->dependencyName || $this->dependencyName = get_class($this);
        $this->dependencies = (array) $this->dependencies;
        $this->parent = $parent;

        // Link shared properties of $parent and $this by reference

        if ($parent !== $this)
        {
            $v = array(
                'parents',
                'nextLine',
                'callbackRegistry',
                'nextRegistryIndex',
            );

            foreach ($v as $v) $this->$v =& $parent->$v;
        }
        else $this->nextRegistryIndex = -1 - PHP_INT_MAX;

        // Verify and set $this->dependencies to the (dependencyName => dependency object) map

        foreach ($this->dependencies as $k => $v)
        {
            unset($this->dependencies[$k]);

            if (is_string($k))
            {
                $c = (array) $v;
                $v = $k;
            }
            else $c = array();

            $k = strtolower('\\' !== $v[0] ? __CLASS__ . '_' . $v : substr($v, 1));

            if (!isset($this->parents[$k]))
            {
                return trigger_error(get_class($this) . ' failed dependency: ' . $v);
            }

            $this->dependencies[$v] = $this->parents[$k];

            foreach ($c as $c) $this->$c =& $this->parents[$k]->$c;
        }

        // Keep track of parents chained parsers

        $k = strtolower($this->dependencyName);
        $this->parents[$k] = $this;

        // Keep parsers chaining order for callbacks ordering

        $this->registryIndex = $this->nextRegistryIndex;
        $this->nextRegistryIndex += 1 << (PHP_INT_SIZE << 2);

        empty($this->callbacks) || $this->register();
    }

    function __destruct()
    {
        $this->parent = $this->parents = $this->callbackRegistry = $this->dependencies = null;
    }

    function parseStream($stream)
    {
        // Parse the stream after recursively traversing $this->parent

        if ($this->parent !== $this) return $this->parent->parseStream($stream);

        // Callback registry matching loop

        $this->nextLine = fgets($stream);
        $reg =& $this->callbackRegistry;

        while (false !== $line = $this->nextLine)
        {
            $t = T_STREAM_LINE;
            $tags = array();
            $callbacks = array();
            $this->nextLine = fgets($stream);

            do
            {
                $tags[$t] = $t;

                if (isset($reg[$t]))
                {
                    $callbacks += $reg[$t];
                    ksort($callbacks);
                }

                foreach ($callbacks as $t => $c)
                {
                    unset($callbacks[$t]);

                    if (false === $c[0] || preg_match($c[0], $line))
                    {
                        $t = $c[1]->$c[2]($line, $tags);

                        if (false === $t) continue 3;
                        if ($t && empty($tags[$t])) continue 2;
                    }
                }

                break;
            }
            while (1);
        }
    }

    protected function register($method = null)
    {
        $this->callbackRegisteryApply($method, 'register');
    }

    protected function unregister($method = null)
    {
        $this->callbackRegisteryApply($method, 'unregister');
    }

    private function callbackRegisteryApply($method, $action)
    {
        null === $method && $method = $this->callbacks;
        is_string($method) && $method = array($method => array(T_STREAM_LINE => false));

        foreach ($method as $method => $tag)
        {
            if (is_int($method))
            {
                $method = $tag;
                $tag = array(T_STREAM_LINE => false);
            }
            else if (is_int($tag)) $tag = array($tag => false);

            foreach ($tag as $tag => $rx)
            {
                $rx = (array) $rx;
                $t = array($tag);

                if (is_string($tag)) list($rx, $t) = array($t, $rx);

                foreach ($rx as $rx)
                {
                    if(is_int($rx))
                    {
                        $tag = array($rx);
                        $rx  = false;
                    }
                    else $tag = $t;

                    foreach ($tag as $tag)
                    {
                        if ('register' === $action)
                        {
                            $this->callbackRegistry[$tag][++$this->registryIndex] = array($rx, $this, $method);
                        }
                        else if ('unregister' === $action)
                        {
                            if (isset($this->callbackRegistry[$tag]))
                                foreach ($this->callbackRegistry[$tag] as $k => $v)
                                    if (array($rx, $this, $method) === $v)
                                        unset($this->callbackRegistry[$tag][$k]);

                            if (empty($this->callbackRegistry[$tag]))
                                unset($this->callbackRegistry[$tag]);
                        }
                    }
                }
            }
        }
    }

    static function createTag($name)
    {
        static $tag = T_STREAM_LINE;
        defined($name) ? $tag = constant($name) : define($name, --$tag);
        if (isset(self::$tagNames[$tag])) trigger_error("Overwriting an already created tag value is forbidden ({$name}={$tag})", E_USER_ERROR);
        else self::$tagNames[$tag] = $name;
    }

    static function getTagName($tag)
    {
        return isset(self::$tagNames[$tag]) ? self::$tagNames[$tag] : false;
    }
}
