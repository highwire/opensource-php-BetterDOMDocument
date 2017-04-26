<?php

use BetterDOMDocument\DOMDoc;

class LoadTest extends PHPUnit_Framework_TestCase {
    public function testLoadFile() {
      $dom = DOMDoc::loadFile("tests/testdata/note.xml");
      $this->assertNotEmpty($dom);
      $this->assertEquals("Jani", $dom->xpathSingle('//from/text()')->nodeValue);

      $dom = DOMDoc::loadFile("tests/testdata/note.namespaced.xml");
      $this->assertNotEmpty($dom);
      $this->assertEquals("Jani", $dom->xpathSingle('//note:from/text()')->nodeValue);
    }

    public function testLoadFileNamespaces() {
      $dom = DOMDoc::loadFile("tests/testdata/freebird.atom");
      $this->assertNotEmpty($dom);

      $this->assertEquals("freebird", $dom->xpathSingle('//atom:id')->nodeValue);
      $this->assertEquals("2017-02-08T13:12:20.119231-08:00", $dom->xpathSingle('//app:edited')->nodeValue);
    }

    public function testEmptyLoad() {
      $dom = new DOMDoc();
      $this->assertEquals('', strval($dom));
    }
}
