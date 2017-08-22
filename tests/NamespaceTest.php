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

    public function testRemove() {
      $dom = new DOMDoc("<html xmlns:mml='http://www.w3.org/1998/Math/MathM'><div><mml:math><mml:infinity /></mml:math></div></html>");

      $dom->removeNamespace('mml');

      $this->assertEquals($dom->out(), "<html><div><math><infinity></infinity></math></div></html>");
    }

}
