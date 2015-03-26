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
