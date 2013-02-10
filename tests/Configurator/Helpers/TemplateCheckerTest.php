<?php

namespace s9e\TextFormatter\Tests\Configurator\Helpers;

use s9e\TextFormatter\Configurator\Items\AttributeFilter;
use s9e\TextFormatter\Configurator\Items\AttributeFilters\Email;
use s9e\TextFormatter\Configurator\Items\AttributeFilters\Number;
use s9e\TextFormatter\Configurator\Items\AttributeFilters\Url;
use s9e\TextFormatter\Configurator\Items\Tag;
use s9e\TextFormatter\Configurator\Helpers\TemplateChecker;
use s9e\TextFormatter\Configurator\Helpers\TemplateOptimizer;
use s9e\TextFormatter\Tests\Test;

/**
* @covers s9e\TextFormatter\Configurator\Helpers\TemplateChecker
*/
class TemplateCheckerTest extends Test
{
	/**
	* @testdox checkUnsafe() throws an exception on invalid XML
	* @expectedException s9e\TextFormatter\Configurator\Exceptions\InvalidXslException
	* @expectedExceptionMessage Premature end of data in tag
	*/
	public function testUnsafeInvalidXML()
	{
		TemplateChecker::checkUnsafe('<xsl:copy>');
	}

	/**
	* @testdox checkUnsafe() throws an exception if the template contains a <?php instruction
	* @expectedException s9e\TextFormatter\Configurator\Exceptions\UnsafeTemplateException
	* @expectedExceptionMessage PHP tags are not allowed
	*/
	public function testPHPTag()
	{
		TemplateChecker::checkUnsafe('<x><?php ?></x>');
	}

	/**
	* @testdox checkUnsafe() throws an exception if the template contains a <?PHP instruction
	* @expectedException s9e\TextFormatter\Configurator\Exceptions\UnsafeTemplateException
	* @expectedExceptionMessage PHP tags are not allowed
	*/
	public function testPHPTagCaseInsensitive()
	{
		TemplateChecker::checkUnsafe('<x><?PHP ?></x>');
	}

	/**
	* @testdox checkUnsafe() throws an exception if the template generates a <?php instruction
	* @expectedException s9e\TextFormatter\Configurator\Exceptions\UnsafeTemplateException
	* @expectedExceptionMessage PHP tags are not allowed
	*/
	public function testPHPTagOutput()
	{
		TemplateChecker::checkUnsafe('<x><xsl:processing-instruction name="php"/></x>');
	}

	/**
	* @testdox checkUnsafe() throws an exception if the template generates a <?PHP instruction
	* @expectedException s9e\TextFormatter\Configurator\Exceptions\UnsafeTemplateException
	* @expectedExceptionMessage PHP tags are not allowed
	*/
	public function testPHPTagCaseOutputInsensitive()
	{
		TemplateChecker::checkUnsafe('<x><xsl:processing-instruction name="PHP"/></x>');
	}

	/**
	* @testdox checkUnsafe() throws an exception if the template generates a dynamic processing instruction
	* @expectedException s9e\TextFormatter\Configurator\Exceptions\UnsafeTemplateException
	* @expectedExceptionMessage Dynamic processing instructions are not allowed
	*/
	public function testDynamicProcessingInstruction()
	{
		TemplateChecker::checkUnsafe('<x><xsl:processing-instruction name="{@foo}"/></x>');
	}

	/**
	* @testdox checkUnsafe() throws an exception if an element has an "use-attribute-sets" attribute
	* @expectedException s9e\TextFormatter\Configurator\Exceptions\UnsafeTemplateException
	* @expectedExceptionMessage Cannot assess the safety of attribute sets
	*/
	public function testAttributeSet()
	{
		TemplateChecker::checkUnsafe('<b use-attribute-sets="foo"/>');
	}

	/**
	* @testdox Not safe: <x:element name="script" xmlns:x="http://www.w3.org/1999/XSL/Transform"><xsl:apply-templates/></x:element>
	* @expectedException s9e\TextFormatter\Configurator\Exceptions\UnsafeTemplateException
	* @expectedExceptionMessage A dynamically generated 'script' element lets unfiltered data through
	*/
	public function testCustomNamespacePrefix()
	{
		TemplateChecker::checkUnsafe('<x:element name="script" xmlns:x="http://www.w3.org/1999/XSL/Transform"><xsl:apply-templates/></x:element>');
	}

	/**
	* @testdox Not safe: <element name="script" xmlns="http://www.w3.org/1999/XSL/Transform"><apply-templates/></element>
	* @expectedException s9e\TextFormatter\Configurator\Exceptions\UnsafeTemplateException
	* @expectedExceptionMessage A dynamically generated 'script' element lets unfiltered data through
	*/
	public function testDefaultNamespace()
	{
		TemplateChecker::checkUnsafe('<element name="script" xmlns="http://www.w3.org/1999/XSL/Transform"><apply-templates/></element>');
	}

	/**
	* @testdox Safe if attribute 'email' has filter #email: <a href="mailto:{@email}"/>
	*/
	public function testMailto()
	{
		$this->checkUnsafe(
			'<a href="mailto:{@email}"/>',
			NULL,
			['attributes' => ['email' => ['filterChain' => [new Email]]]]
		);
	}

	/**
	* @testdox Safe if attribute 'email' has filter #email: <a href="mailto:{@email}?subject=foo"/>
	*/
	public function testMailtoSubject()
	{
		$this->checkUnsafe(
			'<a href="mailto:{@email}?subject=foo"/>',
			NULL,
			['attributes' => ['email' => ['filterChain' => [new Email]]]]
		);
	}

	/**
	* @testdox Not safe even if attribute 'email' has filter #email: <a href="http://{@email}"/>
	* @expectedException s9e\TextFormatter\Configurator\Exceptions\UnsafeTemplateException
	* @expectedExceptionMessage Attribute 'email' is not properly filtered to be used in URL
	*/
	public function testMailtoUnsafe()
	{
		$this->checkUnsafe(
			'<a href="http://{@email}"/>',
			NULL,
			['attributes' => ['email' => ['filterChain' => [new Email]]]]
		);
	}

	// Start of content generated by ../../../scripts/patchTemplateCheckerTest.php
	/**
	* @testdox Not safe: <embed src="{@url}"/>
	*/
	public function testCheckUnsafeFFEA6CBF()
	{
		$this->runCheckUnsafeCase(0);
	}

	/**
	* @testdox Not safe: <embed src="{@url}" allowscriptaccess="always"/>
	*/
	public function testCheckUnsafe85F4F19A()
	{
		$this->runCheckUnsafeCase(1);
	}

	/**
	* @testdox Not safe: <embed src="{@url}" allowscriptaccess="sameDomain"/>
	*/
	public function testCheckUnsafe50932DE0()
	{
		$this->runCheckUnsafeCase(2);
	}

	/**
	* @testdox Safe if attribute 'url' has filter '#url': <embed src="{@url}" allowscriptaccess="never"/>
	*/
	public function testCheckUnsafe7704636D()
	{
		$this->runCheckUnsafeCase(3);
	}

	/**
	* @testdox Not safe: <iframe src="{@url}"/>
	*/
	public function testCheckUnsafeA56D0DBC()
	{
		$this->runCheckUnsafeCase(4);
	}

	/**
	* @testdox Not safe: <object data="{@url}"/>
	*/
	public function testCheckUnsafe200651EB()
	{
		$this->runCheckUnsafeCase(5);
	}

	/**
	* @testdox Safe if attribute 'url' has filter '#url': <object data="{@url}"><param name="allowscriptaccess" value="never"/></object>
	*/
	public function testCheckUnsafe25E1DCEC()
	{
		$this->runCheckUnsafeCase(6);
	}

	/**
	* @testdox Not safe: <script src="{@url}"/>
	*/
	public function testCheckUnsafeFDDAD6DB()
	{
		$this->runCheckUnsafeCase(7);
	}

	/**
	* @testdox Not safe if attribute 'src' has filter '#url': <script src="{@url}"/>
	*/
	public function testCheckUnsafe5E3B5499()
	{
		$this->runCheckUnsafeCase(8);
	}

	/**
	* @testdox Not safe: <script src="http://{@foo}"/>
	*/
	public function testCheckUnsafe4B4CB598()
	{
		$this->runCheckUnsafeCase(9);
	}

	/**
	* @testdox Safe if attribute 'id' has filter '#number': <script src="https://gist.github.com/{@id}.js"/>
	*/
	public function testCheckUnsafe2C8EEEB9()
	{
		$this->runCheckUnsafeCase(10);
	}

	/**
	* @testdox Safe if attribute 'id' has filter '#number': <script src="//gist.github.com/{@id}.js"/>
	*/
	public function testCheckUnsafe6272AD84()
	{
		$this->runCheckUnsafeCase(11);
	}

	/**
	* @testdox Safe: <script src="foo.js"/>
	*/
	public function testCheckUnsafe48B39041()
	{
		$this->runCheckUnsafeCase(12);
	}

	/**
	* @testdox Not safe: <SCRIPT src="{@url}"/>
	*/
	public function testCheckUnsafe22C6B53D()
	{
		$this->runCheckUnsafeCase(13);
	}

	/**
	* @testdox Not safe: <script SRC="{@url}"/>
	*/
	public function testCheckUnsafe4C83A1C2()
	{
		$this->runCheckUnsafeCase(14);
	}

	/**
	* @testdox Not safe: <script><xsl:attribute name="src"><xsl:value-of select="@url"/><?dont-optimize?></xsl:attribute></script>
	*/
	public function testCheckUnsafe959E6486()
	{
		$this->runCheckUnsafeCase(15);
	}

	/**
	* @testdox Not safe: <script><xsl:attribute name="SRC"><xsl:value-of select="@url"/><?dont-optimize?></xsl:attribute></script>
	*/
	public function testCheckUnsafeB8B19B4E()
	{
		$this->runCheckUnsafeCase(16);
	}

	/**
	* @testdox Safe: <script><xsl:attribute name="src">http://example.org/legit.js<?dont-optimize?></xsl:attribute></script>
	*/
	public function testCheckUnsafe9C1DD25F()
	{
		$this->runCheckUnsafeCase(17);
	}

	/**
	* @testdox Safe: <script src="http://example.org/legit.js"><xsl:attribute name="id"><xsl:value-of select="foo"/><?dont-optimize?></xsl:attribute></script>
	*/
	public function testCheckUnsafeF8806A69()
	{
		$this->runCheckUnsafeCase(18);
	}

	/**
	* @testdox Not safe: <script src="http://example.org/legit.js"><xsl:attribute name="src"><xsl:value-of select="@hax"/><?dont-optimize?></xsl:attribute></script>
	*/
	public function testCheckUnsafe1A457E75()
	{
		$this->runCheckUnsafeCase(19);
	}

	/**
	* @testdox Not safe: <xsl:element name="script"><xsl:attribute name="src"><xsl:value-of select="@url"/><?dont-optimize?></xsl:attribute></xsl:element>
	*/
	public function testCheckUnsafeF6856A91()
	{
		$this->runCheckUnsafeCase(20);
	}

	/**
	* @testdox Not safe: <xsl:element name="SCRIPT"><xsl:attribute name="src"><xsl:value-of select="@url"/><?dont-optimize?></xsl:attribute></xsl:element>
	*/
	public function testCheckUnsafe5C2DA78D()
	{
		$this->runCheckUnsafeCase(21);
	}

	/**
	* @testdox Not safe: <object><param name="movie" value="{@url}"/></object>
	*/
	public function testCheckUnsafe75997FF6()
	{
		$this->runCheckUnsafeCase(22);
	}

	/**
	* @testdox Not safe: <OBJECT><PARAM NAME="MOVIE" VALUE="{@url}"/></OBJECT>
	*/
	public function testCheckUnsafe87C8D460()
	{
		$this->runCheckUnsafeCase(23);
	}

	/**
	* @testdox Safe if attribute 'url' has filter '#url': <object><param name="movie" value="{@url}"/><param name="allowscriptaccess" value="never"/></object>
	*/
	public function testCheckUnsafe65D5F45A()
	{
		$this->runCheckUnsafeCase(24);
	}

	/**
	* @testdox Not safe: <b disable-output-escaping="1"/>
	*/
	public function testCheckUnsafeCCAC3746()
	{
		$this->runCheckUnsafeCase(25);
	}

	/**
	* @testdox Not safe: <xsl:copy/>
	*/
	public function testCheckUnsafe60753852()
	{
		$this->runCheckUnsafeCase(26);
	}

	/**
	* @testdox Not safe: <b><xsl:copy-of select="@onclick"/></b>
	*/
	public function testCheckUnsafeC19FCB6D()
	{
		$this->runCheckUnsafeCase(27);
	}

	/**
	* @testdox Not safe: <b><xsl:copy-of select=" @ onclick "/></b>
	*/
	public function testCheckUnsafeE26527B5()
	{
		$this->runCheckUnsafeCase(28);
	}

	/**
	* @testdox Safe: <b><xsl:copy-of select="@title"/></b>
	*/
	public function testCheckUnsafe990F4294()
	{
		$this->runCheckUnsafeCase(29);
	}

	/**
	* @testdox Safe: <b><xsl:copy-of select=" @ title "/></b>
	*/
	public function testCheckUnsafe358E72E5()
	{
		$this->runCheckUnsafeCase(30);
	}

	/**
	* @testdox Not safe if attribute 'href' has no filter: <a><xsl:copy-of select="@href"/></a>
	*/
	public function testCheckUnsafeE6B9D02C()
	{
		$this->runCheckUnsafeCase(31);
	}

	/**
	* @testdox Safe if attribute 'href' has filter '#url': <a><xsl:copy-of select="@href"/></a>
	*/
	public function testCheckUnsafe8FFC2B06()
	{
		$this->runCheckUnsafeCase(32);
	}

	/**
	* @testdox Not safe: <xsl:copy-of select="script"/>
	*/
	public function testCheckUnsafeC8E8CC43()
	{
		$this->runCheckUnsafeCase(33);
	}

	/**
	* @testdox Not safe: <xsl:copy-of select=" script "/>
	*/
	public function testCheckUnsafe10D2139E()
	{
		$this->runCheckUnsafeCase(34);
	}

	/**
	* @testdox Not safe: <xsl:copy-of select="parent::*"/>
	*/
	public function testCheckUnsafe1BDDD975()
	{
		$this->runCheckUnsafeCase(35);
	}

	/**
	* @testdox Not safe: <script><xsl:apply-templates/></script>
	*/
	public function testCheckUnsafe87044075()
	{
		$this->runCheckUnsafeCase(36);
	}

	/**
	* @testdox Not safe: <script><xsl:apply-templates select="st"/></script>
	*/
	public function testCheckUnsafeC968EED0()
	{
		$this->runCheckUnsafeCase(37);
	}

	/**
	* @testdox Not safe: <script><xsl:if test="1"><xsl:apply-templates/></xsl:if></script>
	*/
	public function testCheckUnsafeCC87BEB3()
	{
		$this->runCheckUnsafeCase(38);
	}

	/**
	* @testdox Not safe: <script><xsl:value-of select="st"/></script>
	*/
	public function testCheckUnsafe5D562F28()
	{
		$this->runCheckUnsafeCase(39);
	}

	/**
	* @testdox Not safe: <script><xsl:value-of select="@foo"/></script>
	*/
	public function testCheckUnsafeAA242A38()
	{
		$this->runCheckUnsafeCase(40);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <script><xsl:value-of select="@foo"/></script>
	*/
	public function testCheckUnsafeBD7323B9()
	{
		$this->runCheckUnsafeCase(41);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <script><xsl:if test="1"><xsl:value-of select="@foo"/></xsl:if></script>
	*/
	public function testCheckUnsafe648A7C72()
	{
		$this->runCheckUnsafeCase(42);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <xsl:element name="script"><xsl:value-of select="@foo"/></xsl:element>
	*/
	public function testCheckUnsafeD7E78277()
	{
		$this->runCheckUnsafeCase(43);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <xsl:element name="SCRIPT"><xsl:value-of select="@foo"/></xsl:element>
	*/
	public function testCheckUnsafeF7D14089()
	{
		$this->runCheckUnsafeCase(44);
	}

	/**
	* @testdox Not safe if attribute 'foo' has filter '#number': <script><xsl:for-each select="/*"><xsl:value-of select="@foo"/></xsl:for-each></script>
	*/
	public function testCheckUnsafe2F2F80C9()
	{
		$this->runCheckUnsafeCase(45);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'rawurlencode': <script><xsl:value-of select="@foo"/></script>
	*/
	public function testCheckUnsafe7012AF9D()
	{
		$this->runCheckUnsafeCase(46);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'strtotime': <script><xsl:value-of select="@foo"/></script>
	*/
	public function testCheckUnsafe22244911()
	{
		$this->runCheckUnsafeCase(47);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'urlencode': <script><xsl:value-of select="@foo"/></script>
	*/
	public function testCheckUnsafe3E385049()
	{
		$this->runCheckUnsafeCase(48);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <script><xsl:value-of select="@foo"/></script>
	*/
	public function testCheckUnsafe4634FF28()
	{
		$this->runCheckUnsafeCase(49);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <script><xsl:value-of select="@foo"/></script>
	*/
	public function testCheckUnsafeB3E7F29F()
	{
		$this->runCheckUnsafeCase(50);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <script><xsl:value-of select="@foo"/></script>
	*/
	public function testCheckUnsafe302CE09C()
	{
		$this->runCheckUnsafeCase(51);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <script><xsl:value-of select="@foo"/></script>
	*/
	public function testCheckUnsafeF8670CD8()
	{
		$this->runCheckUnsafeCase(52);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <script><xsl:value-of select="@foo"/></script>
	*/
	public function testCheckUnsafeB213DF48()
	{
		$this->runCheckUnsafeCase(53);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <script><xsl:value-of select="@foo"/></script>
	*/
	public function testCheckUnsafe9A103B25()
	{
		$this->runCheckUnsafeCase(54);
	}

	/**
	* @testdox Not safe: <style><xsl:apply-templates/></style>
	*/
	public function testCheckUnsafe9332F4DA()
	{
		$this->runCheckUnsafeCase(55);
	}

	/**
	* @testdox Not safe: <style><xsl:apply-templates select="st"/></style>
	*/
	public function testCheckUnsafeE7A11344()
	{
		$this->runCheckUnsafeCase(56);
	}

	/**
	* @testdox Not safe: <style><xsl:if test="1"><xsl:apply-templates/></xsl:if></style>
	*/
	public function testCheckUnsafe0F7C3E8F()
	{
		$this->runCheckUnsafeCase(57);
	}

	/**
	* @testdox Not safe: <style><xsl:value-of select="st"/></style>
	*/
	public function testCheckUnsafeF4114812()
	{
		$this->runCheckUnsafeCase(58);
	}

	/**
	* @testdox Not safe: <style><xsl:value-of select="@foo"/></style>
	*/
	public function testCheckUnsafeFD7FAE5C()
	{
		$this->runCheckUnsafeCase(59);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <style><xsl:value-of select="@foo"/></style>
	*/
	public function testCheckUnsafe2BEA39BA()
	{
		$this->runCheckUnsafeCase(60);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <style><xsl:if test="1"><xsl:value-of select="@foo"/></xsl:if></style>
	*/
	public function testCheckUnsafe489BADA7()
	{
		$this->runCheckUnsafeCase(61);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <xsl:element name="style"><xsl:value-of select="@foo"/></xsl:element>
	*/
	public function testCheckUnsafeFC0D6B8F()
	{
		$this->runCheckUnsafeCase(62);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <xsl:element name="STYLE"><xsl:value-of select="@foo"/></xsl:element>
	*/
	public function testCheckUnsafe9092B290()
	{
		$this->runCheckUnsafeCase(63);
	}

	/**
	* @testdox Not safe if attribute 'foo' has filter '#number': <style><xsl:for-each select="/*"><xsl:value-of select="@foo"/></xsl:for-each></style>
	*/
	public function testCheckUnsafe88003D4F()
	{
		$this->runCheckUnsafeCase(64);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#color': <style><xsl:value-of select="@foo"/></style>
	*/
	public function testCheckUnsafe35EAE475()
	{
		$this->runCheckUnsafeCase(65);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <style><xsl:value-of select="@foo"/></style>
	*/
	public function testCheckUnsafe7AE86C7A()
	{
		$this->runCheckUnsafeCase(66);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <style><xsl:value-of select="@foo"/></style>
	*/
	public function testCheckUnsafeE54C9898()
	{
		$this->runCheckUnsafeCase(67);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <style><xsl:value-of select="@foo"/></style>
	*/
	public function testCheckUnsafe91C3EC9A()
	{
		$this->runCheckUnsafeCase(68);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <style><xsl:value-of select="@foo"/></style>
	*/
	public function testCheckUnsafe4256B11C()
	{
		$this->runCheckUnsafeCase(69);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#simpletext': <style><xsl:value-of select="@foo"/></style>
	*/
	public function testCheckUnsafe2441AE99()
	{
		$this->runCheckUnsafeCase(70);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <style><xsl:value-of select="@foo"/></style>
	*/
	public function testCheckUnsafe694A30D6()
	{
		$this->runCheckUnsafeCase(71);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <style><xsl:value-of select="@foo"/></style>
	*/
	public function testCheckUnsafe120F04AE()
	{
		$this->runCheckUnsafeCase(72);
	}

	/**
	* @testdox Not safe: <xsl:element name="{FOO}"><xsl:apply-templates/></xsl:element>
	*/
	public function testCheckUnsafe95E78AB4()
	{
		$this->runCheckUnsafeCase(73);
	}

	/**
	* @testdox Not safe: <b><xsl:attribute name="onclick"><xsl:apply-templates/></xsl:attribute></b>
	*/
	public function testCheckUnsafeCC20E4F6()
	{
		$this->runCheckUnsafeCase(74);
	}

	/**
	* @testdox Not safe: <b><xsl:attribute name="ONCLICK"><xsl:apply-templates/></xsl:attribute></b>
	*/
	public function testCheckUnsafe31C90A06()
	{
		$this->runCheckUnsafeCase(75);
	}

	/**
	* @testdox Not safe: <b onclick=""><xsl:attribute name="onclick"><xsl:apply-templates/></xsl:attribute></b>
	*/
	public function testCheckUnsafe6519C7B2()
	{
		$this->runCheckUnsafeCase(76);
	}

	/**
	* @testdox Not safe: <b><xsl:if test="1"><xsl:attribute name="onclick"><xsl:value-of select="@foo"/></xsl:attribute></xsl:if></b>
	*/
	public function testCheckUnsafeF4D2CDD1()
	{
		$this->runCheckUnsafeCase(77);
	}

	/**
	* @testdox Not safe: <b><xsl:attribute name="onclick"><xsl:if test="1"><xsl:value-of select="@foo"/></xsl:if></xsl:attribute></b>
	*/
	public function testCheckUnsafeCF6CEF14()
	{
		$this->runCheckUnsafeCase(78);
	}

	/**
	* @testdox Not safe: <b onclick="{@foo}"/>
	*/
	public function testCheckUnsafe7A1C2C9E()
	{
		$this->runCheckUnsafeCase(79);
	}

	/**
	* @testdox Not safe: <b ONCLICK="{@foo}"/>
	*/
	public function testCheckUnsafe3DB3E070()
	{
		$this->runCheckUnsafeCase(80);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <b style="{@foo}"/>
	*/
	public function testCheckUnsafeCFE3D31C()
	{
		$this->runCheckUnsafeCase(81);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#color': <b style="{@foo}"/>
	*/
	public function testCheckUnsafe9A32FA1C()
	{
		$this->runCheckUnsafeCase(82);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <b style="{@foo}"/>
	*/
	public function testCheckUnsafeD5307213()
	{
		$this->runCheckUnsafeCase(83);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <b style="{@foo}"/>
	*/
	public function testCheckUnsafe9D1CF5DD()
	{
		$this->runCheckUnsafeCase(84);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <b style="{@foo}"/>
	*/
	public function testCheckUnsafeEE136CBF()
	{
		$this->runCheckUnsafeCase(85);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <b style="{@foo}"/>
	*/
	public function testCheckUnsafe59192425()
	{
		$this->runCheckUnsafeCase(86);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#simpletext': <b style="{@foo}"/>
	*/
	public function testCheckUnsafe44A57F9B()
	{
		$this->runCheckUnsafeCase(87);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <b style="{@foo}"/>
	*/
	public function testCheckUnsafeA4233B9B()
	{
		$this->runCheckUnsafeCase(88);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <b style="{@foo}"/>
	*/
	public function testCheckUnsafe528F81E5()
	{
		$this->runCheckUnsafeCase(89);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <b onclick="{@foo}"/>
	*/
	public function testCheckUnsafeF82217B5()
	{
		$this->runCheckUnsafeCase(90);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'rawurlencode': <b onclick="{@foo}"/>
	*/
	public function testCheckUnsafe8D21FD39()
	{
		$this->runCheckUnsafeCase(91);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'strtotime': <b onclick="{@foo}"/>
	*/
	public function testCheckUnsafeAA37BCED()
	{
		$this->runCheckUnsafeCase(92);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'urlencode': <b onclick="{@foo}"/>
	*/
	public function testCheckUnsafeB62BA5B5()
	{
		$this->runCheckUnsafeCase(93);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <b onclick="{@foo}"/>
	*/
	public function testCheckUnsafe65AA3677()
	{
		$this->runCheckUnsafeCase(94);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <b onclick="{@foo}"/>
	*/
	public function testCheckUnsafeEF44679F()
	{
		$this->runCheckUnsafeCase(95);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <b onclick="{@foo}"/>
	*/
	public function testCheckUnsafe1A6F0785()
	{
		$this->runCheckUnsafeCase(96);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <b onclick="{@foo}"/>
	*/
	public function testCheckUnsafe9208B732()
	{
		$this->runCheckUnsafeCase(97);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <b onclick="{@foo}"/>
	*/
	public function testCheckUnsafeF1FA5319()
	{
		$this->runCheckUnsafeCase(98);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <b onclick="{@foo}"/>
	*/
	public function testCheckUnsafe469E31F8()
	{
		$this->runCheckUnsafeCase(99);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <b onanything="{@foo}"/>
	*/
	public function testCheckUnsafe55C38875()
	{
		$this->runCheckUnsafeCase(100);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'rawurlencode': <b onanything="{@foo}"/>
	*/
	public function testCheckUnsafe344CD60B()
	{
		$this->runCheckUnsafeCase(101);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'strtotime': <b onanything="{@foo}"/>
	*/
	public function testCheckUnsafe5B2F739D()
	{
		$this->runCheckUnsafeCase(102);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'urlencode': <b onanything="{@foo}"/>
	*/
	public function testCheckUnsafe47336AC5()
	{
		$this->runCheckUnsafeCase(103);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <b onanything="{@foo}"/>
	*/
	public function testCheckUnsafeFAE3B2D0()
	{
		$this->runCheckUnsafeCase(104);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <b onanything="{@foo}"/>
	*/
	public function testCheckUnsafe9C8C1F45()
	{
		$this->runCheckUnsafeCase(105);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <b onanything="{@foo}"/>
	*/
	public function testCheckUnsafe5A305389()
	{
		$this->runCheckUnsafeCase(106);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <b onanything="{@foo}"/>
	*/
	public function testCheckUnsafe1ED05833()
	{
		$this->runCheckUnsafeCase(107);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <b onanything="{@foo}"/>
	*/
	public function testCheckUnsafe03BD5AF0()
	{
		$this->runCheckUnsafeCase(108);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <b onanything="{@foo}"/>
	*/
	public function testCheckUnsafe2DAF36B8()
	{
		$this->runCheckUnsafeCase(109);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <form action="{@foo}"/>
	*/
	public function testCheckUnsafe4545A54D()
	{
		$this->runCheckUnsafeCase(110);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'urlencode': <form action="{@foo}"/>
	*/
	public function testCheckUnsafe011ED377()
	{
		$this->runCheckUnsafeCase(111);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'rawurlencode': <form action="{@foo}"/>
	*/
	public function testCheckUnsafeF62D2BCF()
	{
		$this->runCheckUnsafeCase(112);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <form action="{@foo}"/>
	*/
	public function testCheckUnsafe1D8FED00()
	{
		$this->runCheckUnsafeCase(113);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#identifier': <form action="{@foo}"/>
	*/
	public function testCheckUnsafeAD8285AC()
	{
		$this->runCheckUnsafeCase(114);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <form action="{@foo}"/>
	*/
	public function testCheckUnsafeF4C8B6C3()
	{
		$this->runCheckUnsafeCase(115);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <form action="{@foo}"/>
	*/
	public function testCheckUnsafeEA7E010C()
	{
		$this->runCheckUnsafeCase(116);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <form action="{@foo}"/>
	*/
	public function testCheckUnsafe7A2D541E()
	{
		$this->runCheckUnsafeCase(117);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <form action="{@foo}"/>
	*/
	public function testCheckUnsafeE871BF83()
	{
		$this->runCheckUnsafeCase(118);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <form action="{@foo}"/>
	*/
	public function testCheckUnsafeD432C6E9()
	{
		$this->runCheckUnsafeCase(119);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <q cite="{@foo}"/>
	*/
	public function testCheckUnsafe4BB1ACC7()
	{
		$this->runCheckUnsafeCase(120);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'urlencode': <q cite="{@foo}"/>
	*/
	public function testCheckUnsafeA18FDA15()
	{
		$this->runCheckUnsafeCase(121);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'rawurlencode': <q cite="{@foo}"/>
	*/
	public function testCheckUnsafeD65F377B()
	{
		$this->runCheckUnsafeCase(122);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <q cite="{@foo}"/>
	*/
	public function testCheckUnsafe4DF3EFB1()
	{
		$this->runCheckUnsafeCase(123);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#identifier': <q cite="{@foo}"/>
	*/
	public function testCheckUnsafe6B0254F0()
	{
		$this->runCheckUnsafeCase(124);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <q cite="{@foo}"/>
	*/
	public function testCheckUnsafe030FA5BA()
	{
		$this->runCheckUnsafeCase(125);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <q cite="{@foo}"/>
	*/
	public function testCheckUnsafe4567CAEE()
	{
		$this->runCheckUnsafeCase(126);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <q cite="{@foo}"/>
	*/
	public function testCheckUnsafe734CA0E4()
	{
		$this->runCheckUnsafeCase(127);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <q cite="{@foo}"/>
	*/
	public function testCheckUnsafeD7BEFA2A()
	{
		$this->runCheckUnsafeCase(128);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <q cite="{@foo}"/>
	*/
	public function testCheckUnsafe0E4A95DC()
	{
		$this->runCheckUnsafeCase(129);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <foo data="{@foo}"/>
	*/
	public function testCheckUnsafe222484DC()
	{
		$this->runCheckUnsafeCase(130);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'urlencode': <foo data="{@foo}"/>
	*/
	public function testCheckUnsafe5949B936()
	{
		$this->runCheckUnsafeCase(131);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'rawurlencode': <foo data="{@foo}"/>
	*/
	public function testCheckUnsafe008F4834()
	{
		$this->runCheckUnsafeCase(132);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <foo data="{@foo}"/>
	*/
	public function testCheckUnsafeF57A47E2()
	{
		$this->runCheckUnsafeCase(133);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#identifier': <foo data="{@foo}"/>
	*/
	public function testCheckUnsafeDCB55352()
	{
		$this->runCheckUnsafeCase(134);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <foo data="{@foo}"/>
	*/
	public function testCheckUnsafeB5C3F8A9()
	{
		$this->runCheckUnsafeCase(135);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <foo data="{@foo}"/>
	*/
	public function testCheckUnsafeD92757DA()
	{
		$this->runCheckUnsafeCase(136);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <foo data="{@foo}"/>
	*/
	public function testCheckUnsafeE3857497()
	{
		$this->runCheckUnsafeCase(137);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <foo data="{@foo}"/>
	*/
	public function testCheckUnsafe54194A37()
	{
		$this->runCheckUnsafeCase(138);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <foo data="{@foo}"/>
	*/
	public function testCheckUnsafeF39397CC()
	{
		$this->runCheckUnsafeCase(139);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <input formaction="{@foo}"/>
	*/
	public function testCheckUnsafe8822BDBC()
	{
		$this->runCheckUnsafeCase(140);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'urlencode': <input formaction="{@foo}"/>
	*/
	public function testCheckUnsafe163BDE98()
	{
		$this->runCheckUnsafeCase(141);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'rawurlencode': <input formaction="{@foo}"/>
	*/
	public function testCheckUnsafe1BC9BCD2()
	{
		$this->runCheckUnsafeCase(142);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <input formaction="{@foo}"/>
	*/
	public function testCheckUnsafe616C1659()
	{
		$this->runCheckUnsafeCase(143);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#identifier': <input formaction="{@foo}"/>
	*/
	public function testCheckUnsafe415E4E49()
	{
		$this->runCheckUnsafeCase(144);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <input formaction="{@foo}"/>
	*/
	public function testCheckUnsafeAF49AEF4()
	{
		$this->runCheckUnsafeCase(145);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <input formaction="{@foo}"/>
	*/
	public function testCheckUnsafe47589FFB()
	{
		$this->runCheckUnsafeCase(146);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <input formaction="{@foo}"/>
	*/
	public function testCheckUnsafeD0986A86()
	{
		$this->runCheckUnsafeCase(147);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <input formaction="{@foo}"/>
	*/
	public function testCheckUnsafeD1F16722()
	{
		$this->runCheckUnsafeCase(148);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <input formaction="{@foo}"/>
	*/
	public function testCheckUnsafe1FA3041D()
	{
		$this->runCheckUnsafeCase(149);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <a href="{@foo}"/>
	*/
	public function testCheckUnsafeFF6EB164()
	{
		$this->runCheckUnsafeCase(150);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'urlencode': <a href="{@foo}"/>
	*/
	public function testCheckUnsafe9569534D()
	{
		$this->runCheckUnsafeCase(151);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'rawurlencode': <a href="{@foo}"/>
	*/
	public function testCheckUnsafe1F9290E6()
	{
		$this->runCheckUnsafeCase(152);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <a href="{@foo}"/>
	*/
	public function testCheckUnsafe66A53754()
	{
		$this->runCheckUnsafeCase(153);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#identifier': <a href="{@foo}"/>
	*/
	public function testCheckUnsafe03D1F230()
	{
		$this->runCheckUnsafeCase(154);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <a href="{@foo}"/>
	*/
	public function testCheckUnsafe2F2ED620()
	{
		$this->runCheckUnsafeCase(155);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <a href="{@foo}"/>
	*/
	public function testCheckUnsafe21A76358()
	{
		$this->runCheckUnsafeCase(156);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <a href="{@foo}"/>
	*/
	public function testCheckUnsafeAF6507EF()
	{
		$this->runCheckUnsafeCase(157);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <a href="{@foo}"/>
	*/
	public function testCheckUnsafe6F41FD4B()
	{
		$this->runCheckUnsafeCase(158);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <a href="{@foo}"/>
	*/
	public function testCheckUnsafe14BB2A31()
	{
		$this->runCheckUnsafeCase(159);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <html manifest="{@foo}"/>
	*/
	public function testCheckUnsafeF2542B4A()
	{
		$this->runCheckUnsafeCase(160);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'urlencode': <html manifest="{@foo}"/>
	*/
	public function testCheckUnsafeBB6166BF()
	{
		$this->runCheckUnsafeCase(161);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'rawurlencode': <html manifest="{@foo}"/>
	*/
	public function testCheckUnsafe0552EE76()
	{
		$this->runCheckUnsafeCase(162);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <html manifest="{@foo}"/>
	*/
	public function testCheckUnsafe49E08858()
	{
		$this->runCheckUnsafeCase(163);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#identifier': <html manifest="{@foo}"/>
	*/
	public function testCheckUnsafeF2122245()
	{
		$this->runCheckUnsafeCase(164);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <html manifest="{@foo}"/>
	*/
	public function testCheckUnsafe9E3BB9E3()
	{
		$this->runCheckUnsafeCase(165);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <html manifest="{@foo}"/>
	*/
	public function testCheckUnsafe4D569D7B()
	{
		$this->runCheckUnsafeCase(166);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <html manifest="{@foo}"/>
	*/
	public function testCheckUnsafeD67481C9()
	{
		$this->runCheckUnsafeCase(167);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <html manifest="{@foo}"/>
	*/
	public function testCheckUnsafe73104660()
	{
		$this->runCheckUnsafeCase(168);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <html manifest="{@foo}"/>
	*/
	public function testCheckUnsafeB5085615()
	{
		$this->runCheckUnsafeCase(169);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <video poster="{@foo}"/>
	*/
	public function testCheckUnsafe90D5A413()
	{
		$this->runCheckUnsafeCase(170);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'urlencode': <video poster="{@foo}"/>
	*/
	public function testCheckUnsafe78CC8E5E()
	{
		$this->runCheckUnsafeCase(171);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'rawurlencode': <video poster="{@foo}"/>
	*/
	public function testCheckUnsafeD5A4189D()
	{
		$this->runCheckUnsafeCase(172);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <video poster="{@foo}"/>
	*/
	public function testCheckUnsafeB7A8DF22()
	{
		$this->runCheckUnsafeCase(173);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#identifier': <video poster="{@foo}"/>
	*/
	public function testCheckUnsafe9BB59B6E()
	{
		$this->runCheckUnsafeCase(174);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <video poster="{@foo}"/>
	*/
	public function testCheckUnsafeEF509C66()
	{
		$this->runCheckUnsafeCase(175);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <video poster="{@foo}"/>
	*/
	public function testCheckUnsafe99CD7232()
	{
		$this->runCheckUnsafeCase(176);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <video poster="{@foo}"/>
	*/
	public function testCheckUnsafe4AD2F4EE()
	{
		$this->runCheckUnsafeCase(177);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <video poster="{@foo}"/>
	*/
	public function testCheckUnsafeA67DF74D()
	{
		$this->runCheckUnsafeCase(178);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <video poster="{@foo}"/>
	*/
	public function testCheckUnsafe43294FB2()
	{
		$this->runCheckUnsafeCase(179);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <img src="{@foo}"/>
	*/
	public function testCheckUnsafeF39CC4CF()
	{
		$this->runCheckUnsafeCase(180);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'urlencode': <img src="{@foo}"/>
	*/
	public function testCheckUnsafe5D20BBEC()
	{
		$this->runCheckUnsafeCase(181);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'rawurlencode': <img src="{@foo}"/>
	*/
	public function testCheckUnsafeF9B0D150()
	{
		$this->runCheckUnsafeCase(182);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <img src="{@foo}"/>
	*/
	public function testCheckUnsafe5AD4E126()
	{
		$this->runCheckUnsafeCase(183);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#identifier': <img src="{@foo}"/>
	*/
	public function testCheckUnsafe87F02840()
	{
		$this->runCheckUnsafeCase(184);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <img src="{@foo}"/>
	*/
	public function testCheckUnsafeF043F4A6()
	{
		$this->runCheckUnsafeCase(185);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <img src="{@foo}"/>
	*/
	public function testCheckUnsafe86B2FC69()
	{
		$this->runCheckUnsafeCase(186);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <img src="{@foo}"/>
	*/
	public function testCheckUnsafe55FA2ED5()
	{
		$this->runCheckUnsafeCase(187);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <img src="{@foo}"/>
	*/
	public function testCheckUnsafe1E48C163()
	{
		$this->runCheckUnsafeCase(188);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <img src="{@foo}"/>
	*/
	public function testCheckUnsafe46272257()
	{
		$this->runCheckUnsafeCase(189);
	}

	/**
	* @testdox Not safe if attribute 'foo' has no filter: <img lowsrc="{@foo}"/>
	*/
	public function testCheckUnsafe2A2871AB()
	{
		$this->runCheckUnsafeCase(190);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'urlencode': <img lowsrc="{@foo}"/>
	*/
	public function testCheckUnsafeBBB66DAA()
	{
		$this->runCheckUnsafeCase(191);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter 'rawurlencode': <img lowsrc="{@foo}"/>
	*/
	public function testCheckUnsafeA0B9F5E2()
	{
		$this->runCheckUnsafeCase(192);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#float': <img lowsrc="{@foo}"/>
	*/
	public function testCheckUnsafe4FA0965D()
	{
		$this->runCheckUnsafeCase(193);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#identifier': <img lowsrc="{@foo}"/>
	*/
	public function testCheckUnsafe3E0CC169()
	{
		$this->runCheckUnsafeCase(194);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#int': <img lowsrc="{@foo}"/>
	*/
	public function testCheckUnsafeB47B7615()
	{
		$this->runCheckUnsafeCase(195);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#number': <img lowsrc="{@foo}"/>
	*/
	public function testCheckUnsafeE90021D9()
	{
		$this->runCheckUnsafeCase(196);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#range': <img lowsrc="{@foo}"/>
	*/
	public function testCheckUnsafe7AEB28F9()
	{
		$this->runCheckUnsafeCase(197);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#uint': <img lowsrc="{@foo}"/>
	*/
	public function testCheckUnsafeF8E7C339()
	{
		$this->runCheckUnsafeCase(198);
	}

	/**
	* @testdox Safe if attribute 'foo' has filter '#url': <img lowsrc="{@foo}"/>
	*/
	public function testCheckUnsafe717044CD()
	{
		$this->runCheckUnsafeCase(199);
	}

	/**
	* @testdox Not safe: <b><xsl:attribute name="{FOO}"><xsl:apply-templates/></xsl:attribute></b>
	*/
	public function testCheckUnsafeA0040D8C()
	{
		$this->runCheckUnsafeCase(200);
	}

	/**
	* @testdox Not safe: <xsl:value-of select="document(@foo)"/>
	*/
	public function testCheckUnsafeB20D8B13()
	{
		$this->runCheckUnsafeCase(201);
	}

	/**
	* @testdox Not safe: <b title="...{document()}"/>
	*/
	public function testCheckUnsafe70EA60CA()
	{
		$this->runCheckUnsafeCase(202);
	}

	/**
	* @testdox Not safe: <b title="...{ document () }"/>
	*/
	public function testCheckUnsafe77BFCDDA()
	{
		$this->runCheckUnsafeCase(203);
	}

	/**
	* @testdox Not safe: <b title="...{ doc&#117;ment () }"/>
	*/
	public function testCheckUnsafeA9551EEA()
	{
		$this->runCheckUnsafeCase(204);
	}

	/**
	* @testdox Not safe: <b title="{concat('}',document())}"/>
	*/
	public function testCheckUnsafeCE9503E7()
	{
		$this->runCheckUnsafeCase(205);
	}

	/**
	* @testdox Safe: <b title="document()"/>
	*/
	public function testCheckUnsafeBB984E27()
	{
		$this->runCheckUnsafeCase(206);
	}

	/**
	* @testdox Safe: <b title="{&quot;document()&quot;}"/>
	*/
	public function testCheckUnsafe402D2EE0()
	{
		$this->runCheckUnsafeCase(207);
	}
	// End of content generated by ../../../scripts/patchTemplateCheckerTest.php

	protected function runCheckUnsafeCase($k)
	{
		static $data;
		if (!isset($data))
		{
			$data = $this->getUnsafeTemplatesTests();
		}

		call_user_func_array([$this, 'checkUnsafe'], $data[$k]);
	}

	protected function checkUnsafe($template, $exceptionMsg = null, array $tagOptions = [])
	{
		if (isset($exceptionMsg))
		{
			$this->setExpectedException(
				's9e\\TextFormatter\\Configurator\\Exceptions\\UnsafeTemplateException',
				$exceptionMsg
			);
		}

		TemplateChecker::checkUnsafe(
			TemplateOptimizer::optimize($template),
			new Tag($tagOptions)
		);
	}

	public function getUnsafeTemplatesTests()
	{
		return array_merge(
			$this->getUnsafeFixedUrlTests(),
			$this->getUnsafeDisableOutputEscapingTests(),
			$this->getUnsafeCopyNodesTests(),
			$this->getUnsafeContentTests(),
			$this->getUnsafeExpressionTests()
		);
	}

	public function getUnsafeExpressionTests()
	{
		return [
			[
				'<xsl:value-of select="document(@foo)"/>',
				"An XPath expression uses the document() function"
			],
			[
				'<b title="...{document()}"/>',
				"An XPath expression uses the document() function"
			],
			[
				'<b title="...{ document () }"/>',
				"An XPath expression uses the document() function"
			],
			[
				'<b title="...{ doc&#117;ment () }"/>',
				"An XPath expression uses the document() function"
			],
			[
				'<b title="{concat(\'}\',document())}"/>',
				"An XPath expression uses the document() function"
			],
			[
				'<b title="document()"/>',
				null
			],
			[
				'<b title="{&quot;document()&quot;}"/>',
				null
			],
		];
	}

	public function getUnsafeFixedUrlTests()
	{
		return [
			[
				'<embed src="{@url}"/>',
				"The template contains a 'embed' element with a non-fixed URL"
			],
			[
				'<embed src="{@url}" allowscriptaccess="always"/>',
				"The template contains a 'embed' element with a non-fixed URL"
			],
			[
				'<embed src="{@url}" allowscriptaccess="sameDomain"/>',
				"The template contains a 'embed' element with a non-fixed URL"
			],
			[
				'<embed src="{@url}" allowscriptaccess="never"/>',
				null,
				[
					'attributes' => [
						'url' => [
							'filterChain' => [new Url]
						]
					]
				]
			],
			[
				'<iframe src="{@url}"/>',
				"The template contains a 'iframe' element with a non-fixed URL"
			],
			[
				'<object data="{@url}"/>',
				"The template contains a 'object' element with a non-fixed URL"
			],
			[
				'<object data="{@url}"><param name="allowscriptaccess" value="never"/></object>',
				null,
				[
					'attributes' => [
						'url' => [
							'filterChain' => [new Url]
						]
					]
				]
			],
			[
				'<script src="{@url}"/>',
				"The template contains a 'script' element with a non-fixed URL"
			],
			// Redundant but produces a nicer entry in testdox
			[
				'<script src="{@url}"/>',
				"The template contains a 'script' element with a non-fixed URL",
				[
					'attributes' => [
						'src' => [
							'filterChain' => [new Url]
						]
					]
				]
			],
			[
				'<script src="http://{@foo}"/>',
				"The template contains a 'script' element with a non-fixed URL"
			],
			[
				'<script src="https://gist.github.com/{@id}.js"/>',
				null,
				[
					'attributes' => [
						'id' => [
							'filterChain' => [new Number]
						]
					]
				]
			],
			[
				'<script src="//gist.github.com/{@id}.js"/>',
				null,
				[
					'attributes' => [
						'id' => [
							'filterChain' => [new Number]
						]
					]
				]
			],
			[
				'<script src="foo.js"/>',
				null
			],
			// Try working around the safeguards
			[
				'<SCRIPT src="{@url}"/>',
				"The template contains a 'script' element with a non-fixed URL attribute 'src'"
			],
			[
				'<script SRC="{@url}"/>',
				"The template contains a 'script' element with a non-fixed URL attribute 'src'"
			],
			[
				'<script><xsl:attribute name="src"><xsl:value-of select="@url"/><?dont-optimize?></xsl:attribute></script>',
				"The template contains a 'script' element with a dynamically generated 'src' attribute that does not use a fixed URL"
			],
			[
				'<script><xsl:attribute name="SRC"><xsl:value-of select="@url"/><?dont-optimize?></xsl:attribute></script>',
				"The template contains a 'script' element with a dynamically generated 'src' attribute that does not use a fixed URL"
			],
			[
				'<script><xsl:attribute name="src">http://example.org/legit.js<?dont-optimize?></xsl:attribute></script>'
			],
			[
				'<script src="http://example.org/legit.js"><xsl:attribute name="id"><xsl:value-of select="foo"/><?dont-optimize?></xsl:attribute></script>'
			],
			[
				'<script src="http://example.org/legit.js"><xsl:attribute name="src"><xsl:value-of select="@hax"/><?dont-optimize?></xsl:attribute></script>',
				"The template contains a 'script' element with a dynamically generated 'src' attribute that does not use a fixed URL"
			],
			[
				'<xsl:element name="script"><xsl:attribute name="src"><xsl:value-of select="@url"/><?dont-optimize?></xsl:attribute></xsl:element>',
				"The template contains a 'script' element with a dynamically generated 'src' attribute that does not use a fixed URL"
			],
			[
				'<xsl:element name="SCRIPT"><xsl:attribute name="src"><xsl:value-of select="@url"/><?dont-optimize?></xsl:attribute></xsl:element>',
				"The template contains a 'script' element with a dynamically generated 'src' attribute that does not use a fixed URL"
			],
			[
				'<object><param name="movie" value="{@url}"/></object>',
				"The template contains a 'param' element with a non-fixed URL attribute 'value'"
			],
			[
				'<OBJECT><PARAM NAME="MOVIE" VALUE="{@url}"/></OBJECT>',
				"The template contains a 'param' element with a non-fixed URL attribute 'value'"
			],
			[
				'<object><param name="movie" value="{@url}"/><param name="allowscriptaccess" value="never"/></object>',
				null,
				[
					'attributes' => [
						'url' => [
							'filterChain' => [new Url]
						]
					]
				]
			]
		];
	}

	public function getUnsafeDisableOutputEscapingTests()
	{
		return [
			[
				'<b disable-output-escaping="1"/>',
				"The template contains a 'disable-output-escaping' attribute"
			]
		];
	}

	public function getUnsafeCopyNodesTests()
	{
		return [
			[
				'<xsl:copy/>',
				"Cannot assess the safety of an 'xsl:copy' element"
			]
		];
	}

	public function getUnsafeContentTests()
	{
		return array_merge(
			$this->getUnsafeCopyOfNodesTests(),
			$this->getUnsafeElementsTests(),
			$this->getUnsafeAttributesTests()
		);
	}

	public function getUnsafeCopyOfNodesTests()
	{
		return [
			[
				'<b><xsl:copy-of select="@onclick"/></b>',
				"Undefined attribute 'onclick'"
			],
			[
				'<b><xsl:copy-of select=" @ onclick "/></b>',
				"Undefined attribute 'onclick'"
			],
			[
				'<b><xsl:copy-of select="@title"/></b>'
			],
			[
				'<b><xsl:copy-of select=" @ title "/></b>'
			],
			[
				'<a><xsl:copy-of select="@href"/></a>',
				"Attribute 'href' is not properly filtered to be used in URL",
				[
					'attributes' => [
						'href' => []
					]
				]
			],
			[
				'<a><xsl:copy-of select="@href"/></a>',
				null,
				[
					'attributes' => [
						'href' => [
							'filterChain' => [new Url]
						]
					]
				]
			],
			[
				'<xsl:copy-of select="script"/>',
				"Cannot assess 'xsl:copy-of' select expression 'script' to be safe"
			],
			[
				'<xsl:copy-of select=" script "/>',
				"Cannot assess 'xsl:copy-of' select expression 'script' to be safe"
			],
			[
				'<xsl:copy-of select="parent::*"/>',
				"Cannot assess 'xsl:copy-of' select expression 'parent::*' to be safe"
			],
		];
	}

	protected function getSafeFilters($type)
	{
		$filters = [
			'CSS' => [
				'#color',
				'#float',
				'#int',
				'#range',
				'#number',
				'#simpletext',
				'#uint',
				'#url'
			],
			'JS' => [
//				'json_encode',
				'rawurlencode',
				'strtotime',
				'urlencode',
				'#float',
				'#int',
				'#range',
				'#number',
//				'#simpletext',
				'#uint',
				'#url'
			],
			'URL' => [
				'urlencode',
				'rawurlencode',
				'#float',
				'#identifier',
				'#int',
				'#number',
				'#range',
				'#uint',
				'#url'
			]
		];

		return $filters[$type];
	}

	public function getUnsafeElementsTests()
	{
		$elements = [
			'script' => 'JS',
			'style'  => 'CSS'
		];

		$tests = [];

		foreach ($elements as $elName => $type)
		{
			$tests[] = [
				'<' . $elName . '><xsl:apply-templates/></' . $elName . '>',
				"A '" . $elName . "' element lets unfiltered data through"
			];

			$tests[] = [
				'<' . $elName . '><xsl:apply-templates select="st"/></' . $elName . '>',
				"Cannot assess the safety of 'xsl:apply-templates' select expression 'st'"
			];

			$tests[] = [
				'<' . $elName . '><xsl:if test="1"><xsl:apply-templates/></xsl:if></' . $elName . '>',
				"A '" . $elName . "' element lets unfiltered data through"
			];

			$tests[] = [
				'<' . $elName . '><xsl:value-of select="st"/></' . $elName . '>',
				"Cannot assess the safety of XPath expression 'st'"
			];

			$tests[] = [
				'<' . $elName . '><xsl:value-of select="@foo"/></' . $elName . '>',
				"Undefined attribute 'foo'"
			];

			// Try some variations to get around basic checks
			$tagOptions = [
				'attributes' => [
					'foo' => []
				]
			];

			$tests[] = [
				'<' . $elName . '><xsl:value-of select="@foo"/></' . $elName . '>',
				"Attribute 'foo' is not properly filtered to be used in " . $type,
				$tagOptions
			];

			$tests[] = [
				'<' . $elName . '><xsl:if test="1"><xsl:value-of select="@foo"/></xsl:if></' . $elName . '>',
				"Attribute 'foo' is not properly filtered to be used in " . $type,
				$tagOptions
			];

			$tests[] = [
				'<xsl:element name="' . $elName . '"><xsl:value-of select="@foo"/></xsl:element>',
				"Attribute 'foo' is not properly filtered to be used in " . $type,
				$tagOptions
			];

			$tests[] = [
				'<xsl:element name="' . strtoupper($elName) . '"><xsl:value-of select="@foo"/></xsl:element>',
				"Attribute 'foo' is not properly filtered to be used in " . $type,
				$tagOptions
			];

			// Using xsl:for-each to subvert the context
			$tests[] = [
				'<' . $elName . '><xsl:for-each select="/*"><xsl:value-of select="@foo"/></xsl:for-each></' . $elName . '>',
				"Cannot evaluate context node due to 'xsl:for-each'",
				[
					'attributes' => [
						'foo' => [
							'filterChain' => [new Number]
						]
					]
				]
			];

			// Test safe filters
			foreach ($this->getSafeFilters($type) as $filterName)
			{
				$filter = $this->configurator->attributeFilters->get($filterName);

				$tests[] = [
					'<' . $elName . '><xsl:value-of select="@foo"/></' . $elName . '>',
					null,
					[
						'attributes' => [
							'foo' => [
								'filterChain' => [$filter]
							]
						]
					]
				];
			}
		}

		// Dynamic element names are too hard to assess
		$tests[] = [
			'<xsl:element name="{FOO}"><xsl:apply-templates/></xsl:element>',
			"Cannot assess 'xsl:element' name '{FOO}'"
		];

		return $tests;
	}

	public function getUnsafeAttributesTests()
	{
		$attributes = [
			'b:style'          => 'CSS',
			'b:onclick'        => 'JS',
			'b:onanything'     => 'JS',
			'form:action'      => 'URL',
			'q:cite'           => 'URL',
			// Should really be <object> but it would require a more complicated test to avoid
			// triggering the "fixed-src" checks
			'foo:data'         => 'URL',
			'input:formaction' => 'URL',
			'a:href'           => 'URL',
			'html:manifest'    => 'URL',
			'video:poster'     => 'URL',
			'img:src'          => 'URL',
			'img:lowsrc'       => 'URL'
		];

		$tests = [];

		// Those tests don't really need to be repeated for every attribute
		$tests[] = [
			'<b><xsl:attribute name="onclick"><xsl:apply-templates/></xsl:attribute></b>',
			"A dynamically generated 'onclick' attribute lets unfiltered data through"
		];

		$tests[] = [
			'<b><xsl:attribute name="ONCLICK"><xsl:apply-templates/></xsl:attribute></b>',
			"A dynamically generated 'onclick' attribute lets unfiltered data through"
		];

		$tests[] = [
			'<b onclick=""><xsl:attribute name="onclick"><xsl:apply-templates/></xsl:attribute></b>',
			"A dynamically generated 'onclick' attribute lets unfiltered data through"
		];

		$tests[] = [
			'<b><xsl:if test="1"><xsl:attribute name="onclick"><xsl:value-of select="@foo"/></xsl:attribute></xsl:if></b>',
			"Undefined attribute 'foo'"
		];

		$tests[] = [
			'<b><xsl:attribute name="onclick"><xsl:if test="1"><xsl:value-of select="@foo"/></xsl:if></xsl:attribute></b>',
			"Undefined attribute 'foo'"
		];

		$tests[] = [
			'<b onclick="{@foo}"/>',
			"Undefined attribute 'foo'"
		];

		$tests[] = [
			'<b ONCLICK="{@foo}"/>',
			"Undefined attribute 'foo'"
		];

		foreach ($attributes as $attribute => $type)
		{
			list($elName, $attrName) = explode(':', $attribute);
			$filters = $this->getSafeFilters($type);

			$tests[] = [
				'<' . $elName . ' ' . $attrName . '="{@foo}"/>',
				"Attribute 'foo' is not properly filtered to be used in " . $type,
				[
					'attributes' => [
						'foo' => []
					]
				]
			];

			// Test safe filters
			foreach ($filters as $filterName)
			{
				$tests[] = [
					'<' . $elName . ' ' . $attrName . '="{@foo}"/>',
					null,
					[
						'attributes' => [
							'foo' => [
								'filterChain' => [$this->configurator->attributeFilters[$filterName]]
							]
						]
					]
				];
			}
		}

		// Dynamic attribute names are too hard to assess
		$tests[] = [
			'<b><xsl:attribute name="{FOO}"><xsl:apply-templates/></xsl:attribute></b>',
			"Cannot assess 'xsl:attribute' name '{FOO}'"
		];

		return $tests;
	}
}