<?php
/**
 * Highwire Better DOM Document
 *
 * Copyright (c) 2010-2011 Board of Trustees, Leland Stanford Jr. University
 * This software is open-source licensed under the GNU Public License Version 2 or later
 */

class BetterDOMDocument extends DOMDocument {

  private $ns = array();

  function __construct($xml = FALSE) {
    parent::__construct();
    if(is_object($xml)){
      $this->appendChild($this->importNode($xml, true));
    }

    if (is_string($xml)) {
      @$this->loadXML($xml);
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

  function createElementFromXML($xml) {
    $dom = new BetterDOMDocument($xml);
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

  function query_single($xpath, $contextnode = NULL) {
    $result = $this->query($xpath, $contextnode);
    if ($result->length) {
      return $result->item(0);
    }
    else {
      return NULL;
    }
  }

  function registerNamespace($namespace, $url) {
    $this->ns[$namespace] = $url;
  }

  function replace(&$node, $replace) {
    if (is_string($replace)) {
      $replace = $this->createElementFromXML($replace);
    }
    $node->parentNode->replaceChild($replace, $node);
    $node = $replace;
  }

  function out($contextnode = NULL) {
    if (!$contextnode) {
      $contextnode = $this->firstChild;
    }
    return $this->saveXML($contextnode, LIBXML_NOEMPTYTAG);
  }

}

