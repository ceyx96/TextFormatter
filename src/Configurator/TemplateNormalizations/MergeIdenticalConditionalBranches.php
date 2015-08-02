<?php

/**
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2015 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\Configurator\TemplateNormalizations;

use DOMElement;
use DOMNode;
use s9e\TextFormatter\Configurator\Helpers\TemplateParser;
use s9e\TextFormatter\Configurator\TemplateNormalization;

class MergeIdenticalConditionalBranches extends TemplateNormalization
{
	/**
	* Merge xsl:when branches if they have identical content
	*
	* NOTE: may fail if branches have identical equality expressions, e.g. "@a=1" and "@a=1"
	*
	* @param  DOMElement $template <xsl:template/> node
	* @return void
	*/
	public function normalize(DOMElement $template)
	{
		foreach ($template->getElementsByTagNameNS(self::XMLNS_XSL, 'choose') as $choose)
		{
			self::mergeCompatibleBranches($choose);
			self::mergeConsecutiveBranches($choose);
		}
	}

	/**
	* Inspect the branches of an xsl:choose element and merge branches if their content is identical
	* and their order does not matter
	*
	* @param  DOMElement $choose xsl:choose element
	* @return void
	*/
	protected static function mergeCompatibleBranches(DOMElement $choose)
	{
		$node = $choose->firstChild;
		while ($node)
		{
			$nodes = self::collectCompatibleBranches($node);

			if (count($nodes) > 1)
			{
				$node = end($nodes)->nextSibling;

				// Try to merge branches if there's more than one of them
				self::mergeBranches($nodes);
			}
			else
			{
				$node = $node->nextSibling;
			}
		}
	}

	/**
	* Inspect the branches of an xsl:choose element and merge consecutive branches if their content
	* is identical
	*
	* @param  DOMElement $choose xsl:choose element
	* @return void
	*/
	protected static function mergeConsecutiveBranches(DOMElement $choose)
	{
		// Try to merge consecutive branches even if their test conditions are not compatible,
		// e.g. "@a=1" and "@b=2"
		$nodes = [];
		foreach ($choose->childNodes as $node)
		{
			if (self::isXslWhen($node))
			{
				$nodes[] = $node;
			}
		}

		$i = count($nodes);
		while (--$i > 0)
		{
			self::mergeBranches([$nodes[$i - 1], $nodes[$i]]);
		}
	}

	/**
	* Collect consecutive xsl:when elements that share the same kind of equality tests
	*
	* Will return xsl:when elements that test a constant part (e.g. a literal) against the same
	* variable part (e.g. the same attribute)
	*
	* @param  DOMNode      $node First node to inspect
	* @return DOMElement[]
	*/
	protected static function collectCompatibleBranches(DOMNode $node)
	{
		$nodes  = [];
		$key    = null;
		$values = [];

		while ($node && self::isXslWhen($node))
		{
			$branch = TemplateParser::parseEqualityExpr($node->getAttribute('test'));

			if ($branch === false || count($branch) !== 1)
			{
				// The expression is not entirely composed of equalities, or they have a different
				// variable part
				break;
			}

			if (isset($key) && key($branch) !== $key)
			{
				// Not the same variable as our branches
				break;
			}

			if (array_intersect($values, end($branch)))
			{
				// Duplicate values across branches, e.g. ".=1 or .=2" and ".=2 or .=3"
				break;
			}

			$key    = key($branch);
			$values = array_merge($values, end($branch));

			// Record this node then move on to the next sibling
			$nodes[] = $node;
			$node    = $node->nextSibling;
		}

		return $nodes;
	}

	/**
	* Merge identical xsl:when elements from a list
	*
	* @param  DOMElement[] $nodes
	* @return void
	*/
	protected static function mergeBranches(array $nodes)
	{
		$sortedNodes = [];
		foreach ($nodes as $node)
		{
			$outerXML = $node->ownerDocument->saveXML($node);
			$innerXML = preg_replace('([^>]+>(.*)<[^<]+)s', '$1', $outerXML);

			$sortedNodes[$innerXML][] = $node;
		}

		foreach ($sortedNodes as $identicalNodes)
		{
			if (count($identicalNodes) < 2)
			{
				continue;
			}

			$expr = [];
			foreach ($identicalNodes as $i => $node)
			{
				$expr[] = $node->getAttribute('test');

				if ($i > 0)
				{
					$node->parentNode->removeChild($node);
				}
			}

			$identicalNodes[0]->setAttribute('test', implode(' or ', $expr));
		}
	}

	/**
	* Test whether a node is an xsl:when element
	*
	* @param  DOMNode $node
	* @return boolean
	*/
	protected static function isXslWhen(DOMNode $node)
	{
		return ($node->namespaceURI === self::XMLNS_XSL && $node->localName === 'when');
	}
}