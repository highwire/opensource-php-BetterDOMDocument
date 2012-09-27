<?php
/**
 * Highwire Better DOM Document
 *
 * Copyright (c) 2010-2011 Board of Trustees, Leland Stanford Jr. University
 * This software is open-source licensed under the GNU Public License Version 2 or later
 */

class BetterDOMDocument extends DOMDocument {

  private $ns = array();
  private $auto_ns = FALSE;
  public  $error_checking = 'strict'; // Can be 'strict', 'warning', 'none' / FALSE

  function __construct($xml = FALSE, $auto_register_namespaces = FALSE, $error_checking = 'strict') {
    parent::__construct();
    
    // Check up error-checking
    if ($error_checking == FALSE) {
      $this->error_checking = 'none';
    }
    else {
      $this->error_checking = $error_checking;
    }
    if ($this->error_checking != 'strict') {
      $this->strictErrorChecking = FALSE;
    }
    
    if(is_object($xml)){
      $this->appendChild($this->importNode($xml, true));
    }

    if ($xml && is_string($xml)) {
      if ($this->error_checking == 'none') {
        @$this->loadXML($xml);
      }
      else {
        $this->loadXML($xml);
      }

      // There is no way in DOMDocument to auto-detect or list namespaces.
      // Regretably the only option is to parse the first container element for xmlns psudo-attributes
      if ($auto_register_namespaces) {
        $this->auto_ns = TRUE;
        if (preg_match('/<[^\?^!].+?>/s', $xml, $elem_match)) {
          if (preg_match_all('/xmlns:(.+?)=.*?["\'](.+?)["\']/s', $elem_match[0], $ns_matches)) {
            foreach ($ns_matches[1] as $i => $ns_key) {
              $this->registerNamespace(trim($ns_key), trim($ns_matches[2][$i]));
            }
          }
        }
      }
    }
  }

  // $raw should be FALSE, 'full', or 'inner'
  function getArray($raw = FALSE, $node = NULL) {
    $array = false;

    if ($node) {
      if ($raw == 'full') {
        $array['#raw'] = $this->saveXML($node);
      }
      if ($raw == 'inner') {
        $pattern = "/<".preg_quote($node->nodeName)."\b[^>]*>(.*)<\/".preg_quote($node->nodeName).">/s";
        $matches = array();
        preg_match($pattern, $this->saveXML($node), $matches);
        $array['#raw'] = $matches[1];
      }
      if ($node->hasAttributes()) {
        foreach ($node->attributes as $attr) {
          $array['@'.$attr->nodeName] = $attr->nodeValue;
        }
      }

      if ($node->hasChildNodes()) {
        if ($node->childNodes->length == 1 && $node->firstChild->nodeType == XML_TEXT_NODE) {
          $array['#text'] = $node->firstChild->nodeValue;
        }
        else {
          foreach ($node->childNodes as $childNode) {
            if ($childNode->nodeType == XML_ELEMENT_NODE) {
              $array[$childNode->nodeName][] = $this->getArray($raw, $childNode);
            }
          }
        }
      }
    }
    // Else no node was passed, which means we are processing the entire domDocument
    else {
      foreach ($this->childNodes as $childNode) {
        if ($childNode->nodeType == XML_ELEMENT_NODE) {
          $array[$childNode->nodeName][] = $this->getArray($raw, $childNode);
        }
      }
    }

    return $array;
  }

  function getNamespaces() {
    return $this->ns;
  }

  function createElementFromXML($xml) {
    $dom = new BetterDOMDocument($xml, $this->auto_ns);
    if (!$dom->documentElement) {
      highwire_system_message('BetterDomDocument Error: Invalid XML: ' . $xml, 'error');
    }
    $element = $dom->documentElement;
    return $this->importNode($element, true);
  }

  function query($xpath, $contextnode = NULL) {
    $xob = new DOMXPath($this);

    // Register the namespaces
    foreach ($this->ns as $namespace => $url) {
      $xob->registerNamespace($namespace, $url);
    }

    // DOMDocument is a piece of shit when it comes to namespaces
    // Instead of passing the context node, hack the xpath query to manually construct context using xpath
    if ($contextnode) {
      $ns = $contextnode->namespaceURI;
      $lookup = array_flip($this->ns);
      $prefix = $lookup[$ns];
      $prepend = str_replace('/', '/'. $prefix . ':', $contextnode->getNodePath());
      return $xob->query($prepend . $xpath);
    }
    else {
      return $xob->query($xpath);
    }
  }

  function querySingle($xpath, $contextnode = NULL) {
    $result = $this->query($xpath, $contextnode);
    if ($result->length) {
      return $result->item(0);
    }
    else {
      return NULL;
    }
  }

  // Alias for backwards compat
  function query_single($xpath, $contextnode = NULL) {
    return $this->querySingle($xpath, $contextnode);
  }

  function registerNamespace($namespace, $url) {
    $this->ns[$namespace] = $url;
  }

  //@@TODO: allow passing of an xpath string
  function replace(&$node, $replace) {
    if (is_string($replace)) {
      $replace = $this->createElementFromXML($replace);
    }
    $node->parentNode->replaceChild($replace, $node);
    $node = $replace;
  }

  // Can pass a DOMNode, a DOMNodeList, or an xpath string
  function remove($node) {
    if (is_string($node)) {
      $node = $this->query($node);
    }
    if ($node) {
      if (get_class($node) == 'DOMNodeList') {
        foreach($node as $item) {
          $this->remove($item);
        }
      }
      else {
        $node->parentNode->removeChild($node);
      }
    }
  }

  // contextnode can be either a DOMNode or an xpath string
  function out($contextnode = NULL) {
    if (!$contextnode) {
      $contextnode = $this->firstChild;
    }
    if (is_string($contextnode)) {
      $contextnode = $this->querySingle($contextnode);
    }
    return $this->saveXML($contextnode, LIBXML_NOEMPTYTAG);
  }

}

