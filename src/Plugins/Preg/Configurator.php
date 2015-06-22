<?php

/**
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2015 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\Plugins\Preg;

use DOMAttr;
use DOMText;
use DOMXPath;
use Exception;
use InvalidArgumentException;
use s9e\TextFormatter\Configurator\Helpers\RegexpParser;
use s9e\TextFormatter\Configurator\Helpers\TemplateHelper;
use s9e\TextFormatter\Configurator\Items\Tag;
use s9e\TextFormatter\Configurator\Items\Variant;
use s9e\TextFormatter\Configurator\JavaScript\RegexpConvertor;
use s9e\TextFormatter\Plugins\ConfiguratorBase;

class Configurator extends ConfiguratorBase
{
	/**
	* @var array[] Captures from current regexp
	*/
	protected $captures;

	/**
	* @var array Array of [tagName => [regexp, passthroughIdx]]
	*/
	protected $collection = [];

	/**
	* @var string Delimiter used in current regexp
	*/
	protected $delimiter;

	/**
	* @var string Non-D modifiers used in current regexp
	*/
	protected $modifiers;

	/**
	* @var array References used in current template
	*/
	protected $references;

	/**
	* @var string Regexp used to find references in the templates. We check that the reference is
	*             not preceded with an odd number of backslashes
	*/
	protected $referencesRegexp = '((?<!\\\\)(?:\\\\\\\\)*\\K(?:[$\\\\]\\d+|\\$\\{\\d+\\}))S';

	/**
	* {@inheritdoc}
	*/
	public function asConfig()
	{
		if (!count($this->collection))
		{
			return;
		}

		$pregs   = [];
		$jsPregs = [];
		foreach ($this->collection as $tagName => list($regexp, $passthroughIdx))
		{
			$pregs[] = [$tagName, $regexp, $passthroughIdx];

			if (isset($this->configurator->javascript))
			{
				$jsRegexp = RegexpConvertor::toJS($regexp);
				$jsRegexp->flags .= 'g';

				$jsPregs[] = [$tagName, $jsRegexp, $passthroughIdx, $jsRegexp->map];
			}
		}

		$variant = new Variant($pregs);
		if (isset($this->configurator->javascript))
		{
			$variant->set('JS', $jsPregs);
		}

		return ['generics' => $variant];
	}

	/**
	* Configure a pattern-based replacement
	*
	* @param  string $regexp   Regexp to be used by the parser
	* @param  string $template Template to be used for rendering
	* @param  string $tagName  Name of the tag to create. A name based on the regexp is
	*                          automatically generated if none is provided
	* @return Tag              The tag created to represent this replacement
	*/
	public function replace($regexp, $template, $tagName = null)
	{
		if (!isset($tagName))
		{
			$tagName = 'PREG_' . strtoupper(dechex(crc32($regexp)));
		}
		$this->parseRegexp($regexp);
		$this->parseTemplate($template);

		$passthrough = $this->getPassthroughCapture();
		if ($passthrough)
		{
			$this->captures[$passthrough]['passthrough'] = true;
		}

		$regexp   = $this->fixUnnamedCaptures($regexp);
		$template = $this->convertTemplate($template, $passthrough);

		$this->collection[$tagName] = [$regexp, $passthrough];

		return $this->createTag($tagName, $template);
	}

	/**
	* Add given attribute to given tag based on parsed captures
	*
	* @param  Tag    $tag
	* @param  string $attrName
	* @return void
	*/
	protected function addAttribute(Tag $tag, $attrName)
	{
		$isUrl = false;
		$exprs = [];
		foreach ($this->captures as $key => $capture)
		{
			if ($capture['name'] !== $attrName)
			{
				continue;
			}
			$exprs[] = $capture['expr'];
			if (isset($this->references['asUrl'][$key]))
			{
				$isUrl = true;
			}
		}
		$exprs = array_unique($exprs);

		$regexp = $this->delimiter . '^';
		$regexp .= (count($exprs) === 1) ? $exprs[0] : '(?:' . implode('|', $exprs) . ')';
		$regexp .= '$' . $this->delimiter . 'D' . $this->modifiers;

		$attribute = $tag->attributes->add($attrName);

		$filter = $this->configurator->attributeFilters['#regexp'];
		$filter->setRegexp($regexp);
		$attribute->filterChain[] = $filter;

		if ($isUrl)
		{
			$filter = $this->configurator->attributeFilters['#url'];
			$attribute->filterChain[] = $filter;
		}
	}

	/**
	* Convert a preg-style replacement to a template
	*
	* @param  string  $template    Original template
	* @param  integer $passthrough Index of the passthrough capture
	* @return string               Modified template
	*/
	protected function convertTemplate($template, $passthrough)
	{
		// Replace numeric references in the template with the value of the corresponding attribute
		// values or passthrough
		$template = TemplateHelper::replaceTokens(
			$template,
			$this->referencesRegexp,
			function ($m, $node) use ($passthrough)
			{
				$key = (int) trim($m[0], '\\${}');
				if ($key === 0)
				{
					// $0 copies the whole textContent
					return ['expression', '.'];
				}
				if ($key === $passthrough && $node instanceof DOMText)
				{
					// Passthrough capture, does not include start/end tags
					return ['passthrough'];
				}
				if (isset($this->captures[$key]['name']))
				{
					// Normal capture, replaced by the equivalent expression
					return ['expression', '@' . $this->captures[$key]['name']];
				}

				// Non-existent captures are simply ignored, similarly to preg_replace()
				return ['literal', ''];
			}
		);

		// Unescape backslashes and special characters in the template
		$template = TemplateHelper::replaceTokens(
			$template,
			'(\\\\+[0-9${\\\\])',
			function ($m)
			{
				return ['literal', stripslashes($m[0])];
			}
		);

		return $template;
	}

	/**
	* Create the tag that matches current regexp
	*
	* @param  string $tagName
	* @param  string $template
	* @return Tag
	*/
	protected function createTag($tagName, $template)
	{
		$tag = new Tag;
		foreach ($this->captures as $key => $capture)
		{
			if (!isset($capture['name']))
			{
				continue;
			}

			$attrName = $capture['name'];
			if (isset($tag->attributes[$attrName]))
			{
				continue;
			}

			$this->addAttribute($tag, $attrName);
		}
		$tag->template = $template;

		// Normalize the tag's template
		$this->configurator->templateNormalizer->normalizeTag($tag);

		// Check the safeness of this tag
		$this->configurator->templateChecker->checkTag($tag);

		return $this->configurator->tags->add($tagName, $tag);
	}

	/**
	* Give a name to unnamed captures that are referenced in current replacement
	*
	* @param  string $regexp Original regexp
	* @return string         Modified regexp
	*/
	protected function fixUnnamedCaptures($regexp)
	{
		$keys = [];
		foreach ($this->references['anywhere'] as $key)
		{
			$capture = $this->captures[$key];
			if (!$key || isset($capture['name']))
			{
				continue;
			}
			// Give the capture a name if it's used as URL or it's not a passthrough
			if (isset($this->references['asUrl'][$key]) || !isset($capture['passthrough']))
			{
				$keys[] = $key;
			}
		}

		// Alter the original regexp to inject the subpatterns' names. The position is equal to the
		// subpattern's position plus 2, to account for the delimiter at the start of the regexp and
		// the opening parenthesis of the subpattern. Also, we need to process them in reverse order
		// so that replacements don't change the position of subsequent subpatterns
		rsort($keys);
		foreach ($keys as $key)
		{
			$name   = '_' . $key;
			$pos    = $this->captures[$key]['pos'];
			$regexp = substr_replace($regexp, "?'" . $name . "'", 2 + $pos, 0);
			$this->captures[$key]['name'] = $name;
		}

		return $regexp;
	}

	/**
	* Get the index of the capture used for passthrough in current replacement
	*
	* @return integer
	*/
	protected function getPassthroughCapture()
	{
		$passthrough = 0;
		foreach ($this->references['inText'] as $key)
		{
			if (!preg_match('(^\\.[*+]\\??$)D', $this->captures[$key]['expr']))
			{
				// Ignore if it's not a catch-all expression such as .*?
				continue;
			}
			if ($passthrough)
			{
				// Abort if there's more than 1 possible passthrough
				$passthrough = 0;
				break;
			}
			$passthrough = (int) $key;
		}

		return $passthrough;
	}

	/**
	* Parse given regexp and store its information
	*
	* @param  string  $regexp
	* @return void
	*/
	protected function parseRegexp($regexp)
	{
		$valid = false;
		try
		{
			$valid = @preg_match_all($regexp, '', $m);
		}
		catch (Exception $e)
		{
			// Nothing to do here
		}
		if ($valid === false)
		{
			throw new InvalidArgumentException('Invalid regexp');
		}

		$this->captures = [['name' => null, 'expr' => null]];
		$regexpInfo = RegexpParser::parse($regexp);
		$this->delimiter = $regexpInfo['delimiter'];
		$this->modifiers = str_replace('D', '', $regexpInfo['modifiers']);
		foreach ($regexpInfo['tokens'] as $token)
		{
			if ($token['type'] !== 'capturingSubpatternStart')
			{
				continue;
			}
			$this->captures[] = [
				'pos'    => $token['pos'],
				'name'   => (isset($token['name'])) ? $token['name'] : null,
				'expr'   => $token['content']
			];
		}
	}

	/**
	* Parse given template and store the references it contains
	*
	* @param  string $template
	* @return void
	*/
	protected function parseTemplate($template)
	{
		$this->references = [
			'anywhere' => [],
			'asUrl'    => [],
			'inText'   => []
		];

		preg_match_all($this->referencesRegexp, $template, $matches);
		foreach ($matches[0] as $match)
		{
			$key = trim($match, '\\${}');
			$this->references['anywhere'][$key] = $key;
		}

		$dom   = TemplateHelper::loadTemplate($template);
		$xpath = new DOMXPath($dom);
		foreach ($xpath->query('//text()') as $node)
		{
			preg_match_all($this->referencesRegexp, $node->textContent, $matches);
			foreach ($matches[0] as $match)
			{
				$key = trim($match, '\\${}');
				$this->references['inText'][$key] = $key;
			}
		}

		foreach (TemplateHelper::getURLNodes($dom) as $node)
		{
			// We only bother with literal attributes that start with a capture
			if ($node instanceof DOMAttr
			 && preg_match('(^(?:[$\\\\]\\d+|\\$\\{\\d+\\}))', trim($node->value), $m))
			{
				$key = trim($m[0], '\\${}');
				$this->references['asUrl'][$key] = $key;
			}
		}

		$this->removeUnknownReferences();
	}

	/**
	* Remove references that do not correspond to an existing capture
	*
	* @return void
	*/
	protected function removeUnknownReferences()
	{
		foreach ($this->references as &$references)
		{
			$references = array_intersect_key($references, $this->captures);
		}
	}
}