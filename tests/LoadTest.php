<?php

use BetterDOMDocument\DOMDoc;

class LoadTest extends PHPUnit_Framework_TestCase {
    public function testLoadFile() {
      $dom = DOMDoc::loadFile("tests/testdata/note.xml");
      $this->assertNotEmpty($dom);

      $from = $dom->xpathSingle('//from/text()');
      $this->assertEquals("Jani", $dom->xpathSingle('//from/text()')->nodeValue);
    }
}
