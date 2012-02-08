<?php

/**
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2012 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\ConfigBuilder;

use InvalidArgumentException;

class Filter implements Item
{
	/**
	* @var callback
	*/
	protected $callback;

	/**
	* @var array List of params to be passed to the callback
	*/
	protected $params = array();

	/**
	* @param callback $callback
	*/
	public function __construct($callback)
	{
		if (!is_callable($callback))
		{
			throw new InvalidArgumentException('Callback ' . var_export($callback, true) . ' is not callable');
		}

		$this->callback = $callback;
	}

	/**
	* Return whether a string is a valid filter name
	*
	* @param  string $name
	* @return bool
	*/
	static public function isValidName($name)
	{
		return (bool) preg_match('#^[a-z_]\\w*$#Di', $name);
	}

	/**
	* Validate and normalize a filter namename
	*
	* @param  string $name Original filter name
	* @return string       Normalized filter name, in lowercase
	*/
	static public function normalizeName($name)
	{
		if (!self::isValidName($name))
		{
			throw new InvalidArgumentException ("Invalid filter name '" . $name . "'");
		}

		return strtolower($name);
	}

	/**
	* Add a parameter by value
	*
	* @param mixed $paramValue
	*/
	public function addParameterByValue($paramValue)
	{
		$this->params[] = $paramValue;
	}

	/**
	* Add a parameter by name
	*
	* The value will be dynamically generated by the caller
	*
	* @param mixed $paramName
	*/
	public function addParameterByName($paramName)
	{
		$this->params[$paramName] = null;
	}

	/**
	* Set the Javascript source for this callback
	*
	* @param string $js
	*/
	public function setJavascript($js)
	{
		$this->js = $js;
	}

	/**
	* @param  array    $arr
	* @return Filter
	*/
	static public function fromArray(array $arr)
	{
		$callback = new static($arr['callback']);

		if (!empty($arr['params']))
		{
			foreach ($arr['params'] as $k => $v)
			{
				if (is_numeric($k))
				{
					$callback->addParameterByValue($v);
				}
				else
				{
					$callback->addParameterByName($k);
				}
			}
		}

		if (isset($arr['js']))
		{
			$callback->setJavascript($arr['js']);
		}

		return $callback;
	}

	/**
	* @return array
	*/
	public function toArray()
	{
		$arr = array(
			'callback' => $this->callback,
			'params'   => $this->params
		);

		if (isset($this->js))
		{
			$arr['js'] = $this->js;
		}

		return $arr;
	}
}