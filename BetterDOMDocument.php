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
  public  $default_ns = '';
  public  $error_checking = 'strict'; // Can be 'strict', 'warning', 'none' / FALSE
  
  /*
   * Create a new BetterDOMDocument
   *
   * @param mixed $xml
   *  $xml can either be an XML string, a DOMDocument, or a DOMElement. 
   *  You can also pass FALSE or NULL (or omit it) and load XML later using loadXML or loadHTML
   * 
   * @param mixed $auto_register_namespaces 
   *  Auto-register namespaces. All namespaces in the root element will be registered for use in xpath queries.
   *  Namespaces that are not declared in the root element will not be auto-registered
   *  Defaults to TRUE (Meaning it will auto register all auxiliary namespaces but not the default namespace).
   *  Pass a prefix string to automatically register the default namespace.
   *  Pass FALSE to disable auto-namespace registeration
   * 
   * @param bool $error_checking
   *  Can be 'strict', 'warning', or 'none. Defaults to 'strict'.
   *  'none' supresses all errors
   *  'warning' is the default behavior in DOMDocument
   *  'strict' corresponds to DOMDocument strictErrorChecking TRUE
   */
  function __construct($xml = FALSE, $auto_register_namespaces = TRUE, $error_checking = 'strict') {
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
      if ($class == 'BetterDOMDocument') {
        if ($xml->documentElement) {
          $this->appendChild($this->importNode($xml->documentElement, true));
        }
        $this->ns = $xml->ns;
        $this->default_ns = $xml->default_ns;
      }
    }

    if ($xml && is_string($xml)) {
      if ($this->error_checking == 'none') {
        @$this->loadXML($xml);
      }
      else {
        if (!$this->loadXML($xml)) {
          trigger_error('BetterDOMDocument: Could not load: ' . htmlspecialchars($xml), E_USER_WARNING);
        }
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
        
        // If auto_register_namespaces is a prefix string, then we register the default namespace to that string
        if (is_string($auto_register_namespaces) && $this->documentElement->getAttribute('xmlns')) {
          $this->registerNamespace($auto_register_namespaces, $this->documentElement->getAttribute('xmlns'));
          $this->default_ns = $auto_register_namespaces;
        }
      }
    }
  }

  /*
   * Register a namespace to be used in xpath queries
   *
   * @param string $prefix
   *  Namespace prefix to register
   *
   * @param string $url
   *  Connonical URL for this namespace prefix
   */
  function registerNamespace($prefix, $url) {
    $this->ns[$prefix] = $url;
  }

  /*
   * Get the list of registered namespaces as an array
   */
  function getNamespaces() {
    return $this->ns;
  }

  /*
   * Given a namespace URL, get the prefix
   * 
   * @param string $url
   *  Connonical URL for this namespace prefix
   */
  function lookupPrefix($url) {
    return array_search($url, $this->ns);
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

    // Special xpath extension - allow querying by class by using the '.' operator
    if (strpos($xpath, '.') !== FALSE) {
      $xpath = preg_replace_callback("|[^\\/\[\]]*\.[^\\/\[\]]*|", "BetterDOMDocument::ClassSelectorTransform", $xpath);
    }

    $this->createContext($context, 'xpath', FALSE);

    if ($context === FALSE) {
      return FALSE;
    }
    
    $xob = new DOMXPath($this);

    // Register the namespaces
    foreach ($this->ns as $namespace => $url) {
      $xob->registerNamespace($namespace, $url);
    }
    
    // PHP is a piece of shit when it comes to XML namespaces and contexts
    // Instead of passing the context node, hack the xpath query to manually construct context using xpath
    // The bug is that DOMXPath requires default-namespaced queries explicitly pass a prefix, whereas getNodePath() does not provide a prefix
    // if the node is in the default namepspace. This logic works around this bug.
    if ($context) {
      $raw_path_parts = $context->getNodePath();

      // Either there are no namespaces available, or namespaces have been properly built in the path
      if (!$context->namespaceURI || strpos($raw_path_parts, ':')) {
        $context_query = $raw_path_parts;
      }
      else {
        // There's no namespaces in the query string, but the context is namespaced. 
        // This is a bug in DOMDocument (surprise surprise), we need to try to manually construct the path.
        $prefix = $this->lookupPrefix($context->namespaceURI);
        $context_query = '';
        $path_parts = explode('/', $raw_path_parts);
        foreach ($path_parts as $part) {
          if ($part) {
            $context_query .= $prefix . ':' . $part;
          }
        }
      }

      $result = $xob->query($context_query . $xpath);
    }
    else {
      $result = $xob->query($xpath);
    }

    if ($result) {
      return new BetterDOMNodeList($result, $this);
    }
    else {
      return FALSE;
    }
  }

  /*
   * Callback for preg_replace_callback
   * 
   * Replace all instances of "div.foo" with a selector that will select on class
   */
  function ClassSelectorTransform($matches) {
    $parts = explode('.', $matches[0]);
    $element = array_shift($parts);

    $output = $element . '[';
    foreach ($parts as $i => $part) {
      $output .= "contains(@class, '$part')";
      if ($i != count($parts) -1) {
        $output .= " or ";
      }
    }
    $output .= "]";

    return $output;
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
   * Given a CSS selector, get a list of nodes.
   * 
   * @param string $selector
   *  CSS selector to be used
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Provides context for the CSS selector
   * 
   * @return BetterDOMNodeList
   *  A BetterDOMNodeList object, which is very similar to a DOMNodeList, but it iterates in a non-shitty fasion.
   */  
  function select($selector, $context = null) {
    return $this->query($this->transformCSS($selector), $context);
  }

  /*
   * Given a CSS Selector, get a single node (first one found)
   * 
   * @param string $selector
   *  CSS selector
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Provides context for the CSS selector
   * 
   * @return mixed
   *  The first node found by the CSS selector
   */
  function selectSingle($selector) {
    return $this->querySingle($this->transformCSS($selector), $context);
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
            elseif ($childNode->nodeType == XML_CDATA_SECTION_NODE) {
              $array['#text'] = $childNode->textContent;
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
    
    // To make thing easy and make sure namespaces work properly, we add the root namespace delcarations if it is not declared
    $namespaces = $this->ns;
    $xml = preg_replace_callback('/<[^\?^!].+?>/s', function($root_match) use ($namespaces) {
      preg_match('/<([^ <>]+)[\d\s]?.*?>/s', $root_match[0], $root_tag);
      $new_root = $root_tag[1];
      if (strpos($new_root, ':')) {
        $parts = explode(':', $new_root);
        $prefix = $parts[0]; 
        if (isset($namespaces[$prefix])) {
          if (!strpos($root_match[0], "xmlns:$prefix")) {
            $new_root .= " xmlns:$prefix='" . $namespaces[$prefix] . "'";             
          }
        }
      }
      return str_replace($root_tag[1], $new_root, $root_match[0]);
    }, $xml, 1);
    
    $dom = new BetterDOMDocument($xml, $this->auto_ns);
    if (!$dom->documentElement) {
      trigger_error('BetterDomDocument Error: Invalid XML: ' . $xml);
    }
    $element = $dom->documentElement;
    
    // Merge the namespaces
    foreach ($dom->getNamespaces() as $prefix => $url) {
      $this->registerNamespace($prefix, $url);
    }
    
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
    $this->createContext($newnode, 'xml');
    $this->createContext($context, 'xpath');
    
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
  function extract($node, $auto_register_namespaces = TRUE, $error_checking = 'none') {
    $this->createContext($node, 'xpath');
    $dom = new BetterDOMDocument($node, $auto_register_namespaces, $error_checking);
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
        $this->remove(new BetterDOMNodeList($node, $this));
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

    // Copy namespace prefixes
    if ($this->default_ns && !$context->hasAttribute('xmlns')) {
      $context->setAttribute('xmlns', $namespace);
    }
    foreach ($this->ns as $prefix => $namespace) {
      if (!$context->hasAttribute('xmlns:' . $prefix)) {
        $context->setAttribute('xmlns:' . $prefix, $namespace);
      }
    }
    
    return $this->saveXML($context, LIBXML_NOEMPTYTAG);
  }
  
  private function createContext(&$context, $type = 'xpath', $createDocument = TRUE) {
    if (!$context && $createDocument) {
      $context = $this->documentElement;
      return;
    }

    if (!$context) {
      return FALSE;
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

    if (is_object($context)) {
      if (is_a($context, 'DOMElement')) {
        return $context;
      }
      if (is_a($context, 'DOMDocument')) {
        return $context->documentElement;
      }
    }
  }
  
  // Turns CSS selector in to xpath
  private function transformCSS($path) {
    $path = (string) $path;
    
    if (strstr($path, ',')) {
      $paths       = explode(',', $path);
      $expressions = array();
      foreach ($paths as $path) {
        $xpath = self::transform(trim($path));
        if (is_string($xpath)) {
          $expressions[] = $xpath;
        }
        else if (is_array($xpath)) {
          $expressions = array_merge($expressions, $xpath);
        }
      }
      return implode('|', $expressions);
    }

    $paths    = array('//');
    $path     = preg_replace('|\s+>\s+|', '>', $path);
    $segments = preg_split('/\s+/', $path);
    foreach ($segments as $key => $segment) {
      $pathSegment = $this->transformCSSTokenize($segment);
      if (0 == $key) {
        if (0 === strpos($pathSegment, '[contains(')) {
          $paths[0] .= '*' . ltrim($pathSegment, '*');
        }
        else {
          $paths[0] .= $pathSegment;
        }
        continue;
      }
      if (0 === strpos($pathSegment, '[contains(')) {
        foreach ($paths as $pathKey => $xpath) {
          $paths[$pathKey] .= '//*' . ltrim($pathSegment, '*');
          $paths[] = $xpath . $pathSegment;
        }
      }
      else {
        foreach ($paths as $pathKey => $xpath) {
          $paths[$pathKey] .= '//' . $pathSegment;
        }
      }
    }

    if (1 == count($paths)) {
      return $paths[0];
    }
    
    return implode('|', $paths);
  }
  
  private function transformCSSTokenize($expression) {
    // Child selectors
    $expression = str_replace('>', '/', $expression);

    // IDs
    $expression = preg_replace('|#([a-z][a-z0-9_-]*)|i', '[@id=\'$1\']', $expression);
    $expression = preg_replace('|(?<![a-z0-9_-])(\[@id=)|i', '*$1', $expression);

    // arbitrary attribute strict equality
    $expression = preg_replace_callback(
      '|\[([a-z0-9_-]+)=[\'"]([^\'"]+)[\'"]\]|i',
      function ($matches) {
        return '[@' . strtolower($matches[1]) . "='" . $matches[2] . "']";
      },
      $expression
    );

    // arbitrary attribute contains full word
    $expression = preg_replace_callback(
      '|\[([a-z0-9_-]+)~=[\'"]([^\'"]+)[\'"]\]|i',
      function ($matches) {
        return "[contains(concat(' ', normalize-space(@" . strtolower($matches[1]) . "), ' '), ' " . $matches[2] . " ')]";
      },
      $expression
    );

    // arbitrary attribute contains specified content
    $expression = preg_replace_callback(
      '|\[([a-z0-9_-]+)\*=[\'"]([^\'"]+)[\'"]\]|i',
      function ($matches) {
        return "[contains(@" . strtolower($matches[1]) . ", '" . $matches[2] . "')]";
      },
      $expression
    );

    // Classes
    $expression = preg_replace(
      '|\.([a-z][a-z0-9_-]*)|i',
      "[contains(concat(' ', normalize-space(@class), ' '), ' \$1 ')]",
      $expression
    );

    /** ZF-9764 -- remove double asterisk */
    $expression = str_replace('**', '*', $expression);

    return $expression;
  }
  
}


// A BetterDOMNodeList object, which is very similar to a DOMNodeList, but it iterates in a reasonable way.
// Specifically, replacing or removing a node in the list won't screw up the index.
class BetterDOMNodeList implements Countable, Iterator {
  
  private $array = array();
  private $position = 0;
  
  private $length = 0;
  private $dom;
  
  function __construct(DOMNodeList $DOMNodeList, BetterDOMDocument $dom) {
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