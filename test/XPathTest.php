<?php

include_once('BetterDOMDocument.php');

class PrefixTest extends PHPUnit_Framework_TestCase {
    private $xml = '<nlm:contrib xmlns:nlm="http://schema.highwire.org/NLM/Journal" nlm:contrib-type="author"><nlm:string-name nlm:test="teststring">Patrick Douglas Hayes</nlm:string-name></nlm:contrib>';

    public function testAttr() {
        $dom = new BetterDOMDocument($this->xml);
        $this->assertEquals("teststring", $dom->xpathSingle('//@nlm:test')->nodeValue);
    }
    public function testAttrContext() {
        $dom = new BetterDOMDocument($this->xml);
        $this->assertEquals("teststring", $dom->xpathSingle('@nlm:test', "//nlm:string-name")->nodeValue);
    }
}


class MultiPrefixTest extends PHPUnit_Framework_TestCase {
    private $xml = '<?xml version="1.0"?><!-- This is a comment -->
                    <atom:author test="123" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:nlm="http://schema.highwire.org/NLM/Journal" xmlns:hwp="http://schema.highwire.org/Journal" nlm:contrib-type="author">
                      <atom:name>Shubhayan Sanatani</atom:name>
                      <nlm:name name-style="western" hwp:sortable="Sanatani Shubhayan"><nlm:surname>Sanatani</nlm:surname><nlm:given-names>Shubhayan</nlm:given-names></nlm:name>
                      <nlm:name name-style="eastern" hwp:sortable="Li Xu"><nlm:surname>Li</nlm:surname><nlm:given-names>Xu</nlm:given-names></nlm:name>
                    </atom:author>';

    public function testAttr() {
        $dom = new BetterDOMDocument($this->xml);
        $this->assertEquals("Li Xu", $dom->xpathSingle('//nlm:name[@name-style="eastern"]/@hwp:sortable')->nodeValue);
    }
    public function testContext() {
        $dom = new BetterDOMDocument($this->xml);
        $this->assertEquals("Sanatani", $dom->xpathSingle('.//nlm:surname', "//nlm:name[@name-style='western']")->nodeValue);
    }
}


class HWSearchTest extends PHPUnit_Framework_TestCase {
    private $xml = '<?xml version="1.0" encoding="UTF-8"?>
                    <hwsearch:results xmlns:hwsearch="http://schema.highwire.org/Search"
                       query-uuid="d7a5cf55-b5ea-4031-8014-3aa66600a4ba" total-result-count="10405356" first-result="1">
                        <hwsearch:result index="1">
                            <hwsearch:atom-uri>/jbc/288/4/2261.atom</hwsearch:atom-uri>
                            <hwsearch:highwire-id>jbc;M112.411033</hwsearch:highwire-id>
                            <hwsearch:pubmed-id>pmid;23212921</hwsearch:pubmed-id>
                            <hwsearch:georef-id/>
                            <hwsearch:kwic/>
                            <hwsearch:highwire-title>Test Title</hwsearch:highwire-title>
                        </hwsearch:result>
                        <hwsearch:result index="2">
                            <hwsearch:atom-uri>/jbc/288/4/2290.atom</hwsearch:atom-uri>
                            <hwsearch:highwire-id>jbc;M112.417337</hwsearch:highwire-id>
                            <hwsearch:pubmed-id>pmid;23209297</hwsearch:pubmed-id>
                            <hwsearch:georef-id/>
                            <hwsearch:kwic/>
                            <hwsearch:highwire-title>Structural Determinants of Ubiquitin Conjugation in Entamoeba
                                histolytica</hwsearch:highwire-title>
                        </hwsearch:result>
                    </hwsearch:results>';


    public function testFirstTitle() {
        $dom = new BetterDOMDocument($this->xml);
        $this->assertEquals("Test Title", $dom->xpathSingle('.//hwsearch:highwire-title', "//hwsearch:result[@index='1']")->nodeValue);
    }
}
