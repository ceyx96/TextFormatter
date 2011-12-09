<?php

/**
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2011 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter;

use DOMDocument,
    DOMXPath,
    InvalidArgumentException,
    RuntimeException,
    SimpleXMLElement,
    XSLTProcessor,
    UnexpectedValueException;

class ConfigBuilder
{
	/**
	* Allow user-supplied data to be used in sensitive area of a template
	* @see self::setTagTemplate()
	*/
	const ALLOW_UNSAFE_TEMPLATES = 1;

	/**
	* Whether or not preserve redundant whitespace in a template
	* @see  self::setTagTemplate()
	* @link http://www.php.net/manual/en/class.domdocument.php#domdocument.props.preservewhitespace
	*/
	const PRESERVE_WHITESPACE = 2;

	/**
	* @var array Tags repository
	*/
	protected $tags = array();

	/**
	* @var array Holds filters' configuration
	*/
	protected $filters = array(
		'url' => array(
			'allowedSchemes' => array('http', 'https')
		)
	);

	/**
	* @var string Extra XSL to append to the stylesheet
	*/
	protected $xsl = '';

	/**
	* @var array  Default options applied to tags, can be overriden by options passed by plugins
	*/
	public $defaultTagOptions = array(
		'disable'        => false,
		'disallowAsRoot' => false,
		'tagLimit'     => 100,
		'nestingLimit' => 10,
		'defaultChildRule'      => 'allow',
		'defaultDescendantRule' => 'allow'
	);

	//==========================================================================
	// Tag-related methods
	//==========================================================================

	/**
	* Define a new tag
	*
	* @param string $tagName    Name of the tag {@see isValidTagName()}
	* @param array  $tagOptions Tag options (automatically augmented by $this->defaultTagOptions)
	*/
	public function addTag($tagName, array $tagOptions = array())
	{
		$tagName = $this->normalizeTagName($tagName, false);

		if (isset($this->tags[$tagName]))
		{
			throw new InvalidArgumentException("Tag '" . $tagName . "' already exists");
		}

		/**
		* Create the tag with the default options
		*/
		$this->tags[$tagName] = $this->defaultTagOptions;

		/**
		* Set the user-supplied options
		*/
		$this->setTagOptions($tagName, $tagOptions);
	}

	/**
	* Remove a tag from the config
	*
	* @param string $tagName
	*/
	public function removeTag($tagName)
	{
		unset($this->tags[$this->normalizeTagName($tagName)]);
	}

	/**
	* Return whether a tag exists
	*
	* @param  string $tagName
	* @return bool
	*/
	public function tagExists($tagName)
	{
		return isset($this->tags[$this->normalizeTagName($tagName, false)]);
	}

	/**
	* Return whether a string is a valid tag name
	*
	* @param  string $tagName
	* @return bool
	*/
	public function isValidTagName($tagName)
	{
		return (bool) preg_match('#^[a-z_][a-z_0-9]*$#Di', $tagName);
	}

	/**
	* Validate and normalize a tag name
	*
	* @param  string $tagName   Original tag name
	* @param  bool   $mustExist If TRUE, throw an exception if the tag does not exist
	* @return string            Normalized tag name, in uppercase
	*/
	public function normalizeTagName($tagName, $mustExist = true)
	{
		if (!$this->isValidTagName($tagName))
		{
			throw new InvalidArgumentException ("Invalid tag name '" . $tagName . "'");
		}

		$tagName = strtoupper($tagName);

		if ($mustExist && !isset($this->tags[$tagName]))
		{
			throw new InvalidArgumentException("Tag '" . $tagName . "' does not exist");
		}

		return $tagName;
	}

	//==========================================================================
	// Tag options-related methods
	//==========================================================================

	/**
	* Get all of a tag's options
	*
	* @param  string $tagName
	* @return array
	*/
	public function getTagOptions($tagName)
	{
		$tagName = $this->normalizeTagName($tagName);

		return $this->tags[$tagName];
	}

	/**
	* Get a tag's option
	*
	* @param  string $tagName
	* @param  string $optionName
	* @return mixed
	*/
	public function getTagOption($tagName, $optionName)
	{
		$tagName = $this->normalizeTagName($tagName);

		if (!array_key_exists($optionName, $this->tags[$tagName]))
		{
			throw new InvalidArgumentException("Unknown option '" . $optionName . "' from tag '" . $tagName . "'");
		}

		return $this->tags[$tagName][$optionName];
	}

	/**
	* Set several options for a tag
	*
	* @param string $tagName
	* @param array  $tagOptions
	*/
	public function setTagOptions($tagName, array $tagOptions)
	{
		foreach ($tagOptions as $optionName => $optionValue)
		{
			$this->setTagOption($tagName, $optionName, $optionValue);
		}
	}

	/**
	* Set a tag's option
	*
	* @param string $tagName
	* @param string $optionName
	* @param mixed  $optionValue
	*/
	public function setTagOption($tagName, $optionName, $optionValue)
	{
		$tagName = $this->normalizeTagName($tagName);

		switch ($optionName)
		{
			case 'attrs':
				foreach ($optionValue as $attrName => $attrConf)
				{
					$this->addTagAttribute($tagName, $attrName, $attrConf['type'], $attrConf);
				}
				break;

			case 'rules':
				foreach ($optionValue as $action => $targets)
				{
					foreach ($targets as $target)
					{
						$this->addTagRule($tagName, $action, $target);
					}
				}
				break;

			case 'template':
				$this->setTagTemplate($tagName, $optionValue);
				break;

			case 'xsl':
				$this->setTagXSL($tagName, $optionValue);
				break;

			case 'preFilter':
			case 'postFilter':
				$this->clearTagCallbacks($optionName, $tagName);
				foreach ($optionValue as $callbackConf)
				{
					// add the default params config if it's not set
					$callbackConf += array('params' => array('attrs' => null));

					$this->addTagCallback(
						$optionName,
						$tagName,
						$callbackConf['callback'],
						$callbackConf['params']
					);
				}
				break;

			default:
				if (isset($this->defaultTagOptions[$optionName]))
				{
					/**
					* Preserve the PHP type of that option, if applicable
					*/
					settype($optionValue, gettype($this->defaultTagOptions[$optionName]));
				}

				$this->tags[$tagName][$optionName] = $optionValue;
		}
	}

	/**
	* Remove all preFilter callbacks associated with a tag
	*
	* @param string $tagName
	*/
	public function clearTagPreFilterCallbacks($tagName)
	{
		$this->clearTagCallbacks('preFilter', $tagName);
	}

	/**
	* Remove all postFilter callbacks associated with a tag
	*
	* @param string $tagName
	*/
	public function clearTagPostFilterCallbacks($tagName)
	{
		$this->clearTagCallbacks('postFilter', $tagName);
	}

	/**
	* Remove all phase callbacks associated with a tag
	*
	* @param string $phase    Either 'preFilter' or 'postFilter'
	* @param string $tagName
	*/
	protected function clearTagCallbacks($phase, $tagName)
	{
		$tagName = $this->normalizeTagName($tagName);

		unset($this->tags[$tagName][$phase]);
	}

	/**
	* Add a preFilter callback to a tag
	*
	* @param string   $tagName
	* @param callback $callback
	* @param array    $params
	*/
	public function addTagPreFilterCallback($tagName, $callback, array $params = array('attrs' => null))
	{
		$this->addTagCallback('preFilter', $tagName, $callback, $params);
	}

	/**
	* Add a postFilter callback to a tag's attribute
	*
	* @param string   $tagName
	* @param callback $callback
	* @param array    $params
	*/
	public function addTagPostFilterCallback($tagName, $callback, array $params = array('attrs' => null))
	{
		$this->addTagCallback('postFilter', $tagName, $callback, $params);
	}

	/**
	* Add a phase callback to a tag
	*
	* @param string   $phase    Either 'preFilter' or 'postFilter'
	* @param string   $tagName
	* @param callback $callback
	* @param array    $params
	*/
	protected function addTagCallback($phase, $tagName, $callback, array $params)
	{
		$tagName = $this->normalizeTagName($tagName);

		if (!is_callable($callback))
		{
			throw new InvalidArgumentException('Not a callback');
		}

		$this->tags[$tagName][$phase][] = array(
			'callback' => $callback,
			'params'   => $params
		);
	}

	//==========================================================================
	// Attribute-related methods
	//==========================================================================

	/**
	* Define an attribute for a tag
	*
	* @param string $tagName
	* @param string $attrName
	* @param string $attrType
	* @param array  $conf
	*/
	public function addTagAttribute($tagName, $attrName, $attrType, array $attrConf = array())
	{
		$tagName  = $this->normalizeTagName($tagName);
		$attrName = $this->normalizeAttributeName($attrName);

		if (isset($this->tags[$tagName]['attrs'][$attrName]))
		{
			throw new InvalidArgumentException("Attribute '" . $attrName . "' already exists");
		}

		/**
		* Set attribute type
		*/
		$attrConf['type'] = $attrType;

		/**
		* Add the attribute with default config values;
		*/
		$this->tags[$tagName]['attrs'][$attrName] = array(
			/**
			* Compound attributes are not required by default. The attributes they split into
			* should be already. Plus, we remove compound attributes during parsing.
			*/
			'isRequired' => (bool) ($attrType !== 'compound')
		);

		$this->setTagAttributeOptions($tagName, $attrName, $attrConf);
	}

	/**
	* Set several options in a tag's attribute config
	*
	* @param string $tagName
	* @param string $attrName
	* @param array  $options
	*/
	public function setTagAttributeOptions($tagName, $attrName, $options)
	{
		foreach ($options as $optionName => $optionValue)
		{
			$this->setTagAttributeOption($tagName, $attrName, $optionName, $optionValue);
		}
	}

	/**
	* Set an option in a tag's attribute config
	*
	* @param string $tagName
	* @param string $attrName
	* @param string $optionName
	* @param mixed  $optionValue
	*/
	public function setTagAttributeOption($tagName, $attrName, $optionName, $optionValue)
	{
		$tagName  = $this->normalizeTagName($tagName);
		$attrName = $this->normalizeAttributeName($attrName, $tagName);

		$attrConf =& $this->tags[$tagName]['attrs'][$attrName];

		switch ($optionName)
		{
			case 'preFilter':
			case 'postFilter':
				$this->clearTagAttributeCallbacks($optionName, $tagName, $attrName);

				foreach ($optionValue as $callbackConf)
				{
					// add the default params config if it's not set
					$callbackConf += array('params' => array('attrVal' => null));

					$this->addTagAttributeCallback(
						$optionName,
						$tagName,
						$attrName,
						$callbackConf['callback'],
						$callbackConf['params']
					);
				}
				break;

			default:
				$attrConf[$optionName] = $optionValue;
		}
	}

	/**
	* Return all the options of a tag's attribute
	*
	* @param  string $tagName
	* @param  string $attrName
	* @return array
	*/
	public function getTagAttributeOptions($tagName, $attrName)
	{
		$tagName  = $this->normalizeTagName($tagName);
		$attrName = $this->normalizeAttributeName($attrName, $tagName);

		return $this->tags[$tagName]['attrs'][$attrName];
	}

	/**
	* Return the value of an option in a tag's attribute config
	*
	* @param  string $tagName
	* @param  string $attrName
	* @param  string $optionName
	* @return mixed
	*/
	public function getTagAttributeOption($tagName, $attrName, $optionName)
	{
		$tagName  = $this->normalizeTagName($tagName);
		$attrName = $this->normalizeAttributeName($attrName, $tagName);

		return $this->tags[$tagName]['attrs'][$attrName][$optionName];
	}

	/**
	* Remove an attribute from a tag
	*
	* @param string $tagName
	* @param string $attrName
	*/
	public function removeAttribute($tagName, $attrName)
	{
		$tagName  = $this->normalizeTagName($tagName);
		$attrName = $this->normalizeAttributeName($attrName, $tagName);
		unset($this->tags[$tagName]['attrs'][$attrName]);
	}

	/**
	* Return whether a tag's attribute exists
	*
	* @param  string $tagName
	* @param  string $attrName
	* @return bool
	*/
	public function attributeExists($tagName, $attrName)
	{
		$tagName  = $this->normalizeTagName($tagName);
		$attrName = $this->normalizeAttributeName($attrName);

		return isset($this->tags[$tagName]['attrs'][$attrName]);
	}

	/**
	* Return whether a string is a valid attribute name
	*
	* @param  string $attrName
	* @return bool
	*/
	static public function isValidAttributeName($attrName)
	{
		return (bool) preg_match('#^[a-z_][a-z_0-9]*$#Di', $attrName);
	}

	/**
	* Validate and normalize an attribute name
	*
	* @param  string $attrName Original attribute name
	* @param  string $tagName  If set, check that the attribute exists for given tag and throw an
	*                          exception otherwise
	* @return string           Normalized attribute name, in lowercase
	*/
	public function normalizeAttributeName($attrName, $tagName = null)
	{
		if (!$this->isValidAttributeName($attrName))
		{
			throw new InvalidArgumentException ("Invalid attribute name '" . $attrName . "'");
		}

		$attrName = strtolower($attrName);

		if (isset($tagName))
		{
			$tagName = $this->normalizeTagName($tagName);

			if (!isset($this->tags[$tagName]['attrs'][$attrName]))
			{
				throw new InvalidArgumentException("Tag '" . $tagName . "' does not have an attribute named '" . $attrName . "'");
			}
		}

		return $attrName;
	}

	/**
	* Remove all preFilter callbacks associated with an attribute
	*
	* @param string $tagName
	* @param string $attrName
	*/
	public function clearTagAttributePreFilterCallbacks($tagName, $attrName)
	{
		$this->clearTagAttributeCallbacks('preFilter', $tagName, $attrName);
	}

	/**
	* Remove all postFilter callbacks associated with an attribute
	*
	* @param string $tagName
	* @param string $attrName
	*/
	public function clearTagAttributePostFilterCallbacks($tagName, $attrName)
	{
		$this->clearTagAttributeCallbacks('postFilter', $tagName, $attrName);
	}

	/**
	* Remove all phase callbacks associated with an attribute
	*
	* @param string $phase    Either 'preFilter' or 'postFilter'
	* @param string $tagName
	* @param string $attrName
	*/
	protected function clearTagAttributeCallbacks($phase, $tagName, $attrName)
	{
		$tagName  = $this->normalizeTagName($tagName);
		$attrName = $this->normalizeAttributeName($attrName);

		unset($this->tags[$tagName]['attrs'][$attrName][$phase]);
	}

	/**
	* Add a preFilter callback to a tag's attribute
	*
	* @param string   $tagName
	* @param string   $attrName
	* @param callback $callback
	* @param array    $params
	*/
	public function addTagAttributePreFilterCallback($tagName, $attrName, $callback, array $params = array('attrVal' => null))
	{
		$this->addTagAttributeCallback('preFilter', $tagName, $attrName, $callback, $params);
	}

	/**
	* Add a postFilter callback to a tag's attribute
	*
	* @param string   $tagName
	* @param string   $attrName
	* @param callback $callback
	* @param array    $params
	*/
	public function addTagAttributePostFilterCallback($tagName, $attrName, $callback, array $params = array('attrVal' => null))
	{
		$this->addTagAttributeCallback('postFilter', $tagName, $attrName, $callback, $params);
	}

	/**
	* Add a phase callback to a tag's attribute
	*
	* @param string   $phase    Either 'preFilter' or 'postFilter'
	* @param string   $tagName
	* @param string   $attrName
	* @param callback $callback
	* @param array    $params
	*/
	protected function addTagAttributeCallback($phase, $tagName, $attrName, $callback, array $params)
	{
		$tagName  = $this->normalizeTagName($tagName);
		$attrName = $this->normalizeAttributeName($attrName);

		if (!is_callable($callback))
		{
			throw new InvalidArgumentException('Not a callback');
		}

		$this->tags[$tagName]['attrs'][$attrName][$phase][] = array(
			'callback' => $callback,
			'params'   => $params
		);
	}

	//==========================================================================
	// Rule-related methods
	//==========================================================================

	/**
	* Define a rule
	*
	* The first tag must already exist at the time the rule is created.
	* The target tag doesn't have to exist though, so that we can set all the rules related to a tag
	* during its creation, regardless on whether target tags exist or not. Rules that pertain to
	* inexistent tags do not appear in the final configuration.
	*
	* @param string $tagName
	* @param string $action
	* @param string $target
	*/
	public function addTagRule($tagName, $action, $target)
	{
		$tagName = $this->normalizeTagName($tagName);
		$target  = $this->normalizeTagName($target, false);

		if (!in_array($action, array(
			'allowChild',
			'allowDescendant',
			'closeParent',
			'closeAncestor',
			'denyChild',
			'denyDescendant',
			'reopenChild',
			'requireParent',
			'requireAncestor'
		), true))
		{
			throw new UnexpectedValueException("Unknown rule action '" . $action . "'");
		}

		$this->tags[$tagName]['rules'][$action][$target] = $target;

		/**
		* Replicate *Descendant rules to *Child
		*/
		if ($action === 'denyDescendant'
		 || $action === 'allowDescendant')
		{
			 $this->addTagRule($tagName, substr($action, 0, -10) . 'Child', $target);
		}
	}

	/**
	* Remove a tag's rule
	*
	* @param string $tagName
	* @param string $action
	* @param string $target
	*/
	public function removeRule($tagName, $action, $target)
	{
		$tagName = $this->normalizeTagName($tagName);
		$target  = $this->normalizeTagName($target);

		unset($this->tags[$tagName]['rules'][$action][$target]);
	}

	//==========================================================================
	// Tag template-related methods
	//==========================================================================

	/**
	* Return the XSL associated with a tag
	*
	* @param  string $tagName Name of the tag
	* @return string
	*/
	public function getTagXSL($tagName)
	{
		$tagName = $this->normalizeTagName($tagName);

		if (!isset($this->tags[$tagName]['xsl']))
		{
			throw new InvalidArgumentException("No XSL set for tag '" . $tagName . "'");
		}

		return $this->tags[$tagName]['xsl'];
	}

	/**
	* Set the template associated with a tag
	*
	* @param string  $tagName Name of the tag
	* @param string  $tpl     Must be the contents of a valid <xsl:template> element
	* @param integer $flags
	*/
	public function setTagTemplate($tagName, $tpl, $flags = 0)
	{
		$tagName = $this->normalizeTagName($tagName);

		$xsl = '<xsl:template match="' . $tagName . '">'
		     . $tpl
		     . '</xsl:template>';

		$this->setTagXSL($tagName, $xsl, $flags);
	}

	/**
	* Set or replace the XSL associated with a tag
	*
	* @param string  $tagName Name of the tag
	* @param string  $xsl     Must be valid XSL elements. A root node is not required
	* @param integer $flags
	*/
	public function setTagXSL($tagName, $xsl, $flags = 0)
	{
		$tagName = $this->normalizeTagName($tagName);

		$this->tags[$tagName]['xsl'] = $this->normalizeXSL($xsl, $flags);
	}

	//==========================================================================
	// Plugins
	//==========================================================================

	/**
	* Get all loaded plugins
	*
	* @return array
	*/
	public function getLoadedPlugins()
	{
		$plugins = array();

		foreach (get_object_vars($this) as $k => $v)
		{
			if ($v instanceof PluginConfig)
			{
				$plugins[$k] = $v;
			}
		}

		return $plugins;
	}

	/**
	* Magic __get automatically loads plugins, PredefinedTags class
	*
	* @param  string $k Property name
	* @return mixed
	*/
	public function __get($k)
	{
		if ($k === 'predefinedTags')
		{
			if (!class_exists(__NAMESPACE__ . '\\PredefinedTags'))
			{
				include __DIR__ . '/PredefinedTags.php';
			}

			return $this->predefinedTags = new PredefinedTags($this);
		}

		if (preg_match('#^[A-Z][A-Za-z_0-9]+$#D', $k))
		{
			return $this->loadPlugin($k);
		}

		throw new RuntimeException('Undefined property: ' . __CLASS__ . '::$' . $k);
	}

	/**
	* Load a plugin
	*
	* If a plugin of the same name exists, it will be overwritten. This method knows how to load
	* core plugins. Otherwise, you have to include the appropriate files beforehand.
	*
	* @param  string $pluginName    Name of the plugin
	* @param  string $className     Name of the plugin's config class (required for custom plugins)
	* @param  array  $overrideProps Properties of the plugin will be overwritten with those
	* @return PluginConfig
	*/
	public function loadPlugin($pluginName, $className = null, array $overrideProps = array())
	{
		if (!preg_match('#^[A-Z][A-Za-z_0-9]+$#D', $pluginName))
		{
			throw new InvalidArgumentException('Invalid plugin name "' . $pluginName . '"');
		}

		$classFilepath = null;

		if (!isset($className))
		{
			$className = __NAMESPACE__ . '\\Plugins\\' . $pluginName . 'Config';
			$classFilepath = __DIR__ . '/Plugins/' . $pluginName . 'Config.php';
		}

		$useAutoload = !isset($classFilepath);

		/**
		* We test whether the class exists. If a filepath was provided, we disable autoload
		*/
		if (!class_exists($className, $useAutoload)
		 && isset($classFilepath))
		{
			/**
			* Load the PluginConfig abstract class if necessary
			*/
			if (!class_exists(__NAMESPACE__ . '\\PluginConfig', $useAutoload))
			{
				include __DIR__ . '/PluginConfig.php';
			}

			if (file_exists($classFilepath))
			{
				include $classFilepath;
			}
		}

		if (!class_exists($className))
		{
			throw new RuntimeException("Class '" . $className . "' not found");
		}

		return $this->$pluginName = new $className($this, $overrideProps);
	}

	//==========================================================================
	// Factories
	//==========================================================================

	/**
	* Return an instance of Parser based on the current config
	*
	* @return Parser
	*/
	public function getParser()
	{
		if (!class_exists(__NAMESPACE__ . '\\Parser'))
		{
			include __DIR__ . '/Parser.php';
		}

		return new Parser($this->getParserConfig());
	}

	/**
	* Return an instance of Renderer based on the current config
	*
	* @return Renderer
	*/
	public function getRenderer()
	{
		if (!class_exists(__NAMESPACE__ . '\\Renderer'))
		{
			include __DIR__ . '/Renderer.php';
		}

		return new Renderer($this->getXSL());
	}

	//==========================================================================
	// Filters
	//==========================================================================

	/**
	* Set the filter used to validate an attribute type
	*
	* @param string $filterType Attribute type this filter is in charge of
	* @param array  $filterConf Callback
	*/
	public function setFilter($filterType, array $filterConf)
	{
		if (!isset($filterConf['params']))
		{
			$filterConf['params'] = array('attrVal' => null);
		}

		if (!is_callable($filterConf['callback']))
		{
			throw new InvalidArgumentException('Not a callback');
		}

		$this->filters[$filterType] = $filterConf;
	}

	/**
	* Allow a URL scheme
	*
	* @param string $scheme URL scheme, e.g. "file" or "ed2k"
	*/
	public function allowScheme($scheme)
	{
		$this->filters['url']['allowedSchemes'][] = $scheme;
	}

	/**
	* Return the list of allowed URL schemes
	*
	* @return array
	*/
	public function getAllowedSchemes()
	{
		return $this->filters['url']['allowedSchemes'];
	}

	/**
	* Disallow a hostname (or hostname mask) from being used in URLs
	*
	* @param string $host Hostname or hostmask
	*/
	public function disallowHost($host)
	{
		$this->addHostmask('disallowedHosts', $host);
	}

	/**
	* Force URLs from given hostmask to be followed and resolved to their true location
	*
	* @param string $host Hostname or hostmask
	*/
	public function resolveRedirectsFrom($host)
	{
		$this->addHostmask('resolveRedirectsHosts', $host);
	}

	/**
	* @param string $host Hostname or hostmask
	*/
	protected function addHostmask($type, $host)
	{
		if (preg_match('#[\\x80-\xff]#', $host))
		{
			// @codeCoverageIgnoreStart
			if (!function_exists('idn_to_ascii'))
			{
				throw new RuntimeException('Cannot handle IDNs without the Intl PHP extension');
			}
			// @codeCoverageIgnoreEnd

			$host = idn_to_ascii($host);
		}

		/**
		* Transform "*.tld" and ".tld" into the functionally equivalent "tld"
		*
		* As a side-effect, when someone bans *.example.com it also bans example.com (no subdomain)
		* but that's usually what people were trying to achieve.
		*/
		$this->filters['url'][$type][] = ltrim($host, '*.');
	}

	//==========================================================================
	// Config
	//==========================================================================

	/**
	* Return the config needed by the global parser
	*
	* @return array
	*/
	public function getParserConfig()
	{
		return array(
			'filters' => $this->getFiltersConfig(),
			'plugins' => $this->getPluginsConfig(),
			'tags'    => $this->getTagsConfig(true)
		);
	}

	/**
	* Return the configs generated by plugins
	*
	* @param  string $method Either "getConfig" or "getJSConfig"
	* @return array
	*/
	public function getPluginsConfig($method = 'getConfig')
	{
		$config = array();

		foreach ($this->getLoadedPlugins() as $pluginName => $plugin)
		{
			$pluginConfig = $plugin->$method();

			if ($pluginConfig === false)
			{
				/**
				* This plugin is disabled
				*/
				continue;
			}

			/**
			* Add some default config if missing
			*/
			if (isset($pluginConfig['regexp']))
			{
				foreach (array('regexpLimit', 'regexpLimitAction') as $k)
				{
					if (!isset($pluginConfig[$k]))
					{
						$pluginConfig[$k] = $plugin->$k;
					}
				}
			}

			$config[$pluginName] = $pluginConfig;
		}

		return $config;
	}

	/**
	* Return the list of filters and their config
	*
	* @return array
	*/
	public function getFiltersConfig()
	{
		$filters = $this->filters;

		$filters['url']['allowedSchemes']
			= '#^' . self::buildRegexpFromList($filters['url']['allowedSchemes']) . '$#Di';

		foreach (array('disallowedHosts', 'resolveRedirectsHosts') as $k)
		{
			if (isset($filters['url'][$k]))
			{
				$filters['url'][$k]
					= '#(?<![^\\.])'
					. self::buildRegexpFromList(
						$filters['url'][$k],
						array('*' => '.*')
					  )
					. '$#DiS';
			}
		}

		return $filters;
	}

	/**
	* Return the tags' config, normalized and sorted, minus the tags' templates
	*
	* @param  bool  $reduce If true, remove unnecessary/empty entries and build the list of allowed
	*                       decendants for each tag
	* @return array
	*/
	public function getTagsConfig($reduce = false)
	{
		$tagsConfig = $this->tags;
		ksort($tagsConfig);

		$n = -1;

		foreach ($tagsConfig as $tagName => &$tagConfig)
		{
			if ($reduce)
			{
				if ($tagConfig['disable'])
				{
					// This tag is disabled, remove it
					unset($tagsConfig[$tagName]);
					continue;
				}

				$tagConfig['n'] = ++$n;

				/**
				* Build the list of allowed children and descendants.
				* Note: $tagsConfig is already sorted, so we don't have to sort the list
				*/
				$tagConfig['allowedChildren'] = array_fill_keys(
					array_keys($tagsConfig),
					($tagConfig['defaultChildRule'] === 'allow') ? '1' : '0'
				);
				$tagConfig['allowedDescendants'] = array_fill_keys(
					array_keys($tagsConfig),
					($tagConfig['defaultDescendantRule'] === 'allow') ? '1' : '0'
				);

				if (isset($tagConfig['rules']))
				{
					/**
					* Sort the rules so that "deny" overwrites "allow"
					*/
					ksort($tagConfig['rules']);

					foreach ($tagConfig['rules'] as $action => &$targets)
					{
						switch ($action)
						{
							case 'allowChild':
							case 'allowDescendant':
							case 'denyChild':
							case 'denyDescendant':
								/**
								* Those rules are converted into the allowedChildren and
								* allowedDescendants bitmaps
								*/
								$k = (substr($action, -5) === 'Child')
								   ? 'allowedChildren'
								   : 'allowedDescendants';

								$v = (substr($action, 0, 4) === 'deny') ? '0' : '1';

								foreach ($targets as $target)
								{
									// make sure the target really exists
									if (isset($tagConfig[$k][$target]))
									{
										$tagConfig[$k][$target] = $v;
									}
								}

								// We don't need those anymore
								unset($tagConfig['rules'][$action]);
								break;

							case 'requireParent':
							case 'requireAncestor':
								/**
								* Nothing to do here. If the target tag does not exist, this tag
								* will never be valid but we still leave it in the configuration
								*/
								break;

							default:
								// keep only the rules that target existing tags
								$targets = array_intersect_key($targets, $tagsConfig);
						}
					}
					unset($targets);

					/**
					* Remove rules with no targets
					*/
					$tagConfig['rules'] = array_filter($tagConfig['rules']);

					if (empty($tagConfig['rules']))
					{
						unset($tagConfig['rules']);
					}
				}

				unset($tagConfig['defaultChildRule']);
				unset($tagConfig['defaultDescendantRule']);
				unset($tagConfig['disable']);

				/**
				* We only need to store this option is it's true
				*/
				if (!$tagConfig['disallowAsRoot'])
				{
					unset($tagConfig['disallowAsRoot']);
				}

				/**
				* We don't need the tag's template
				*/
				unset($tagConfig['xsl']);

				/**
				* Generate a proper (binary) bitfield
				*/
				$tagConfig['allowedChildren'] = self::bin2raw($tagConfig['allowedChildren']);
				$tagConfig['allowedDescendants'] = self::bin2raw($tagConfig['allowedDescendants']);

				/**
				* Children are descendants of current node, so we apply denyDescendant rules to them
				* as well.
				*
				* @todo This largely overlaps with the replication of *Descendant rules into *Child
				*       rules in addTagRule() and should be looked into at some point
				*/
				$tagConfig['allowedChildren'] &= $tagConfig['allowedDescendants'];
			}

			ksort($tagConfig);
		}
		unset($tagConfig);

		return $tagsConfig;
	}

	static protected function bin2raw($values)
	{
		$bin = implode('', $values) . str_repeat('0', (((count($values) + 7) & 7) ^ 7));

		return implode('', array_map('chr', array_map('bindec', array_map('strrev', str_split($bin, 8)))));
	}

	//==========================================================================
	// Misc tools
	//==========================================================================

	/**
	* Create a regexp pattern that matches a list of words
	*
	* @param  array  $words Words to sort (UTF-8 expected)
	* @param  array  $esc   Array that caches how each individual characters should be escaped
	* @return string
	*/
	static public function buildRegexpFromList($words, array $esc = array())
	{
		// Sort the words to produce the same regexp regardless of the words' order
		sort($words);

		$initials = array();

		$arr = array();
		foreach ($words as $word)
		{
			if (preg_match_all('#.#us', $word, $matches))
			{
				/**
				* Store the initial for later
				*/
				$initials[$matches[0][0]] = true;

				$cur =& $arr;
				foreach ($matches[0] as $c)
				{
					if (!isset($esc[$c]))
					{
						$esc[$c] = preg_quote($c, '#');
					}

					$cur =& $cur[$esc[$c]];
				}
				$cur[''] = false;
			}
		}
		unset($cur);

		$regexp = '';

		/**
		* Test whether none of the initials has a special meaning
		*/
		if (count($initials) > 1)
		{
			$useLookahead = true;
			foreach ($initials as $initial => $void)
			{
				if ($esc[$initial] !== preg_quote($initial, '#'))
				{
					$useLookahead = false;
					break;
				}
			}

			if ($useLookahead)
			{
				$regexp .= '(?=[' . implode('', array_intersect_key($esc, $initials)) . '])';
			}
		}

		$regexp .= self::buildRegexpFromTrie($arr);

		return $regexp;
	}

	static protected function buildRegexpFromTrie($arr)
	{
		foreach (array('.*', '.*?') as $expr)
		{
			if (isset($arr[$expr])
			 && $arr[$expr] === array('' => false))
			{
				return $expr;
			}
		}

		$regexp = '';
		$suffix = '';
		$cnt    = count($arr);

		if (isset($arr['']))
		{
			unset($arr['']);

			if (empty($arr))
			{
				return '';
			}

			$suffix = '?';
		}

		/**
		* See if we can use a character class to produce [xy] instead of (?:x|y)
		*/
		$useCharacterClass = (bool) ($cnt > 1);
		foreach ($arr as $c => $sub)
		{
			/**
			* If this is not the last character, we can't use a character class
			*/
			if ($sub !== array('' => false))
			{
				$useCharacterClass = false;
				break;
			}

			/**
			* If this is a special character, we can't use a character class
			*/
			if ($c !== preg_quote(stripslashes($c), '#'))
			{
				$useCharacterClass = false;
				break;
			}
		}

		if ($useCharacterClass)
		{
			if ($cnt === 2 && $suffix)
			{
				/**
				* Produce x? instead of [x]?
				*/
				return implode('', array_keys($arr)) . $suffix;
			}

			return '[' . implode('', array_keys($arr)) . ']' . $suffix;
		}

		$sep = '';
		foreach ($arr as $c => $sub)
		{
			$regexp .= $sep . $c . self::buildRegexpFromTrie($sub);
			$sep = '|';
		}

		if ($cnt > 1)
		{
			return '(?:' . $regexp . ')' . $suffix;
		}

		return $regexp . $suffix;
	}

	/**
	* @param  string $regexp
	* @return array
	*/
	static public function parseRegexp($regexp)
	{
		if (!preg_match('#(.)(.*?)\\1([a-zA-Z]*)$#D', $regexp, $m))
		{
			throw new RuntimeException('Could not parse regexp delimiters');
		}

		$ret = array(
			'delimiter' => $m[1],
			'modifiers' => $m[3],
			'regexp' => $m[2],
			'tokens' => array()
		);

		$regexp = $m[2];

		$openSubpatterns = array();

		$pos = 0;
		$regexpLen = strlen($regexp);

		while ($pos < $regexpLen)
		{
			switch ($regexp[$pos])
			{
				case '\\':
					// skip next character
					$pos += 2;
					break;

				case '[':
					if (!preg_match('#\\[(.*?(?<!\\\\)(?:\\\\\\\\)*)\\]((?:[\\+\\*]\\+?)?)#', $regexp, $m, 0, $pos))
					{
						throw new RuntimeException('Could not find matching bracket from pos ' . $pos);
					}

					$ret['tokens'][] = array(
						'pos'         => $pos,
						'len'         => strlen($m[0]),
						'type'        => 'characterClass',
						'content'     => $m[1],
						'quantifiers' => $m[2]
					);

					$pos += strlen($m[0]);
					break;

				case '(';
					if (preg_match('#\\(\\?([a-z]*)\\)#i', $regexp, $m, 0, $pos))
					{
						/**
						* This is an option (?i) so we skip past the right parenthesis
						*/
						$ret['tokens'][] = array(
							'pos'     => $pos,
							'len'     => strlen($m[0]),
							'type'    => 'option',
							'options' => $m[1]
						);

						$pos += strlen($m[0]);
						break;
					}

					/**
					* This should be a subpattern, we just have to sniff which kind
					*/
					if (preg_match("#(?J)\\(\\?(?:P?<(?<name>[a-z]+)>|'(?<name>[a-z]+)')#", $regexp, $m, \PREG_OFFSET_CAPTURE, $pos))
					{
						/**
						* This is a named capture
						*/
						$tok = array(
							'pos'  => $pos,
							'len'  => strlen($m[0][0]),
							'type' => 'capturingSubpatternStart',
							'name' => $m['name'][0]
						);

						$pos += strlen($m[0][0]);
					}
					elseif (preg_match('#\\(\\?([a-z]*):#iA', $regexp, $m, 0, $pos))
					{
						/**
						* This is a non-capturing subpattern (?:xxx)
						*/
						$tok = array(
							'pos'     => $pos,
							'len'     => strlen($m[0]),
							'type'    => 'nonCapturingSubpatternStart',
							'options' => $m[1]
						);

						$pos += strlen($m[0]);
					}
					elseif (preg_match('#\\(\\?>#iA', $regexp, $m, 0, $pos))
					{
						/**
						* This is a non-capturing subpattern with atomic grouping (?>x+)
						*/
						$tok = array(
							'pos'     => $pos,
							'len'     => strlen($m[0]),
							'type'    => 'nonCapturingSubpatternStart',
							'subtype' => 'atomic'
						);

						$pos += strlen($m[0]);
					}
					elseif (preg_match('#\\(\\?(<?[!=])#A', $regexp, $m, 0, $pos))
					{
						/**
						* This is an assertion
						*/
						$assertions = array(
							'='  => 'lookahead',
							'<=' => 'lookbehind',
							'!'  => 'negativeLookahead',
							'<!' => 'negativeLookbehind'
						);

						$tok = array(
							'pos'     => $pos,
							'len'     => strlen($m[0]),
							'type'    => $assertions[$m[1]] . 'AssertionStart'
						);

						$pos += strlen($m[0]);
					}
					elseif (preg_match('#\\(\\?#A', $regexp, $m, 0, $pos))
					{
						throw new RuntimeException('Unsupported subpattern type at pos ' . $pos);
					}
					else
					{
						/**
						* This should be a normal capture
						*/
						$tok = array(
							'pos'  => $pos,
							'len'  => 1,
							'type' => 'capturingSubpatternStart'
						);

						++$pos;
					}

					$openSubpatterns[] = count($ret['tokens']);
					$ret['tokens'][] = $tok;
					break;

				case ')':
					if (empty($openSubpatterns))
					{
						throw new RuntimeException('Could not find matching pattern start for right parenthesis at pos ' . $pos);
					}

					$k = array_pop($openSubpatterns);
					$ret['tokens'][$k]['endToken'] = count($ret['tokens']);


					/**
					* Look for quantifiers after the subpattern, e.g. (?:ab)++
					*/
					$spn = strspn($regexp, '+*', 1 + $pos);
					$quantifiers = substr($regexp, 1 + $pos, $spn);

					$ret['tokens'][] = array(
						'pos'  => $pos,
						'len'  => 1 + $spn,
						'type' => substr($ret['tokens'][$k]['type'], 0, -5) . 'End',
						'quantifiers' => $quantifiers
					);

					$pos += 1 + $spn;
					break;

				default:
					++$pos;
			}
		}

		if (!empty($openSubpatterns))
		{
			throw new RuntimeException('Could not find matching pattern end for left parenthesis at pos ' . $ret['tokens'][$openSubpatterns[0]]['pos']);
		}

		return $ret;
	}

	//==========================================================================
	// XSL stuff
	//==========================================================================

	/**
	* Return the XSL used for rendering
	*
	* @param  string $prefix Prefix to use for XSL elements (defaults to "xsl")
	* @return string
	*/
	public function getXSL($prefix = 'xsl')
	{
		$xsl = '<?xml version="1.0" encoding="utf-8"?>'
		     . "\n"
			 . '<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">'
			 . '<xsl:output method="html" encoding="utf-8" omit-xml-declaration="yes" indent="no"/>'
			 . '<xsl:template match="/m">'
			 . '<xsl:for-each select="*">'
			 . '<xsl:apply-templates/>'
			 . '<xsl:if test="following-sibling::*"><xsl:value-of select="/m/@uid"/></xsl:if>'
			 . '</xsl:for-each>'
			 . '</xsl:template>';

		foreach ($this->tags as $tag)
		{
			if (isset($tag['xsl']))
			{
				$xsl .= $tag['xsl'];
			}
		}

		$xsl .= $this->xsl
		      . '<xsl:template match="st|et|i"/>'
		      . '</xsl:stylesheet>';

		if ($prefix !== 'xsl')
		{
			$trans = new DOMDocument;
			$trans->loadXML(
				'<?xml version="1.0" encoding="utf-8"?>
				<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:' . $prefix . '="http://www.w3.org/1999/XSL/Transform">

					<xsl:output method="xml" encoding="utf-8" />

					<xsl:template match="xsl:*">
						<xsl:element name="' . $prefix . ':{local-name()}" namespace="http://www.w3.org/1999/XSL/Transform">
							<xsl:copy-of select="@*" />
							<xsl:apply-templates />
						</xsl:element>
					</xsl:template>

					<xsl:template match="node()">
						<xsl:copy>
							<xsl:copy-of select="@*" />
							<xsl:apply-templates />
						</xsl:copy>
					</xsl:template>

				</xsl:stylesheet>'
			);

			$xslt = new XSLTProcessor;
			$xslt->importStylesheet($trans);

			$_xsl = new DOMDocument;
			$_xsl->loadXML($xsl);

			$xsl = rtrim($xslt->transformToXml($_xsl));
		}

		return $xsl;
	}

	/**
	* Add generic XSL
	*
	* This XSL will be output in the final stylesheet before tag-specific templates.
	*
	* @param string  $xsl     Must be valid XSL elements. A root node is not required
	* @param integer $flags
	*/
	public function addXSL($xsl, $flags = 0)
	{
		$this->xsl .= $this->normalizeXSL($xsl, $flags);
	}

	/**
	* Normalize XSL
	*
	* Check for well-formedness, remove whitespace if applicable.
	* Check for unsafe script tags.
	*
	* @param  string  $xsl     Must be valid XSL elements. A root node is not required
	* @param  integer $flags
	* @return string
	*/
	protected function normalizeXSL($xsl, $flags)
	{
		/**
		* Prepare a temporary stylesheet so that we can load the template and make sure it's valid
		*/
		$xsl = '<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform">'
		     . $xsl
		     . '</xsl:stylesheet>';

		/**
		* Load the stylesheet with libxml's internal errors temporarily enabled
		*/
		$useInternalErrors = libxml_use_internal_errors(true);

		$dom = new DOMDocument;
		$dom->preserveWhiteSpace = (bool) ($flags & self::PRESERVE_WHITESPACE);
		$res = $dom->loadXML($xsl);

		libxml_use_internal_errors($useInternalErrors);

		if (!$res)
		{
			$error = libxml_get_last_error();
			throw new InvalidArgumentException('Invalid XML - error was: ' . $error->message);
		}

		if (!($flags & self::ALLOW_UNSAFE_TEMPLATES))
		{
			$xpath = new DOMXPath($dom);

			$hasUnsafeScript = (bool) $xpath->evaluate(
				'count(
					//*[translate(name(), "SCRIPT", "script") = "script"]
					   [
					       @*[translate(name(), "SRC", "src") = "src"][contains(., "{")]
					    or .//xsl:value-of
					    or .//xsl:attribute
					   ]
				)'
			);

			if ($hasUnsafeScript)
			{
				throw new RuntimeException('It seems that your template contains a <script> tag that uses user-supplied information. Those can be unsafe and are disabled by default. Please use the ' . __CLASS__ . '::ALLOW_UNSAFE_TEMPLATES flag to enable it');
			}

			if ($xpath->evaluate('count(//@disable-output-escaping)'))
			{
				throw new RuntimeException("It seems that your template contains a 'disable-output-escaping' attribute. Those can be unsafe and are disabled by default. Please use the " . __CLASS__ . "::ALLOW_UNSAFE_TEMPLATES flag to enable it");
			}

			$attrs = $xpath->query(
				'//@*[starts-with(translate(name(), "ON", "on"), "on")][contains(., "{")]'
			);

			foreach ($attrs as $attr)
			{
				// test for false-positives, IOW escaped brackets
				preg_match_all('#\\{.#', $attr->value, $matches);

				foreach ($matches[0] as $m)
				{
					if ($m !== '{{')
					{
						throw new RuntimeException("It seems that your template contains at least one attribute named '" . $attr->name . "' using user-supplied content. Those can be unsafe and are disabled by default. Please use the " . __CLASS__ . "::ALLOW_UNSAFE_TEMPLATES flag to enable it");
					}
				}
			}

			$attrs = $xpath->query(
				// any xsl:attribute node that whose @name starts with "on" and has an
				// xsl:value-of or xsl:templates descendant
				'//xsl:attribute
					[starts-with(translate(@name, "ON", "on"), "on")]
					[//xsl:value-of or //xsl:apply-templates]'
			);

			foreach ($attrs as $attr)
			{
				throw new RuntimeException("It seems that your template contains at least one attribute named '" . $attr->getAttribute('name') . "' that is created dynamically. Those can be unsafe and are disabled by default. Please use the " . __CLASS__ . "::ALLOW_UNSAFE_TEMPLATES flag to enable it");
			}
		}

		/**
		* Rebuild the XSL by serializing and concatenating each of the root node's children
		*/
		$xsl = '';
		foreach ($dom->documentElement->childNodes as $childNode)
		{
			$xsl .= $dom->saveXML($childNode);
		}

		return $xsl;
	}

	//==========================================================================
	// Javascript parser stuff
	//==========================================================================

	/**
	* Return the Javascript parser that corresponds to this configuration
	*
	* @return string
	*/
	public function getJSParser(array $options = array())
	{
		include_once __DIR__ . '/JSParserGenerator.php';

		$jspg = new JSParserGenerator($this);

		return $jspg->get($options);
	}

	/**
	* Return JS parsers and their config
	*
	* @return array
	*/
	public function getJSPlugins()
	{
		$plugins = array();

		foreach ($this->getPluginsConfig('getJSConfig') as $pluginName => $pluginConfig)
		{
			$js = $this->$pluginName->getJSParser();

			if (!$js)
			{
				continue;
			}

			$plugins[$pluginName] = array(
				'parser' => $js,
				'config' => $pluginConfig,
				'meta'   => $this->$pluginName->getJSConfigMeta()
			);
		}

		return $plugins;
	}

	//==========================================================================
	// HTML guessing stuff
	//==========================================================================

	/**
	* What is this? you might ask. This is basically a compressed version of the HTML5 content
	* models, with some liberties taken.
	*
	* For each element, up to three bitfields are defined: "c", "ac" and "dd". Bitfields are stored
	* as a number for convenience.
	*
	* "c" represents the categories the element belongs to. The categories are comprised of HTML5
	* content models (such as "phrasing content" or "interactive content") plus a few special
	* categories created dynamically (part of the specs refer to "a group of X and Y elements"
	* rather than a specific content model, in which case a special category is formed for those
	* elements.)
	*
	* "ac" represents the categories that are allowed as children of given element.
	*
	* "dd" represents the categories that may not appear as a descendant of given element.
	*
	* Sometimes, HTML5 specifies some restrictions on when an element can accept certain children,
	* or what categories the element belongs to. For example, an <img> element is only part of the
	* "interactive content" category if it has a "usemap" attribute. Those restrictions are
	* expressed as an XPath expression and stored using the concatenation of the key of the bitfield
	* plus the bit number of the category. For instance, if "interactive content" got assigned to
	* bit 2, the definition of the <img> element will contain a key "c2" with value "@usemap".
	*
	* There is a special content model defined in HTML5, the "transparent" content model. If an
	* element uses the "transparent" content model, the key "t" is non-empty (set to 1.)
	*
	* In addition, HTML5 defines "optional end tag" rules, where one element automatically closes
	* its predecessor. Those are used to generate closeParent rules and are stored in the "cp" key.
	*/
	protected $htmlElements = array(
		'a'=>array('c'=>15,'ac'=>0,'dd'=>8,'t'=>1),
		'abbr'=>array('c'=>7,'ac'=>4),
		'address'=>array('c'=>1027,'ac'=>1,'dd'=>1552,'cp'=>array('p')),
		'area'=>array('c'=>5),
		'article'=>array('c'=>515,'ac'=>1,'cp'=>array('p')),
		'aside'=>array('c'=>515,'ac'=>1,'cp'=>array('p')),
		'audio'=>array('c'=>47,'c3'=>'@controls','c1'=>'@controls','ac'=>8192,'ac13'=>'@src','t'=>1),
		'b'=>array('c'=>7,'ac'=>4),
		'bdi'=>array('c'=>7,'ac'=>4),
		'bdo'=>array('c'=>7,'ac'=>4),
		'blockquote'=>array('c'=>259,'ac'=>1,'cp'=>array('p')),
		'br'=>array('c'=>5),
		'button'=>array('c'=>15,'ac'=>4,'dd'=>8),
		'canvas'=>array('c'=>39,'ac'=>0,'t'=>1),
		'caption'=>array('c'=>64,'ac'=>1,'dd'=>4194304),
		'cite'=>array('c'=>7,'ac'=>4),
		'code'=>array('c'=>7,'ac'=>4),
		'col'=>array('c'=>268435456,'c28'=>'not(@span)'),
		'colgroup'=>array('c'=>64,'ac'=>268435456,'ac28'=>'not(@span)'),
		'datalist'=>array('c'=>5,'ac'=>1048580),
		'dd'=>array('c'=>131072,'ac'=>1,'cp'=>array('dd','dt')),
		'del'=>array('c'=>5,'ac'=>0,'t'=>1),
		'details'=>array('c'=>267,'ac'=>524289),
		'dfn'=>array('c'=>134217735,'ac'=>4,'dd'=>134217728),
		'div'=>array('c'=>3,'ac'=>1,'cp'=>array('p')),
		'dl'=>array('c'=>3,'ac'=>131072,'cp'=>array('p')),
		'dt'=>array('c'=>131072,'ac'=>1,'dd'=>16912,'cp'=>array('dd','dt')),
		'em'=>array('c'=>7,'ac'=>4),
		'embed'=>array('c'=>47),
		'fieldset'=>array('c'=>259,'ac'=>2097153,'cp'=>array('p')),
		'figcaption'=>array('c'=>0x80000000,'ac'=>1),
		'figure'=>array('c'=>259,'ac'=>0x80000001),
		'footer'=>array('c'=>17411,'ac'=>1,'dd'=>16384,'cp'=>array('p')),
		'form'=>array('c'=>67108867,'ac'=>1,'dd'=>67108864,'cp'=>array('p')),
		'h1'=>array('c'=>147,'ac'=>4,'cp'=>array('p')),
		'h2'=>array('c'=>147,'ac'=>4,'cp'=>array('p')),
		'h3'=>array('c'=>147,'ac'=>4,'cp'=>array('p')),
		'h4'=>array('c'=>147,'ac'=>4,'cp'=>array('p')),
		'h5'=>array('c'=>147,'ac'=>4,'cp'=>array('p')),
		'h6'=>array('c'=>147,'ac'=>4,'cp'=>array('p')),
		'header'=>array('c'=>17411,'ac'=>1,'dd'=>16384,'cp'=>array('p')),
		'hgroup'=>array('c'=>19,'ac'=>128,'cp'=>array('p')),
		'hr'=>array('c'=>1,'cp'=>array('p')),
		'i'=>array('c'=>7,'ac'=>4),
		'img'=>array('c'=>47,'c3'=>'@usemap'),
		'input'=>array('c'=>15,'c3'=>'@type!="hidden"','c1'=>'@type!="hidden"'),
		'ins'=>array('c'=>7,'ac'=>0,'t'=>1),
		'kbd'=>array('c'=>7,'ac'=>4),
		'keygen'=>array('c'=>15),
		'label'=>array('c'=>33554447,'ac'=>4,'dd'=>33554432),
		'legend'=>array('c'=>2097152,'ac'=>4),
		'li'=>array('c'=>1073741824,'ac'=>1,'cp'=>array('li')),
		'map'=>array('c'=>7,'ac'=>0,'t'=>1),
		'mark'=>array('c'=>7,'ac'=>4),
		'menu'=>array('c'=>11,'c3'=>'@type="toolbar"','c1'=>'@type="toolbar" or @type="list"','ac'=>1073741825,'cp'=>array('p')),
		'meter'=>array('c'=>16779271,'ac'=>4,'dd'=>16777216),
		'nav'=>array('c'=>515,'ac'=>1,'cp'=>array('p')),
		'object'=>array('c'=>47,'c3'=>'@usemap','ac'=>8388608,'t'=>1),
		'ol'=>array('c'=>3,'ac'=>1073741824,'cp'=>array('p')),
		'optgroup'=>array('c'=>4096,'ac'=>1048576,'cp'=>array('optgroup','option')),
		'option'=>array('c'=>1052672,'cp'=>array('option')),
		'output'=>array('c'=>7,'ac'=>4),
		'p'=>array('c'=>3,'ac'=>4,'cp'=>array('p')),
		'param'=>array('c'=>8388608),
		'pre'=>array('c'=>3,'ac'=>4,'cp'=>array('p')),
		'progress'=>array('c'=>264199,'ac'=>4,'dd'=>262144),
		'q'=>array('c'=>7,'ac'=>4),
		'rp'=>array('c'=>65536,'ac'=>4,'cp'=>array('rp','rt')),
		'rt'=>array('c'=>65536,'ac'=>4,'cp'=>array('rp','rt')),
		'ruby'=>array('c'=>7,'ac'=>65540),
		's'=>array('c'=>7,'ac'=>4),
		'samp'=>array('c'=>7,'ac'=>4),
		'section'=>array('c'=>515,'ac'=>1,'cp'=>array('p')),
		'select'=>array('c'=>15,'ac'=>4096),
		'small'=>array('c'=>7,'ac'=>4),
		'source'=>array('c'=>8192,'c13'=>'not(@src)'),
		'span'=>array('c'=>7,'ac'=>4),
		'strong'=>array('c'=>7,'ac'=>4),
		'sub'=>array('c'=>7,'ac'=>4),
		'summary'=>array('c'=>524288,'ac'=>4),
		'sup'=>array('c'=>7,'ac'=>4),
		'table'=>array('c'=>4194307,'ac'=>64,'cp'=>array('p')),
		'tbody'=>array('c'=>64,'ac'=>536870912,'cp'=>array('tbody','tfoot','thead')),
		'td'=>array('c'=>33024,'ac'=>1,'cp'=>array('td','th')),
		'textarea'=>array('c'=>15),
		'tfoot'=>array('c'=>64,'ac'=>536870912,'cp'=>array('tbody','thead')),
		'th'=>array('c'=>32768,'ac'=>1,'dd'=>16912,'cp'=>array('td','th')),
		'thead'=>array('c'=>64,'ac'=>536870912),
		'time'=>array('c'=>7,'ac'=>4),
		'tr'=>array('c'=>536870976,'ac'=>32768,'cp'=>array('tr')),
		'track'=>array('c'=>8192,'c13'=>'@src'),
		'u'=>array('c'=>7,'ac'=>4),
		'ul'=>array('c'=>3,'ac'=>1073741824,'cp'=>array('p')),
		'var'=>array('c'=>7,'ac'=>4),
		'video'=>array('c'=>47,'c3'=>'@controls','ac'=>8192,'ac13'=>'@src','t'=>1),
		'wbr'=>array('c'=>5)
	);

	/**
	* Add rules generated from the HTML5 specs
	*
	* @param array $options Array of option settings, see generateRulesFromHTML5Specs()
	*/
	public function addRulesFromHTML5Specs(array $options = array())
	{
		foreach ($this->generateRulesFromHTML5Specs($options) as $tagName => $tagOptions)
		{
			$this->setTagOptions($tagName, $tagOptions);
		}
	}

	/**
	* Generate rules based on HTML5 content models
	*
	* We use the HTML5 specs to determine which children or descendants should be allowed or denied
	* based on HTML5 content models. While it does not exactly match HTML5 content models, it gets
	* pretty close. We also use HTML5 "optional end tag" rules to create closeParent rules.
	*
	* Currently, this method does not evaluate elements created with <xsl:element> correctly, or
	* attributes created with <xsl:attribute> and may never will due to the increased complexity it
	* would entail. Additionally, it does not evaluate the scope of <xsl:apply-templates/>. For
	* instance, it will treat <xsl:apply-templates select="LI"/> as if it was <xsl:apply-templates/>
	*
	* @link http://dev.w3.org/html5/spec/content-models.html#content-models
	* @link http://dev.w3.org/html5/spec/syntax.html#optional-tags
	* @see  ../scripts/patchConfigBuilder.php
	*
	* Possible options:
	*
	*  rootElement: name of the HTML element used as the root of the rendered text
	*
	* @param array $options Array of option settings
	* @return array
	*/
	public function generateRulesFromHTML5Specs(array $options = array())
	{
		$tagsConfig = $this->tags;

		if (isset($options['rootElement']))
		{
			if (!isset($this->htmlElements[$options['rootElement']]))
			{
				throw new InvalidArgumentException("Unknown HTML element '" . $options['rootElement'] . "'");
			}

			/**
			* Create a fake tag for our root element. "fake-root" is not a valid tag name so it
			* shouldn't conflict with any existing tag
			*/
			$rootTag = 'fake-root';

			$tagsConfig[$rootTag]['xsl'] =
				'<xsl:template match="' . $rootTag . '">
					<' . $options['rootElement'] . '>
						<xsl:apply-templates />
					</' . $options['rootElement'] . '>
				</xsl:template>';
		}

		$tagsInfo = array();
		foreach ($tagsConfig as $tagName => $tagConfig)
		{
			$tagInfo = array(
				'lastChildren' => array()
			);

			$tagInfo['root'] = simplexml_load_string(
				'<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform">' . $tagConfig['xsl'] . '</xsl:stylesheet>'
			);

			/**
			* Get every HTML element with no HTML ancestor
			*/
			$tagInfo['firstChildren'] = $tagInfo['root']->xpath('//*[namespace-uri() != "http://www.w3.org/1999/XSL/Transform"][not(ancestor::*[namespace-uri() != "http://www.w3.org/1999/XSL/Transform"])]');

			/**
			* Compute the category bitfield of every first element
			*/
			$tagInfo['firstChildrenCategoryBitfield'] = array();

			foreach ($tagInfo['firstChildren'] as $firstChild)
			{
				$tagInfo['firstChildrenCategoryBitfield'][]
					= $this->filterHTMLRulesBitfield($firstChild->getName(), 'c', $firstChild);
			}

			/**
			* Get every HTML element from this tag's template(s) and generate a bitfield that
			* represents all the content models in use
			*/
			$tagInfo['usedCategories'] = 0;

			foreach ($tagInfo['root']->xpath('//*[namespace-uri() != "http://www.w3.org/1999/XSL/Transform"]') as $node)
			{
				$tagInfo['usedCategories'] |= $this->filterHTMLRulesBitfield($node->getName(), 'c', $node);
			}

			/**
			* For each <xsl:apply-templates/> element, iterate over all the HTML ancestors, compute
			* the allowChildBitfields and denyDescendantBitfield values, and save the last HTML
			* child of the branch
			*/
			$tagInfo['denyDescendantBitfield'] = 0;

			foreach ($tagInfo['root']->xpath('//xsl:apply-templates') as $at)
			{
				$allowChildBitfield = null;

				foreach ($at->xpath('ancestor::*[namespace-uri() != "http://www.w3.org/1999/XSL/Transform"]') as $node)
				{
					$elName = $node->getName();

					if (empty($this->htmlElements[$elName]['t']))
					{
						/**
						* If this element does not use the transparent content model, we discard its
						* parent's bitfield
						*/
						$allowChildBitfield = 0;

						$tagInfo['isTransparent'] = false;
					}
					elseif (!isset($allowChildBitfield))
					{
						/**
						* If this element uses the transparent content model and this is the first
						* HTML element of this template, we reuse its category bitfield. It's not
						* exactly how it should work though, as at this point we don't know what
						* category enabled this tag
						*/
						$allowChildBitfield
							= $this->filterHTMLRulesBitfield($elName, 'c', $node);

						/**
						* Accumulate the denied descendants
						*/
						$tagInfo['denyDescendantBitfield'] |= $this->filterHTMLRulesBitfield($elName, 'dd', $node);

						if (!isset($tagInfo['isTransparent']))
						{
							$tagInfo['isTransparent'] = true;
						}
					}

					$allowChildBitfield
						|= $this->filterHTMLRulesBitfield($elName, 'ac', $node);
				}

				$tagInfo['allowChildBitfields'][] = $allowChildBitfield;
				$tagInfo['lastChildren'][] = $node;
			}

			$tagsInfo[$tagName] = $tagInfo;
		}

		$tagsOptions = array();

		/**
		* Generate closeParent rules
		*/
		foreach ($tagsInfo as $tagName => $tagInfo)
		{
			if (!empty($tagInfo['isTransparent']))
			{
				$tagsOptions[$tagName]['isTransparent'] = true;
			}

			foreach ($tagInfo['firstChildren'] as $firstChild)
			{
				$elName = $firstChild->getName();

				if (!isset($this->htmlElements[$elName]['cp']))
				{
					continue;
				}

				foreach ($tagsInfo as $targetName => $targetInfo)
				{
					foreach ($targetInfo['lastChildren'] as $lastChild)
					{
						if (in_array($lastChild->getName(), $this->htmlElements[$elName]['cp'], true))
						{
							$tagsOptions[$tagName]['rules']['closeParent'][] = $targetName;
						}
					}
				}
			}
		}

		/**
		* Generate allowChild/denyChild rules
		*/
		foreach ($tagsInfo as $tagName => $tagInfo)
		{
			/**
			* If this tag allows no children, we deny every one of them
			*/
			if (empty($tagInfo['allowChildBitfields']))
			{
				foreach ($tagsInfo as $targetName => $targetInfo)
				{
					$tagsOptions[$tagName]['rules']['denyChild'][] = $targetName;
				}

				continue;
			}

			foreach ($tagInfo['allowChildBitfields'] as $allowChildBitfield)
			{
				foreach ($tagsInfo as $targetName => $targetInfo)
				{
					foreach ($targetInfo['firstChildrenCategoryBitfield'] as $firstChildBitfield)
					{
						$action = ($allowChildBitfield & $firstChildBitfield)
								? 'allowChild'
								: 'denyChild';

						$tagsOptions[$tagName]['rules'][$action][] = $targetName;
					}
				}
			}
		}

		/**
		* Generate denyDescendant rules
		*/
		foreach ($tagsInfo as $tagName => $tagInfo)
		{
			foreach ($tagsInfo as $targetName => $targetInfo)
			{
				if ($tagInfo['denyDescendantBitfield'] & $targetInfo['usedCategories'])
				{
					$tagsOptions[$tagName]['rules']['denyDescendant'][] = $targetName;
				}
			}
		}

		/**
		* Sets the options related to the root element
		*/
		if (isset($options['rootElement']))
		{
			/**
			* Tags that cannot be a child of our root tag gets the disallowAsRoot option
			*/
			if (isset($tagsOptions[$rootTag]['rules']['denyChild']))
			{
				foreach ($tagsOptions[$rootTag]['rules']['denyChild'] as $tagName)
				{
					$tagsOptions[$tagName]['disallowAsRoot'] = true;
				}
			}

			/**
			* Tags that cannot be a descendant of our root tag gets the disable option
			*/
			if (isset($tagsOptions[$rootTag]['rules']['denyDescendant']))
			{
				foreach ($tagsOptions[$rootTag]['rules']['denyDescendant'] as $tagName)
				{
					$tagsOptions[$tagName]['disable'] = true;
				}
			}

			/**
			* Now remove any mention of our root tag from the return array
			*/
			unset($tagsOptions[$rootTag]);

			foreach ($tagsOptions as &$tagOptions)
			{
				if (isset($tagOptions['rules']))
				{
					foreach ($tagOptions['rules'] as $rule => $targets)
					{
						/**
						* First we flip the target so we can unset the fake tag by key, then we
						* flip them back, which rearranges their keys as a side-effect
						*/
						$targets = array_flip($targets);
						unset($targets[$rootTag]);
						$tagOptions['rules'][$rule] = array_flip($targets);
					}
				}
			}
			unset($tagOptions);
		}

		/**
		* Deduplicate rules and resolve conflicting rules
		*/
		$precedence = array(
			array('denyDescendant', 'denyChild'),
			array('denyDescendant', 'allowChild'),
			array('denyChild', 'allowChild')
		);

		foreach ($tagsOptions as $tagName => &$tagOptions)
		{
			// flip the rules targets
			$tagOptions['rules'] = array_map('array_flip', $tagOptions['rules']);

			// apply precedence, e.g. if there's a denyChild rule, remove any allowChild rules
			foreach ($precedence as $pair)
			{
				list($k1, $k2) = $pair;

				if (!isset($tagOptions['rules'][$k1], $tagOptions['rules'][$k2]))
				{
					continue;
				}

				$tagOptions['rules'][$k2] = array_diff_key(
					$tagOptions['rules'][$k2],
					$tagOptions['rules'][$k1]
				);
			}

			// flip the rules again
			$tagOptions['rules'] = array_map('array_keys', $tagOptions['rules']);

			// remove empty rules
			$tagOptions['rules'] = array_filter($tagOptions['rules']);
		}
		unset($tagOptions);

		return $tagsOptions;
	}

	/**
	* Filter a bitfield according to its context node
	*
	* @param  string           $elName Name of the HTML element
	* @param  string           $k      Bitfield name: either 'c', 'ac' or 'dd'
	* @param  SimpleXMLElement $node   Context node
	* @return integer
	*/
	protected function filterHTMLRulesBitfield($elName, $k, SimpleXMLElement $node)
	{
		if (empty($this->htmlElements[$elName][$k]))
		{
			return 0;
		}

		$bitfield = $this->htmlElements[$elName][$k];

		foreach (str_split(strrev(decbin($bitfield)), 1) as $n => $v)
		{
			if (!$v)
			{
				continue;
			}

			if (isset($this->htmlElements[$elName][$k . $n])
			 && !$node->xpath($this->htmlElements[$elName][$k . $n]))
			{
				$bitfield ^= 1 << $n;
			}
		}

		return $bitfield;
	}
}