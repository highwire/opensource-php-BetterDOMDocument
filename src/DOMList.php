<?php namespace BetterDOMDocument;

// A BetterDOMNodeList object, which is very similar to a DOMNodeList, but it iterates in a reasonable way.
// Specifically, replacing or removing a node in the list won't screw up the index.
class DOMList implements \Countable, \Iterator {
  
  private $array = array();
  private $position = 0;
  
  private $length = 0;
  private $dom;
  
  function __construct(\DOMNodeList $DOMNodeList, DOMDoc $dom) {
    foreach ($DOMNodeList as $item) {
      $this->array[] = $item;
    }
    
    $this->dom = $dom;
    $this->length = count($this->array);
    $this->position = 0;
  }
  
  // Provides read-only access to $length and $dom
  function __get ($prop) {
    if ($prop == 'length') {
      return $this->length;
    }
    else if ($prop == 'dom') {
      return $this->dom;
    }
    else {
      return null;
    }
  }

  function rewind() {
    $this->position = 0;
  }

  function current() {
    return $this->array[$this->position];
  }

  function key() {
    return $this->position;
  }

  function next() {
    ++$this->position;
  }

  function valid() {
    return isset($this->array[$this->position]);
  }
  
  function item($index) {
    if (isset($this->array[$index])) {
      return $this->array[$index];
    }
    else return FALSE;
  }
  
  function count() {
    return count($this->array);
  }
}