<?php namespace BetterDOMDocument;

// A BetterDOMNodeList object, which is very similar to a DOMNodeList, but it iterates in a reasonable way.
// Specifically, replacing or removing a node in the list won't screw up the index.
class DOMList implements \Countable, \Iterator {
  
  private $array = array();
  private $position = 0;
  
  private $length = 0;
  private $dom;
  
  public function __construct(\DOMNodeList $DOMNodeList, DOMDoc $dom) {
    foreach ($DOMNodeList as $item) {
      $this->array[] = $item;
    }
    
    $this->dom = $dom;
    $this->length = count($this->array);
    $this->position = 0;
  }
  
  // Provides read-only access to $length and $dom
  public function __get ($prop) {
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

  public function rewind() {
    $this->position = 0;
  }

  public function current() {
    return $this->array[$this->position];
  }

  public function key() {
    return $this->position;
  }

  public function next() {
    ++$this->position;
  }

  public function valid() {
    return isset($this->array[$this->position]);
  }
  
  public function item($index) {
    if (isset($this->array[$index])) {
      return $this->array[$index];
    }
    else return FALSE;
  }
  
  public function count() {
    return count($this->array);
  }
}