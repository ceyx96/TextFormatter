<?php

namespace s9e\TextFormatter\Tests\Plugins;

use s9e\TextFormatter\Tests\Test;

include_once __DIR__ . '/../../src/autoloader.php';

/**
* @covers s9e\TextFormatter\Plugins\AutolinkParser
*/
class AutolinkParserTest extends Test
{
	public function setUp()
	{
		$this->cb->loadPlugin('Autolink');
	}

	/**
	* @test
	*/
	public function HTTP_urls_are_linkified_by_default()
	{
		$this->assertTransformation(
			'Go to http://www.example.com for more info',
			'<rt>Go to <URL url="http://www.example.com">http://www.example.com</URL> for more info</rt>',
			'Go to <a href="http://www.example.com">http://www.example.com</a> for more info'
		);
	}

	/**
	* @test
	*/
	public function HTTPS_urls_are_linkified_by_default()
	{
		$this->assertTransformation(
			'Go to https://www.example.com for more info',
			'<rt>Go to <URL url="https://www.example.com">https://www.example.com</URL> for more info</rt>',
			'Go to <a href="https://www.example.com">https://www.example.com</a> for more info'
		);
	}

	/**
	* @test
	*/
	public function FTP_urls_are_not_linkified_by_default()
	{
		$this->assertTransformation(
			'Go to ftp://www.example.com for more info',
			'<pt>Go to ftp://www.example.com for more info</pt>',
			'Go to ftp://www.example.com for more info'
		);
	}

	/**
	* @test
	*/
	public function FTP_urls_are_linkified_if_the_scheme_has_been_allowed_in_configBuilder()
	{
		$this->cb->allowScheme('ftp');

		$this->assertTransformation(
			'Go to ftp://www.example.com for more info',
			'<rt>Go to <URL url="ftp://www.example.com">ftp://www.example.com</URL> for more info</rt>',
			'Go to <a href="ftp://www.example.com">ftp://www.example.com</a> for more info'
		);
	}

	/**
	* @test
	* @depends HTTP_urls_are_linkified_by_default
	*/
	public function Trailing_dots_are_not_linkified()
	{
		$this->assertTransformation(
			'Go to http://www.example.com. Or the kitten dies.',
			'<rt>Go to <URL url="http://www.example.com">http://www.example.com</URL>. Or the kitten dies.</rt>',
			'Go to <a href="http://www.example.com">http://www.example.com</a>. Or the kitten dies.'
		);
	}

	/**
	* @test
	* @depends HTTP_urls_are_linkified_by_default
	*/
	public function Trailing_punctuation_is_not_linkified()
	{
		$this->assertTransformation(
			'Go to http://www.example.com! Or the kitten dies.',
			'<rt>Go to <URL url="http://www.example.com">http://www.example.com</URL>! Or the kitten dies.</rt>',
			'Go to <a href="http://www.example.com">http://www.example.com</a>! Or the kitten dies.'
		);
	}

	/**
	* @test
	* @depends Trailing_punctuation_is_not_linkified
	*/
	public function Trailing_slash_is_linkified()
	{
		$this->assertTransformation(
			'Go to http://www.example.com/!',
			'<rt>Go to <URL url="http://www.example.com/">http://www.example.com/</URL>!</rt>',
			'Go to <a href="http://www.example.com/">http://www.example.com/</a>!'
		);
	}

	/**
	* @test
	* @depends Trailing_punctuation_is_not_linkified
	*/
	public function Trailing_equal_sign_is_linkified()
	{
		$this->assertTransformation(
			'Go to http://www.example.com/?q=!',
			'<rt>Go to <URL url="http://www.example.com/?q=">http://www.example.com/?q=</URL>!</rt>',
			'Go to <a href="http://www.example.com/?q=">http://www.example.com/?q=</a>!'
		);
	}

	/**
	* @test
	* @depends HTTP_urls_are_linkified_by_default
	*/
	public function Balanced_right_parentheses_are_linkified()
	{
		$this->assertTransformation(
			'Mars (http://en.wikipedia.org/wiki/Mars_(planet)) is the fourth planet from the Sun',
			'<rt>Mars (<URL url="http://en.wikipedia.org/wiki/Mars_(planet)">http://en.wikipedia.org/wiki/Mars_(planet)</URL>) is the fourth planet from the Sun</rt>',
			'Mars (<a href="http://en.wikipedia.org/wiki/Mars_(planet)">http://en.wikipedia.org/wiki/Mars_(planet)</a>) is the fourth planet from the Sun'
		);
	}

	/**
	* @test
	* @depends HTTP_urls_are_linkified_by_default
	*/
	public function Non_balanced_right_parentheses_are_not_linkified()
	{
		$this->assertTransformation(
			'Mars (http://en.wikipedia.org/wiki/Mars) can mean many things',
			'<rt>Mars (<URL url="http://en.wikipedia.org/wiki/Mars">http://en.wikipedia.org/wiki/Mars</URL>) can mean many things</rt>',
			'Mars (<a href="http://en.wikipedia.org/wiki/Mars">http://en.wikipedia.org/wiki/Mars</a>) can mean many things'
		);
	}

	/**
	* @test
	* @link http://area51.phpbb.com/phpBB/viewtopic.php?p=203955#p203955
	*/
	public function IDNs_are_linkified()
	{
		$this->assertTransformation(
			'http://www.xn--lyp-plada.com for http://www.älypää.com',
			'<rt><URL url="http://www.xn--lyp-plada.com">http://www.xn--lyp-plada.com</URL> for <URL url="http://www.xn--lyp-plada.com">http://www.älypää.com</URL></rt>',
			'<a href="http://www.xn--lyp-plada.com">http://www.xn--lyp-plada.com</a> for <a href="http://www.xn--lyp-plada.com">http://www.älypää.com</a>'
		);
	}

	/**
	* @test
	* @link http://area51.phpbb.com/phpBB/viewtopic.php?p=203955#p203955
	*/
	public function URLs_with_non_ASCII_chars_are_linkified()
	{
		$this->assertTransformation(
			'http://en.wikipedia.org/wiki/Matti_Nyk%C3%A4nen for http://en.wikipedia.org/wiki/Matti_Nykänen',
			'<rt><URL url="http://en.wikipedia.org/wiki/Matti_Nyk%C3%A4nen">http://en.wikipedia.org/wiki/Matti_Nyk%C3%A4nen</URL> for <URL url="http://en.wikipedia.org/wiki/Matti_Nyk%C3%A4nen">http://en.wikipedia.org/wiki/Matti_Nykänen</URL></rt>',
			'<a href="http://en.wikipedia.org/wiki/Matti_Nyk%C3%A4nen">http://en.wikipedia.org/wiki/Matti_Nyk%C3%A4nen</a> for <a href="http://en.wikipedia.org/wiki/Matti_Nyk%C3%A4nen">http://en.wikipedia.org/wiki/Matti_Nykänen</a>'
		);
	}

	/**
	* @test
	*/
	public function URLs_that_end_with_a_non_ASCII_char_are_linkified()
	{
		$this->assertTransformation(
			'Check this out http://en.wikipedia.org/wiki/♥',
			'<rt>Check this out <URL url="http://en.wikipedia.org/wiki/%E2%99%A5">http://en.wikipedia.org/wiki/&#x2665;</URL></rt>',
			'Check this out <a href="http://en.wikipedia.org/wiki/%E2%99%A5">http://en.wikipedia.org/wiki/♥</a>'
		);
	}

	/**
	* @test
	*/
	public function URLs_that_contain_an_empty_pair_of_square_brackets_are_linkified()
	{
		$this->assertTransformation(
			'Check those out: http://example.com/list.php?cat[]=1&cat[]=2',
			'<rt>Check those out: <URL url="http://example.com/list.php?cat[]=1&amp;cat[]=2">http://example.com/list.php?cat[]=1&amp;cat[]=2</URL></rt>',
			'Check those out: <a href="http://example.com/list.php?cat%5B%5D=1&amp;cat%5B%5D=2">http://example.com/list.php?cat[]=1&amp;cat[]=2</a>'
		);
	}

	/**
	* @test
	*/
	public function URLs_that_contain_pair_of_square_brackets_that_contain_ASCII_letters_and_digits_are_linkified()
	{
		$this->assertTransformation(
			'Check those out: http://example.com/list.php?cat[1a]=1&cat[1b]=2',
			'<rt>Check those out: <URL url="http://example.com/list.php?cat[1a]=1&amp;cat[1b]=2">http://example.com/list.php?cat[1a]=1&amp;cat[1b]=2</URL></rt>',
			'Check those out: <a href="http://example.com/list.php?cat%5B1a%5D=1&amp;cat%5B1b%5D=2">http://example.com/list.php?cat[1a]=1&amp;cat[1b]=2</a>'
		);
	}

	/**
	* @test
	*/
	public function Ignores_the_right_square_bracket_of_a_BBCode_tag()
	{
		$this->assertTransformation(
			'[url=http://example.com]Non-existent URL tag[/url]',
			'<rt>[url=<URL url="http://example.com">http://example.com</URL>]Non-existent URL tag[/url]</rt>',
			'[url=<a href="http://example.com">http://example.com</a>]Non-existent URL tag[/url]'
		);
	}
}