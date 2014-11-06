<?php

/*
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2014 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\Configurator\TemplateNormalizations;

use DOMElement;
use DOMXPath;
use s9e\TextFormatter\Configurator\TemplateNormalization;

class ConvertCurlyExpressionsInText extends TemplateNormalization
{
	public function normalize(DOMElement $template)
	{
		$dom   = $template->ownerDocument;
		$xpath = new DOMXPath($dom);
		$query = '//text()[contains(., "{@") or contains(., "{$")]';

		foreach ($xpath->query($query) as $node)
		{
			$parentNode = $node->parentNode;

			if ($parentNode->namespaceURI === 'http://www.w3.org/1999/XSL/Transform')
				continue;

			\preg_match_all(
				'#\\{([$@][-\\w]+)\\}#',
				$node->textContent,
				$matches,
				2 | 256
			);

			$lastPos = 0;
			foreach ($matches as $m)
			{
				$pos = $m[0][1];

				if ($pos > $lastPos)
					$parentNode->insertBefore(
						$dom->createTextNode(
							\substr($node->textContent, $lastPos, $pos - $lastPos)
						),
						$node
					);
				$lastPos = $pos + \strlen($m[0][0]);

				$parentNode
					->insertBefore(
						$dom->createElementNS(
							'http://www.w3.org/1999/XSL/Transform',
							'xsl:value-of'
						),
						$node
					)
					->setAttribute('select', $m[1][0]);
			}

			$text = \substr($node->textContent, $lastPos);
			if ($text > '')
				$parentNode->insertBefore($dom->createTextNode($text), $node);

			$parentNode->removeChild($node);
		}
	}
}