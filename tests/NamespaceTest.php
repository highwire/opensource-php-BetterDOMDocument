<?php

use BetterDOMDocument\DOMDoc;

class NamespaceTest extends \PHPUnit_Framework_TestCase {
    public function testLookup() {
      $dom = DOMDoc::loadFile("tests/testdata/note.namespaced.xml");

      $this->assertEquals("note", $dom->lookupPrefix('http://my-example-namespace'));
      $this->assertEquals("http://my-example-namespace", $dom->lookupURL('note'));
    }

    public function testChange() {
      $dom = DOMDoc::loadFile("tests/testdata/helloworld.xhtml");

      $dom->changeNamespace('//html:img', 'img', 'http://www.w3.org/1998/Image');

      $this->assertNotEmpty($dom->xpath('//img:img'));
    }

}
