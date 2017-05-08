<?php

use BetterDOMDocument\DOMDoc;

class MethodTest extends \PHPUnit_Framework_TestCase {
    public function testExtract() {
      $dom = DOMDoc::loadFile("tests/testdata/note.xml");

      $extracted = $dom->extract('//to');
      $this->assertEquals('<to>Tove</to>', $extracted->out());
    }

    public function testReplace() {
      $dom = DOMDoc::loadFile("tests/testdata/note.xml");

      $dom->replace('//to', '<to>Bon</to>');
      $extracted = $dom->extract('//to');
      $this->assertEquals('<to>Bon</to>', $extracted->out());
    }

    public function testAppend() {
      $dom = DOMDoc::loadFile("tests/testdata/note.xml");

      $dom->append('<to>Bon</to>');
      $extracted = $dom->extract('//to[2]');
      $this->assertEquals('<to>Bon</to>', $extracted->out());
    }

    public function testAppendSibling() {
      $dom = DOMDoc::loadFile("tests/testdata/note.xml");

      $dom->appendSibling('<to>Bon</to>', '//to');
      $extracted = $dom->extract('//to[2]');
      $this->assertEquals('<to>Bon</to>', $extracted->out());
    }

    public function testPrepend() {
      $dom = DOMDoc::loadFile("tests/testdata/note.xml");

      $dom->prepend('<to>Bon</to>');
      $extracted = $dom->extract('//to[1]');
      $this->assertEquals('<to>Bon</to>', $extracted->out());
    }

    public function testPrependSibling() {
      $dom = DOMDoc::loadFile("tests/testdata/note.xml");

      $dom->prependSibling('<to>Bon</to>', '//to');
      $extracted = $dom->extract('//to[1]');
      $this->assertEquals('<to>Bon</to>', $extracted->out());
    }

    public function testRemove() {
      $dom = DOMDoc::loadFile("tests/testdata/note.xml");

      $dom->remove('//body');
      $extracted = $dom->extract('//body');
      $this->assertEquals('', $extracted->out());
    }

    public function testHTML() {
      $dom = DOMDoc::loadFile("tests/testdata/helloworld.html");

      $extracted = $dom->out('//img');
      $this->assertEquals('<img class="image square" src="helloworld.jpg"/>', $extracted);
    }
  
    public function testArray() {
      $dom = DOMDoc::loadFile("tests/testdata/helloworld.html");

      $a = $dom->getArray();
      $this->assertEquals('image square', $a['html'][0]['body'][0]['img'][0]['@class']);
      $this->assertEquals('Hi there world.', $a['html'][0]['body'][0]['p'][0]['#text']);
      
      $a = $dom->getArray('full');
      $this->assertEquals('<img class="image square" src="helloworld.jpg"/>', $a['html'][0]['body'][0]['img'][0]['#raw']);
      
      $a = $dom->getArray('inner');
      $this->assertEquals('Hi there world.', $a['html'][0]['body'][0]['p'][0]['#raw']);
    }
}
