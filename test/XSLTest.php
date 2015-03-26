<?php

include_once('BetterDOMDocument.php');

class AsHTMLTest extends PHPUnit_Framework_TestCase {
    private $xml = '<nlm:contrib xmlns:nlm="http://schema.highwire.org/NLM/Journal" nlm:contrib-type="author"><nlm:string-name nlm:test="teststring">Patrick Douglas Hayes</nlm:string-name></nlm:contrib>';
    private $html = ''

    public function testAsHTML() {
        $dom = new BetterDOMDocument($this->xml);
        print $this->asHTML();
    }
    public function testAsHTMLContext() {
        $dom = new BetterDOMDocument($this->xml);
        print $this->asHTML('//nlm:string-name');
    }
}
