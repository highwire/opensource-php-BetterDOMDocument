<?php

include_once('BetterDOMDocument.php');

class AsHTMLTest extends PHPUnit_Framework_TestCase {
    private $xml = '<nlm:contrib xmlns:nlm="http://schema.highwire.org/NLM/Journal" nlm:contrib-type="author"><nlm:string-name nlm:test="teststring">Patrick Douglas Hayes</nlm:string-name></nlm:contrib>';
    private $html = '<span class="nlm-contrib" data-nlm-contrib-type="author"><span class="nlm-string-name" data-nlm-test="teststring">Patrick Douglas Hayes</span></span>';
    private $contexthtml = '<span class="nlm-string-name" data-nlm-test="teststring">Patrick Douglas Hayes</span>';

    public function testAsHTML() {
        $dom = new BetterDOMDocument($this->xml);
        $this->assertEquals($this->html, $dom->asHTML());
    }
    public function testAsHTMLContext() {
        $dom = new BetterDOMDocument($this->xml);
         $this->assertEquals($this->contexthtml, $dom->asHTML('//nlm:string-name'));
    }
}


class AsHTMLXLinkTest extends PHPUnit_Framework_TestCase {
    private $xml = '<nlm:permissions xmlns:nlm="http://schema.highwire.org/NLM/Journal" xmlns:xlink="http://www.w3.org/1999/xlink"><nlm:license license-type="creative-commons">This article is distributed under the terms of the Creative Commons Attribution 3.0 License (<nlm:ext-link ext-link-type="uri" xlink:href="http://www.creativecommons.org/licenses/by/3.0/" xlink:type="simple">http://www.creativecommons.org/licenses/by/3.0/</nlm:ext-link>)</nlm:license></nlm:permissions>';

    private $html = '<span class="nlm-permissions"><span class="nlm-license" data-license-type="creative-commons">This article is distributed under the terms of the Creative Commons Attribution 3.0 License (<a class="nlm-ext-link" data-ext-link-type="uri" data-xlink-href="http://www.creativecommons.org/licenses/by/3.0/" data-xlink-type="simple" href="http://www.creativecommons.org/licenses/by/3.0/">http://www.creativecommons.org/licenses/by/3.0/</a>)</span></span>';

    public function testXHTMLXLink() {
        $dom = new BetterDOMDocument($this->xml);
        $this->assertEquals($this->html, $dom->asHTML(FALSE, array('xlink' => TRUE)));
    }
}