<?php

namespace s9e\TextFormatter\Tests\Configurator\RendererGenerators\PHP;

use DOMDocument;
use Exception;
use RuntimeException;
use s9e\TextFormatter\Configurator\RendererGenerators\PHP\Serializer;
use s9e\TextFormatter\Tests\Test;

/**
* @covers s9e\TextFormatter\Configurator\RendererGenerators\PHP\Serializer
*/
class SerializerTest extends Test
{
	/**
	* @dataProvider getConvertXPathTests
	* @testdox convertXPath() tests
	*/
	public function testConvertXPath($original, $expected, $setup = null)
	{
		$serializer = new Serializer;

		if (isset($setup))
		{
			$setup($serializer);
		}

		$this->assertSame($expected, $serializer->convertXPath($original));
	}

	/**
	* @dataProvider getConvertConditionTests
	* @testdox convertCondition() tests
	*/
	public function testConvertCondition($original, $expected, $setup = null)
	{
		$serializer = new Serializer;

		if (isset($setup))
		{
			$setup($serializer);
		}

		$this->assertSame($expected, $serializer->convertCondition($original));
	}

	public function getConvertXPathTests()
	{
		return [
			[
				'@bar',
				"\$node->getAttribute('bar')"
			],
			[
				'.',
				"\$node->textContent"
			],
			[
				'$foo',
				"\$this->params['foo']"
			],
			[
				"'foo'",
				"'foo'"
			],
			[
				'"foo"',
				"'foo'"
			],
			[
				'local-name()',
				'$node->localName'
			],
			[
				'name()',
				'$node->nodeName'
			],
			[
				'123',
				"'123'"
			],
			[
				'normalize-space(@bar)',
				"\$this->xpath->evaluate('normalize-space(@bar)',\$node)"
			],
			[
				'string-length(@bar)',
				"\$this->xpath->evaluate('string-length(@bar)',\$node)"
			],
			[
				'string-length(@bar)',
				"mb_strlen(\$node->getAttribute('bar'),'utf-8')",
				function ($serializer)
				{
					$serializer->useMultibyteStringFunctions = true;
				}
			],
			[
				'string-length()',
				"\$this->xpath->evaluate('string-length()',\$node)"
			],
			[
				'string-length()',
				"mb_strlen(\$node->textContent,'utf-8')",
				function ($serializer)
				{
					$serializer->useMultibyteStringFunctions = true;
				}
			],
			[
				'substring(.,1,2)',
				"\$this->xpath->evaluate('substring(.,1,2)',\$node)"
			],
			[
				'substring(.,1,2)',
				"mb_substr(\$node->textContent,0,2,'utf-8')",
				function ($serializer)
				{
					$serializer->useMultibyteStringFunctions = true;
				}
			],
			[
				'substring(.,0,2)',
				"\$this->xpath->evaluate('substring(.,0,2)',\$node)"
			],
			[
				// NOTE: as per XPath specs, the length is adjusted to the negative position
				'substring(.,0,2)',
				"mb_substr(\$node->textContent,0,1,'utf-8')",
				function ($serializer)
				{
					$serializer->useMultibyteStringFunctions = true;
				}
			],
			[
				'substring(.,@x,1)',
				"\$this->xpath->evaluate('substring(.,@x,1)',\$node)"
			],
			[
				'substring(.,@x,1)',
				"mb_substr(\$node->textContent,max(0,\$node->getAttribute('x')-1),1,'utf-8')",
				function ($serializer)
				{
					$serializer->useMultibyteStringFunctions = true;
				}
			],
			[
				'substring(.,1,@x)',
				"\$this->xpath->evaluate('substring(.,1,@x)',\$node)"
			],
			[
				'substring(.,1,@x)',
				"mb_substr(\$node->textContent,0,max(0,\$node->getAttribute('x')),'utf-8')",
				function ($serializer)
				{
					$serializer->useMultibyteStringFunctions = true;
				}
			],
			[
				'substring(.,2)',
				"\$this->xpath->evaluate('substring(.,2)',\$node)"
			],
			[
				'substring(.,2)',
				"mb_substr(\$node->textContent,1,null,'utf-8')",
				function ($serializer)
				{
					$serializer->useMultibyteStringFunctions = true;
				}
			],
			[
				'translate(@bar,"abc","ABC")',
				"strtr(\$node->getAttribute('bar'),'abc','ABC')"
			],
			[
				'translate(@bar,"abc","ABC")',
				"strtr(\$node->getAttribute('bar'),'abc','ABC')"
			],
			[
				'translate(@bar,"éè","ÉÈ")',
				"strtr(\$node->getAttribute('bar'),['é'=>'É','è'=>'È'])"
			],
			[
				'translate(@bar,"ab","ABC")',
				"strtr(\$node->getAttribute('bar'),'ab','AB')"
			],
			[
				'translate(@bar,"abcd","AB")',
				"strtr(\$node->getAttribute('bar'),['a'=>'A','b'=>'B','c'=>'','d'=>''])"
			],
			[
				'translate(@bar,"abbd","ABCD")',
				"strtr(\$node->getAttribute('bar'),'abd','ABD')"
			],
			// Custom representations
			[
				"substring('songWw',6-5*boolean(@songid),5)",
				"(\$node->hasAttribute('songid')?'songW':'w')"
			],
			[
				'400-360*boolean(@songid)',
				"(\$node->hasAttribute('songid')?40:400)"
			],
			// Math
			[
				'@foo + 12',
				"\$node->getAttribute('foo')+12",
				function ()
				{
					if (version_compare(PCRE_VERSION, '8.13', '<'))
					{
						$this->markTestSkipped('This optimization requires PCRE 8.13 or newer');
					}
				}
			],
			[
				'44 + $bar',
				"44+\$this->params['bar']",
				function ()
				{
					if (version_compare(PCRE_VERSION, '8.13', '<'))
					{
						$this->markTestSkipped('This optimization requires PCRE 8.13 or newer');
					}
				}
			],
			[
				'@h * 3600 + @m * 60 + @s',
				"\$node->getAttribute('h')*3600+\$node->getAttribute('m')*60+\$node->getAttribute('s')",
				function ()
				{
					if (version_compare(PCRE_VERSION, '8.13', '<'))
					{
						$this->markTestSkipped('This optimization requires PCRE 8.13 or newer');
					}
				}
			],
		];
	}

	public function getConvertConditionTests()
	{
		return [
			[
				'@foo',
				"\$node->hasAttribute('foo')"
			],
			[
				'not(@foo)',
				"!\$node->hasAttribute('foo')"
			],
			[
				'$foo',
				"!empty(\$this->params['foo'])"
			],
			[
				'not($foo)',
				"empty(\$this->params['foo'])"
			],
			[
				".='foo'",
				"\$node->textContent==='foo'"
			],
			[
				"@foo='foo'",
				"\$node->getAttribute('foo')==='foo'"
			],
			[
				".='fo\"o'",
				"\$node->textContent==='fo\"o'"
			],
			[
				'.=\'"_"\'',
				'$node->textContent===\'"_"\''
			],
			[
				".='foo'or.='bar'",
				"\$node->textContent==='foo'||\$node->textContent==='bar'"
			],
			[
				'.=3',
				"\$node->textContent==3"
			],
			[
				'.=022',
				"\$node->textContent==22"
			],
			[
				'044=.',
				"44==\$node->textContent"
			],
			[
				'@foo != @bar',
				"\$node->getAttribute('foo')!==\$node->getAttribute('bar')"
			],
			[
				'@foo = @bar or @baz',
				"\$node->getAttribute('foo')===\$node->getAttribute('bar')||\$node->hasAttribute('baz')"
			],
			[
				'not(@foo) and @bar',
				"!\$node->hasAttribute('foo')&&\$node->hasAttribute('bar')"
			],
			[
				'not(@foo and @bar)',
				"!(\$node->hasAttribute('foo')&&\$node->hasAttribute('bar'))",
				function ()
				{
					if (version_compare(PCRE_VERSION, '8.13', '<'))
					{
						// Not exactly sure of the oldest version that doesn't segault
						$this->markTestSkipped('This optimization requires PCRE 8.13 or newer');
					}
				}
			],
			[
				".='x'or.='y'or.='z'",
				"\$node->textContent==='x'||\$node->textContent==='y'||\$node->textContent==='z'"
			],
			[
				"contains(@foo,'x')",
				"(strpos(\$node->getAttribute('foo'),'x')!==false)"
			],
			[
				" contains( @foo , 'x' ) ",
				"(strpos(\$node->getAttribute('foo'),'x')!==false)"
			],
			[
				"not(contains(@id, 'bar'))",
				"(strpos(\$node->getAttribute('id'),'bar')===false)"
			],
			[
				"starts-with(@foo,'bar')",
				"(strpos(\$node->getAttribute('foo'),'bar')===0)"
			],
			[
				'@foo and (@bar or @baz)',
				"\$node->hasAttribute('foo')&&(\$node->hasAttribute('bar')||\$node->hasAttribute('baz'))",
				function ()
				{
					if (version_compare(PCRE_VERSION, '8.13', '<'))
					{
						$this->markTestSkipped('This optimization requires PCRE 8.13 or newer');
					}
				}
			],
			[
				'(@a = @b) or (@b = @c)',
				"(\$node->getAttribute('a')===\$node->getAttribute('b'))||(\$node->getAttribute('b')===\$node->getAttribute('c'))",
				function ()
				{
					if (version_compare(PCRE_VERSION, '8.13', '<'))
					{
						$this->markTestSkipped('This optimization requires PCRE 8.13 or newer');
					}
				}
			],
			// Custom representations
			[
				"contains('upperlowerdecim',substring(@type,1,5))",
				"strpos('upperlowerdecim',substr(\$node->getAttribute('type'),0,5))!==false"
			],
		];
	}

	/**
	* @testdox serialize() tests
	* @dataProvider getSerializeTests
	*/
	public function testSerialize($xml, $expected, $branchTables = [])
	{
		$ir = new DOMDocument;
		$ir->preserveWhiteSpace = false;
		$ir->loadXML($xml);

		$serializer = new Serializer;

		if ($expected instanceof Exception)
		{
			$this->setExpectedException(get_class($expected), $expected->getMessage());
		}

		$this->assertSame($expected, $serializer->serialize($ir->documentElement));
		$this->assertSame($branchTables, $serializer->branchTables);
	}

	public function getSerializeTests()
	{
		return [
			[
				'<template outputMethod="html">
					<switch branch-key="@foo">
						<case branch-values=\'["1"]\' test="@foo = 1">
							<output escape="text" type="literal">1</output>
						</case>
						<case branch-values=\'["2"]\' test="@foo = 2">
							<output escape="text" type="literal">2</output>
						</case>
						<case branch-values=\'["3"]\' test="@foo = 3">
							<output escape="text" type="literal">3</output>
						</case>
						<case branch-values=\'["4"]\' test="4 = @foo">
							<output escape="text" type="literal">4</output>
						</case>
						<case branch-values=\'["5"]\' test="5 = @foo">
							<output escape="text" type="literal">5</output>
						</case>
						<case branch-values=\'["6"]\' test="@foo = 6">
							<output escape="text" type="literal">6</output>
						</case>
						<case branch-values=\'["7"]\' test="@foo = 7">
							<output escape="text" type="literal">7</output>
						</case>
						<case branch-values=\'["8"]\' test="@foo = 8">
							<output escape="text" type="literal">8</output>
						</case>
						<case>
							<output escape="text" type="literal">default</output>
						</case>
					</switch>
				</template>',
				"if(isset(self::\$bt13027555[\$node->getAttribute('foo')])){\$n=self::\$bt13027555[\$node->getAttribute('foo')];if(\$n<4){if(\$n===0){\$this->out.='1';}elseif(\$n===1){\$this->out.='2';}elseif(\$n===2){\$this->out.='3';}else{\$this->out.='4';}}elseif(\$n===4){\$this->out.='5';}elseif(\$n===5){\$this->out.='6';}elseif(\$n===6){\$this->out.='7';}else{\$this->out.='8';}}else{\$this->out.='default';}",
				['bt13027555' => [1=>0,2=>1,3=>2,4=>3,5=>4,6=>5,7=>6,8=>7]]
			],
			[
				'<template><closeTag id="1"/></template>',
				new RuntimeException
			],
			[
				'<template><hash/></template>',
				new RuntimeException
			],
			[
				'<template outputMethod="html">
					<switch branch-key="@foo">
						<case branch-values=\'["1"]\' test="@foo = 1">
							<output escape="text" type="literal">1</output>
						</case>
						<case branch-values=\'["2"]\' test="@foo = 2">
							<output escape="text" type="literal">2</output>
						</case>
						<case branch-values=\'["3"]\' test="@foo = 3">
							<output escape="text" type="literal">3</output>
						</case>
						<case branch-values=\'["4"]\' test="4 = @foo">
							<output escape="text" type="literal">4</output>
						</case>
						<case branch-values=\'["5"]\' test="5 = @foo">
							<output escape="text" type="literal">5</output>
						</case>
						<case branch-values=\'["6"]\' test="@foo = 6">
							<output escape="text" type="literal">6</output>
						</case>
						<case branch-values=\'["7"]\' test="@foo = 7">
							<output escape="text" type="literal">7</output>
						</case>
						<case branch-values=\'["8","44"]\' test="@foo = 8 or @foo = 44">
							<output escape="text" type="literal">8</output>
						</case>
					</switch>
				</template>',
				"if(isset(self::\$bt7794ED46[\$node->getAttribute('foo')])){\$n=self::\$bt7794ED46[\$node->getAttribute('foo')];if(\$n<4){if(\$n===0){\$this->out.='1';}elseif(\$n===1){\$this->out.='2';}elseif(\$n===2){\$this->out.='3';}else{\$this->out.='4';}}elseif(\$n===4){\$this->out.='5';}elseif(\$n===5){\$this->out.='6';}elseif(\$n===6){\$this->out.='7';}else{\$this->out.='8';}}",
				['bt7794ED46' => [1=>0,2=>1,3=>2,4=>3,5=>4,6=>5,7=>6,8=>7,44=>7]]
			],
		];
	}
}