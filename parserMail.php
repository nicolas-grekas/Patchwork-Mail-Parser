#!/usr/bin/php -q
<?php // vi: set encoding=utf-8 expandtab shiftwidth=4 tabstop=4:

$parser = new Mail_Parser;
$parser = new Mail_Parser_Echo($parser);
$parser->parseStream(STDIN);



class Mail_Parser
{
    protected

    // Declarations used by __construct()
    $dependencyName = null,    // Fully qualified class identifier, defaults to get_class($this)
    $dependencies   = array(), // (dependencyName => shared properties) map before instanciation, then (dependencyName => dependency object) map after
    $callbacks      = array(), // Callbacks to be registered

    $callbackRegistry = array();

    private

    $parents = array(),
    $nextRegistryIndex = 0,

    $parent,
    $registryIndex = 0;


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
                'callbackRegistry',
                'parents',
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

    function parseStream($stream)
    {
        // Parse the stream after recursively traversing $this->parent

        if ($this->parent !== $this) return $this->parent->parseStream($stream);

        // Alias properties to local variables, initialize them

        $reg =& $this->callbackRegistry;

        while (false !== $b = fgets($stream))
        {
            // Trigger callbacks

            foreach ($reg as $c)
            {
                if (preg_match($c[0], $b)) $c[1]->$c[2]($b);
            }
        }

        // Free memory
        $reg = $this->parents = $this->parent = null;
    }


    protected function register($method = null)
    {
        null === $method && $method = $this->callbacks;

        foreach ((array) $method as $method => $rx)
        {
            if (is_int($method))
            {
                $method = $rx;
                $rx = array('//');
            }

            foreach((array) $rx as $rx)
                $this->callbackRegistry[++$this->registryIndex] = array($rx, $this, $method);
        }

        ksort($this->callbackRegistry);
    }

    protected function unregister($method = null)
    {
        null === $method && $method = $this->callbacks;

        foreach ((array) $method as $method => $rx)
        {
            if (is_int($method))
            {
                $method = $rx;
                $rx = array('//');
            }

            foreach ((array) $rx as $rx)
                foreach ($this->callbackRegistry as $k => $v)
                    if (array($rx, $this, $method) === $v)
                        unset($this->callbackRegistry[$k]);
        }
    }
}

class Mail_Parser_Echo extends Mail_Parser
{
    protected $callbacks = 'echoLine';

    protected function echoLine($line)
    {
        echo $line;
    }
}
