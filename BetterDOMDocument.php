<?php
/**
 * Highwire Better DOM Document
 *
 * Copyright (c) 2010-2011 Board of Trustees, Leland Stanford Jr. University
 * This software is open-source licensed under the GNU Public License Version 2 or later
 */

class BetterDOMDocument extends DOMDocument {

  private $auto_ns = FALSE;
  public  $ns = array();
  public  $error_checking = 'strict'; // Can be 'strict', 'warning', 'none' / FALSE
  
  /*
   * Create a new BetterDOMDocument
   *
   * @param mixed $xml
   *  $xml can either be an XML string, a DOMDocument, or a DOMElement. 
   *  You can also pass FALSE or NULL (or omit it) and load XML later using loadXML or loadHTML
   * 
   * @param bool $auto_register_namespaces 
   *  Auto-register namespaces. All namespaces in the root element will be registered for use in xpath queries.
   *  Namespaces that are not declared in the root element will not be auto-registered
   *  defaults to FALSE
   * 
   * @param bool $error_checking
   *  Can be 'strict', 'warning', or 'none. Defaults to 'strict'.
   *  'none' supresses all errors
   *  'warning' is the default behavior in DOMDocument
   *  'strict' corresponds to DOMDocument strictErrorChecking TRUE
   */
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
      $class = get_class($xml);
      if ($class == 'DOMElement') {
        $this->appendChild($this->importNode($xml, true));
      }
      if ($class == 'DOMDocument') {
        if ($xml->documentElement) {
          $this->appendChild($this->importNode($xml->documentElement, true));
        }
      }
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

  /*
   * Register a namespace to be used in xpath queries
   *
   * @param string $namespace
   *  Namespace prefix to register
   *
   * @param string $url
   *  Connonical URL for this namespace prefix
   */
  function registerNamespace($namespace, $url) {
    $this->ns[$namespace] = $url;
  }

  /*
   * Get the list of registered namespaces as an array
   */
  function getNamespaces() {
    return $this->ns;
  }

  /*
   * Given an xpath, get a list of nodes.
   * 
   * @param string $xpath
   *  xpath to be used for query
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Provides context for the xpath query
   * 
   * @return BetterDOMNodeList
   *  A BetterDOMNodeList object, which is very similar to a DOMNodeList, but it iterates in a non-shitty fasion.
   */
  function query($xpath, $context = NULL) {
    $this->createContext($context, 'xpath', FALSE);
    
    $xob = new DOMXPath($this);

    // Register the namespaces
    foreach ($this->ns as $namespace => $url) {
      $xob->registerNamespace($namespace, $url);
    }

    // PHP is a piece of shit when it comes to XML namespaces
    // Instead of passing the context node, hack the xpath query to manually construct context using xpath
    if ($context) {
      $ns = $context->namespaceURI;
      $lookup = array_flip($this->ns);
      $prefix = $lookup[$ns];
      $prepend = str_replace('/', '/'. $prefix . ':', $context->getNodePath());
      return new BetterDOMNodeList($xob->query($prepend . $xpath));
    }
    else {
      return new BetterDOMNodeList($xob->query($xpath));
    }
  }
  
  /*
   * Given an xpath, a single node (first one found)
   * 
   * @param string $xpath
   *  xpath to be used for query
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Provides context for the xpath query
   * 
   * @return mixed
   *  The first node found by the xpath query
   */
  function querySingle($xpath, $context = NULL) {
    $result = $this->query($xpath, $context);
    
    if (empty($result) || !count($result)) {
      return FALSE;
    }
    else {
      return $result->item(0);
    }
  }

  /*
   * Alias for querySingle for backwards compatibility
   */
  function query_single($xpath, $context = NULL) {
    return $this->querySingle($xpath, $context);
  }

  /*
   * Get the document (or an element) as an array
   *
   * @param string $raw
   *  Can be either FALSE, 'full', or 'inner'. Defaults to FALSE.
   *  When set to 'full' every node's full XML is also attached to the array
   *  When set to 'inner' every node's inner XML is attached to the array.
   * 
   * @param mixed $context 
   *  Optional context node. Can pass an DOMElement object or an xpath string.
   *  If passed, only the given node will be used when generating the array 
   */
  function getArray($raw = FALSE, $context = NULL) {
    $array = false;

    $this->createContext($context, 'xpath', FALSE);
    
    if ($context) {
      if ($raw == 'full') {
        $array['#raw'] = $this->saveXML($context);
      }
      if ($raw == 'inner') {
        $array['#raw'] = $this->innerText($context);
      }
      if ($context->hasAttributes()) {
        foreach ($context->attributes as $attr) {
          $array['@'.$attr->nodeName] = $attr->nodeValue;
        }
      }
  
      if ($context->hasChildNodes()) {
        if ($context->childNodes->length == 1 && $context->firstChild->nodeType == XML_TEXT_NODE) {
          $array['#text'] = $context->firstChild->nodeValue;
        }
        else {
          foreach ($context->childNodes as $childNode) {
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
  
  /*
   * Get the inner text of an element
   * 
   * @param mixed $context 
   *  Optional context node. Can pass an DOMElement object or an xpath string.
   */
  function innerText($context = NULL) {
    $this->createContext($context, 'xpath');
    
    $pattern = "/<".preg_quote($context->nodeName)."\b[^>]*>(.*)<\/".preg_quote($context->nodeName).">/s";
    $matches = array();
    preg_match($pattern, $this->saveXML($context), $matches);
    return $matches[1];
  }

  /*
   * Create an DOMElement from XML and attach it to the DOMDocument
   * 
   * Note that this does not place it anywhere in the dom tree, it merely imports it.
   * 
   * @param string $xml 
   *  XML string to import
   */
  function createElementFromXML($xml) {
    //@@TODO: Merge namespaces
    $dom = new BetterDOMDocument($xml, $this->auto_ns);
    if (!$dom->documentElement) {
      //print var_dump(debug_backtrace(2));
      trigger_error('BetterDomDocument Error: Invalid XML: ' . $xml);
    }
    $element = $dom->documentElement;
    return $this->importNode($element, true);
  }

  /*
   * Append a child to the context node, make it the last child
   * 
   * @param mixed $newnode
   *  $newnode can either be an XML string, a DOMDocument, or a DOMElement. 
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Omiting $context results in using the root document element as the context
   * 
   * @return DOMElement
   *  The $newnode, properly attached to DOMDocument. If you passed $newnode as a DOMElement
   *  then you should replace your DOMElement with the returned one.
   */
  function append($newnode, $context = NULL) {
    $this->createContext($newnode, 'xml');
    $this->createContext($context, 'xpath');
    
    if (!$context || !$newnode) {
      return FALSE;
    }
    
    return $context->appendChild($newnode);
  }
  
  /*
   * Append a child to the context node, make it the first child
   * 
   * @param mixed $newnode
   *  $newnode can either be an XML string, a DOMDocument, or a DOMElement. 
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Omiting $context results in using the root document element as the context
   *
   * @return DOMElement
   *  The $newnode, properly attached to DOMDocument. If you passed $newnode as a DOMElement
   *  then you should replace your DOMElement with the returned one.
   */
  function prepend($newnode, $context = NULL) {
    $this->createContext($newnode, 'xml');
    $this->createContext($context, 'xpath');
    
    if (!$context || !$newnode) {
      return FALSE;
    }
    
    return $context->insertBefore($newnode, $context->firstChild);
  }

  /*
   * Prepend a sibling to the context node, put it just before the context node
   * 
   * @param mixed $newnode
   *  $newnode can either be an XML string, a DOMDocument, or a DOMElement. 
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Omiting $context results in using the root document element as the context 
   * 
   * @return DOMElement
   *  The $newnode, properly attached to DOMDocument. If you passed $newnode as a DOMElement
   *  then you should replace your DOMElement with the returned one.
   */
  function prependSibling($newnode, $context) {
    $this->createContext($newnode, 'xml');
    $this->createContext($context, 'xpath');
    
    if (!$context || !$newnode) {
      return FALSE;
    }
    
    return $context->parentNode->insertBefore($newnode, $context);
  }
  
  /*
   * Append a sibling to the context node, put it just after the context node
   * 
   * @param mixed $newnode
   *  $newnode can either be an XML string, a DOMDocument, or a DOMElement. 
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Omiting $context results in using the root document element as the context 
   * 
   * @return DOMElement
   *  The $newnode, properly attached to DOMDocument. If you passed $newnode as a DOMElement
   *  then you should replace your DOMElement with the returned one.
   */
  function appendSibling($newnode, $context) {
    $this->createConext($newnode, 'xml');
    $this->createConext($context, 'xpath');
    
    if (!$context){
      return FALSE;
    }
    
    if ($context->nextSibling) { 
      // $context has an immediate sibling : insert newnode before this one 
      return $context->parentNode->insertBefore($newnode, $context->nextSibling); 
    }
    else { 
      // $context has no sibling next to it : insert newnode as last child of it's parent 
      return $context->parentNode->appendChild($newnode); 
    }
  }
  
  /*
   * Given an xpath or DOMElement, return a new BetterDOMDocument.
   * 
   * @param mixed $node
   *  $node can either be an xpath string or a DOMElement. 
   * 
   * @return BetterDOMDocument
   *  A new BetterDOMDocument created from the xpath or DOMElement
   */
  function extract($node) {
    $this->createContext($node, 'xpath');
    
    $dom = new BetterDOMDocument($node);
    $dom->ns = $this->ns;
    return $dom;
  }
  
  /*
   * Given a pair of nodes, replace the first with the second
   * 
   * @param mixed $node
   *  Node to be replaced. Can either be an xpath string or a DOMDocument (or even a DOMNode).
   * 
   * @param mixed $replace
   *  Replace $node with $replace. Replace can be an XML string, or a DOMNode
   * 
   * @return replaced node
   *   The overwritten / replaced node.
   */
  function replace($node, $replace) {
    $this->createContext($node, 'xpath');
    $this->createContext($replace, 'xml');
    
    if (!$node || !$replace) {
      return FALSE;
    }
        
    if (!$replace->ownerDocument->documentElement->isSameNode($this->documentElement)) {
      $replace = $this->importNode($replace, true);
    }
    $node->parentNode->replaceChild($replace, $node);
    $node = $replace;
    return $node;
  }

  /*
   * Given a node(s), remove / delete them
   * 
   * @param mixed $node
   *  Can pass a DOMNode, a BetterDOMNodeList, DOMNodeList, an xpath string, or an array of any of these.
   */
  function remove($node) {
    // We can't use createContext here because we want to use the entire nodeList (not just a single element)
    if (is_string($node)) {
      $node = $this->query($node);
    }
    
    if ($node) {
      if (is_array($node) || get_class($node) == 'BetterDOMNodeList') {
        foreach($node as $item) {
          $this->remove($item);
        }
      }
      else if (get_class($node) == 'DOMNodeList') {
        $this->remove(new BetterDOMNodeList($node));
      }
      else {
        $parent = $node->parentNode;
        $nsuri = $parent->namespaceURI;
        $parent->removeChild($node);
        if (!$parent->parentNode) {
          $parent->namespaceURI = $nsuri;
        }
      }
    }
  }
  
  /*
   * Given an XSL string, transform the BetterDOMDocument (or a passed context node)
   * 
   * @param string $xsl
   *   XSL Transormation
   * 
   * @param mixed $context
   *   $context can either be an xpath string, or a DOMElement. Ommiting it
   *   results in transforming the entire document
   * 
   * @return a new BetterDOMDocument
   */
  function tranform($xsl, $context = NULL) {
    if (!$context) {
      $doc = $this;
    }
    else {
      if (is_string($context)) {
        $context = $this->querySingle($context);
      }
      $doc = new BetterDOMDocument($context);
    }
    
    $xslDoc = new BetterDOMDocument($xsl);
    $xslt = new XSLTProcessor();
    $xslt->importStylesheet($xslDoc);
    
    return new BetterDomDocument($xslt->transformToDoc($doc));
  }

  /*
   * Get a lossless HTML representation of the XML
   *
   * Transforms the document (or passed context) into a set of HTML spans.
   * The element name becomes the class, all other attributes become HTML5
   * "data-" attributes.
   * 
   * @param mixed $context
   *   $context can either be an xpath string, or a DOMElement. Ommiting it
   *   results in transforming the entire document
   * 
   * @return HTML string
   */  
  function asHTML($context = NULL) {
    $xsl = '
      <xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
      <xsl:template match="*">
      <span class="{translate(name(.),\':\',\'-\')}">
        <xsl:for-each select="./@*">
          <xsl:attribute name="data-{translate(name(.),\':\',\'-\')}">
            <xsl:value-of select="." />
          </xsl:attribute>
        </xsl:for-each>
        <xsl:apply-templates/>
      </span>
      </xsl:template>
      </xsl:stylesheet>';
    
    $transformed = $this->tranform($xsl, $context);
    return $transformed->out();
  }

  /*
   * Output the BetterDOMDocument as an XML string
   * 
   * @param mixed $context
   *   $context can either be an xpath string, or a DOMElement. Ommiting it
   *   results in outputting the entire document
   * 
   * @return XML string
   */  
  function out($context = NULL) {
    $this->createContext($context, 'xpath');
    if (!$context) {
      return '';
    }
    
    return $this->saveXML($context, LIBXML_NOEMPTYTAG);
  }
  
  private function createContext(&$context, $type = 'xpath', $createDocument = TRUE) {
    if (!$context && $createDocument) {
      $context = $this->documentElement;
      return;
    }
    if ($context && is_string($context)) {
      if ($type == 'xpath') {
        $context = $this->querySingle($context);
        return;
      }
      if ($type = 'xml') {
        $context = $this->createElementFromXML($context);
        return;
      }
    }
  }
  
}


// A BetterDOMNodeList object, which is very similar to a DOMNodeList, but it iterates in a reasonable way.
// Specifically, replacing or removing a node in the list won't screw up the index.
class BetterDOMNodeList implements Countable, Iterator {
  
  private $array = array();
  private $position = 0;
  
  private $length = 0;
  
  function __construct(DOMNodeList $DOMNodeList) {
    foreach ($DOMNodeList as $item) {
      $this->array[] = $item;
    }
    
    $this->length = count($this->array);
    $this->position = 0;
  }
  
  // Provides read-only access to $length
  function __get ($prop) {
    if ($prop == 'length') {
      return $this->length;
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
    return $this->array[$index];
  }
  
  function count() {
    return count($this->array);
  }
}