<?php
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Stream;

const T_STREAM_LINE = -1; // Generic tag matching any single line of the input stream

/**
 * Patchwork\Stream\Parser is a highly extensible framework
 * for building high-performance stream parsers.
 *
 * It does nothing on its own but implement a predictable plugin mechanism
 * for registering and dispatching lines of the stream to a chain of parsers,
 * while remaining as fast and memory efficient as possible.
 */
class Parser
{
    protected $serviceName = null;    // Fully qualified class identifier, defaults to get_class($this)
    protected $dependencies = array(  // (serviceName => shared properties) map before instanciation
                                   ); // (serviceName => service provider object) map after
    protected $callbacks = array();   // Callbacks to register
    protected $nextLine = false;      // Next line of the input stream
    protected $lineNumber = 0;        // Line number

    private $parents = array();
    private $errors = array();
    private $nextRegistryIndex = 0;

    private $parent;
    private $registryIndex = 0;
    private $callbackRegistry = array();

    protected static $tagNames = array(T_STREAM_LINE => 'T_STREAM_LINE');

    public function __construct(self $parent = null)
    {
        $parent || $parent = __CLASS__ === get_class($this) ? $this : new self();

        if (!$this->serviceName) {
            $this->serviceName = get_class($this);
        }
        $this->dependencies = (array) $this->dependencies;
        $this->parent = $parent;

        // Link shared properties of $parent and $this by reference

        if ($parent !== $this) {
            $v = array(
                'parents',
                'errors',
                'nextLine',
                'lineNumber',
                'callbackRegistry',
                'nextRegistryIndex',
            );

            foreach ($v as $v) {
                $this->$v = &$parent->$v;
            }
        } else {
            $this->nextRegistryIndex = -1 - PHP_INT_MAX;
        }

        // Verify and set $this->dependencies to the (serviceName => service provider object) map

        foreach ($this->dependencies as $k => $v) {
            unset($this->dependencies[$k]);

            if (is_string($k)) {
                $c = (array) $v;
                $v = $k;
            } else {
                $c = array();
            }

            $k = strtolower('\\' !== $v[0] ? __CLASS__.'\\'.$v : substr($v, 1));

            if (!isset($this->parents[$k])) {
                throw new \LogicException(get_class($this)." failed dependency: {$v}");
            }

            $parent = $this->dependencies[$v] = $this->parents[$k];

            foreach ($c as $c => $k) {
                is_int($c) && $c = $k;

                if (!property_exists($parent, $c)) {
                    user_error(get_class($this)." undefined parent property: {$v}->{$c}", E_USER_WARNING);
                }
                if (!property_exists($this, $k)) {
                    user_error(get_class($this)." undefined property: \$this->{$k}", E_USER_NOTICE);
                }

                $this->$k = &$parent->$c;
            }
        }

        // Keep track of parents chained parsers

        $k = strtolower($this->serviceName);
        $this->parents[$k] = $this;

        // Keep parsers chaining order for callbacks ordering

        $this->registryIndex = $this->nextRegistryIndex;
        $this->nextRegistryIndex += 1 << (PHP_INT_SIZE << 2);

        $this->register($this->callbacks);
    }

    public function __destruct()
    {
        $this->parent = $this->parents = $this->callbackRegistry = $this->dependencies = null;
    }

    public function getErrors()
    {
        ksort($this->errors);
        $e = array();
        foreach ($this->errors as $v) {
            foreach ($v as $e[]) {
            }
        }

        return $e;
    }

    public function parseStream($stream)
    {
        // Recursively traverse the inheritance chain defined by $this->parent

        if ($this->parent !== $this) {
            return $this->parent->parseStream($stream);
        }

        $this->errors = array();
        $this->lineNumber = 0;
        $this->nextLine = fgets($stream);
        $reg = &$this->callbackRegistry;

        // Callback registry matching loop

        while (false !== $line = $this->nextLine) {
            $t = T_STREAM_LINE;
            $tags = array();
            $callbacks = array();
            $this->nextLine = fgets($stream);
            ++$this->lineNumber;

            for (;;) {
                $tags[$t] = $t;

                if (isset($reg[$t])) {
                    $callbacks += $reg[$t];

                    // Callbacks triggering are always ordered:
                    // - first by parsers' instanciation order
                    // - then by callbacks' registration order
                    // - callbacks registered with a tilde prefix
                    //   are then called in reverse order.
                    ksort($callbacks);
                }

                foreach ($callbacks as $k => $c) {
                    unset($callbacks[$k]);
                    $matches = array();

                    if (false === $c[0] || preg_match($c[0], $line, $matches)) {
                        if ($k < 0) {
                            // $line is the current line
                            // $matches is populated by applying a registered regexp to $line
                            // $tags is an array of tags already associated to $line

                            $t = $c[1]->$c[2]($line, $tags, $matches);

                            // Non-tilde-prefixed callback can return:
                            // - false, which cancels the current line
                            // - a new line tag, which is added to $tags and loads the
                            //   related callbacks in the current callbacks stack
                            // - or nothing (null)

                            if (false === $t) {
                                continue 3;
                            }
                            if ($t && empty($tags[$t])) {
                                continue 2;
                            }
                        } elseif (null !== $c[0]->$c[1]($line, $tags, $matches)) {
                            user_error('No return value is expected for tilde-registered callback: '.get_class($c[0]).'->'.$c[1].'()', E_USER_NOTICE);
                        }
                    }
                }

                break;
            }
        }
    }

    // Set an error on input code inside parsers

    protected function setError($message, $type)
    {
        $this->errors[(int) $this->lineNumber][] = array(
            'type' => $type,
            'message' => $message,
            'line' => (int) $this->lineNumber,
            'parser' => get_class($this),
        );
    }

    // Register callbacks for the next lines

    protected function register($method)
    {
        $this->callbackRegisteryApply($method, true);
    }

    // Unregister callbacks for the next lines

    protected function unregister($method)
    {
        $this->callbackRegisteryApply($method, false);
    }

    private function callbackRegisteryApply($method, $register)
    {
        if (is_string($method)) {
            $method = array($method => array(T_STREAM_LINE => false));
        }

        foreach ($method as $method => $tag) {
            if (is_int($method)) {
                $method = $tag;
                $tag = array(T_STREAM_LINE => false);
            } elseif (is_int($tag)) {
                $tag = array($tag => false);
            }

            if ('~' === $method[0]) {
                $desc = -1;
                $method = substr($method, 1);
            } else {
                $desc = 0;
            }

            foreach ($tag as $tag => $rx) {
                $rx = (array) $rx;
                $t = array($tag);

                if (is_string($tag)) {
                    list($rx, $t) = array($t, $rx);
                }

                foreach ($rx as $rx) {
                    if (is_int($rx)) {
                        $tag = array($rx);
                        $rx = false;
                    } else {
                        $tag = $t;
                    }

                    foreach ($tag as $tag) {
                        if ($register) {
                            $this->callbackRegistry[$tag][++$this->registryIndex ^ $desc] = array($rx, $this, $method);
                        } else {
                            if (isset($this->callbackRegistry[$tag])) {
                                foreach ($this->callbackRegistry[$tag] as $k => $v) {
                                    if (array($rx, $this, $method) === $v && ($desc ? $k > 0 : $k < 0)) {
                                        unset($this->callbackRegistry[$tag][$k]);
                                    }
                                }
                            }

                            if (empty($this->callbackRegistry[$tag])) {
                                unset($this->callbackRegistry[$tag]);
                            }
                        }
                    }
                }
            }
        }
    }

    // Unregister all callbacks registered for $this parser

    protected function unregisterAll()
    {
        foreach ($this->callbackRegistry as $tag => $v) {
            foreach ($v as $k => $v) {
                if ($this === $v[1]) {
                    unset($this->callbackRegistry[$tag][$k]);
                }
            }

            if (empty($this->callbackRegistry[$tag])) {
                unset($this->callbackRegistry[$tag]);
            }
        }
    }

    // Create new line type

    public static function createTag($name)
    {
        static $tag = T_STREAM_LINE;

        if (defined($name)) {
            $tag = constant($name);
        } else {
            define($name, --$tag);
        }
        if (isset(self::$tagNames[$tag])) {
            throw new \LogicException("Overwriting an already created tag value is forbidden ({$name}={$tag})");
        } else {
            self::$tagNames[$tag] = $name;
        }
    }

    // Get the symbolic name of a given line tag as created by self::createTag

    public static function getTagName($tag)
    {
        return isset(self::$tagNames[$tag]) ? self::$tagNames[$tag] : false;
    }
}
