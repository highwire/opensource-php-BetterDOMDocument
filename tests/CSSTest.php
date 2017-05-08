<?php

use BetterDOMDocument\DOMDoc;

class CSSTest extends \PHPUnit_Framework_TestCase {
    public function testSelect() {
      $dom = DOMDoc::loadFile("tests/testdata/helloworld.html");

      $this->assertNotEmpty($dom->select('.image'));
      $this->assertEmpty($dom->select('.asdf'));

      $this->assertNotEmpty($dom->selectSingle('.image'));
      $this->assertEmpty($dom->selectSingle('.asdf'));
    }
}
