<?php namespace BetterDOMDocument;

use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * Better DOM Document.
 *
 * Copyright (c) 2010-2011 Board of Trustees, Leland Stanford Jr. University
 * This software is open-source licensed under the GNU Public License Version 2 or later.
 */
class DOMDoc extends \DOMDocument {

  private $auto_ns = FALSE;
  public  $ns = array();
  public  $default_ns = FALSE;
  public  $error_checking = 'strict'; // Can be 'strict', 'warning', 'none' / FALSE

  /**
   * Create a new DOMDoc
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
   *   Can be 'strict', 'warning', or 'none. Defaults to 'strict'.
   *   'none' supresses all errors
   *   'warning' is the default behavior in DOMDocument
   *   'strict' corresponds to DOMDocument strictErrorChecking TRUE
   */
  public function __construct($xml = FALSE, $auto_register_namespaces = TRUE, $error_checking = 'strict') {
    parent::__construct();

    $this->setErrorChecking($error_checking);
    
    if(is_object($xml)){
      if (is_a($xml, 'DOMElement')) {
        $this->appendChild($this->importNode($xml, true));
      }
      else if (is_a($xml, 'BetterDOMDocument\DOMDoc')) {
        if ($xml->documentElement) {
          $this->appendChild($this->importNode($xml->documentElement, true));
        }
        $this->ns = $xml->ns;
      }
      else if (is_a($xml, 'DOMDocument')) {
        if ($xml->documentElement) {
          $this->appendChild($this->importNode($xml->documentElement, true));
        }
      }
      else if (method_exists($xml, '__toString')) {
        $this->loadFromString($xml->__toString());
      }
    }
    else if (is_string($xml) && !empty($xml)) {
      $this->loadFromString($xml);
    }

    if ($auto_register_namespaces) {
      $this->AutoRegisterNamespace($auto_register_namespaces);
    }
  }

  /**
   * Register a namespace to be used in xpath queries
   *
   * @param string $prefix
   *  Namespace prefix to register
   *
   * @param string $url
   *  Connonical URL for this namespace prefix
   */
  public function registerNamespace($prefix, $url) {
    $this->ns[$prefix] = $url;
  }

  /**
   * Get the list of registered namespaces as an array
   */
  public function getNamespaces() {
    return $this->ns;
  }

  /**
   * Given a namespace URL, get the prefix
   * 
   * @param string $url
   *  Connonical URL for this namespace prefix
   * 
   * @return string|false
   *  The namespace prefix or FALSE if there is no namespace with that URL
   */
  public function lookupPrefix($url) {
    return array_search($url, $this->ns);
  }

  /**
   * Given a namespace prefix, get the URL
   * 
   * @param string $prefix
   *  namespace prefix
   * 
   * return string|false
   *  The namespace URL or FALSE if there is no namespace with that prefix
   */
  public function lookupURL($prefix) {
    if (isset($this->ns[$prefix])) {
      return $this->ns[$prefix];
    }
    else {
      return FALSE;
    }
  }

  /**
   * Given an xpath, get a list of nodes.
   * 
   * @param string $xpath
   *  xpath to be used for query
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Provides context for the xpath query
   * 
   * @return DOMList|false
   *  A DOMList object, which is very similar to a DOMNodeList, but with better iterabilility.
   */
  public function xpath($xpath, $context = NULL) {
    $this->createContext($context, 'xpath', FALSE);

    if ($context === FALSE) {
      return FALSE;
    }
    
    $xob = new \DOMXPath($this);

    // Register the namespaces
    foreach ($this->ns as $namespace => $url) {
      $xob->registerNamespace($namespace, $url);
    }

    if ($context) {
      $result = $xob->query($xpath, $context);
    }
    else {
      $result = $xob->query($xpath);
    }

    if ($result) {
      return new DOMList($result, $this);
    }
    else {
      return FALSE;
    }
  }


  /**
   * Given an xpath, get a single node (first one found)
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
  public function xpathSingle($xpath, $context = NULL) {
    $result = $this->xpath($xpath, $context);
    
    if (empty($result) || !count($result)) {
      return FALSE;
    }
    else {
      return $result->item(0);
    }
  }


  /**
   * Given an CSS selector, get a list of nodes.
   * 
   * @param string $css_selector
   *  CSS Selector to be used for query
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Provides context for the CSS selector
   * 
   * @return DOMList|false
   *  A DOMList object, which is very similar to a DOMNodeList, but with better iterabilility.
   */
  public function select($css_selector, $context = NULL) {
    $converter = new CssSelectorConverter();
    $xpath = $converter->toXPath($css_selector);

    return $this->xpath($xpath, $context);
  }

  /**
   * Given an CSS selector, get a single node.
   * 
   * @param string $css_selector
   *  CSS Selector to be used for query
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Provides context for the CSS selector
   * 
   * @return DOMList
   *  A DOMList object, which is very similar to a DOMNodeList, but with better iterabilility.
   */
  public function selectSingle($css_selector, $context = NULL) {
    $converter = new CssSelectorConverter();
    $xpath = $converter->toXPath($css_selector);

    return $this->xpathSingle($xpath, $context);
  }

  /**
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
  public function getArray($raw = FALSE, $context = NULL) {
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
  
  /**
   * Get the inner text of an element
   * 
   * @param mixed $context 
   *  Optional context node. Can pass an DOMElement object or an xpath string.
   */
  public function innerText($context = NULL) {
    $this->createContext($context, 'xpath');
    
    $pattern = "/<".preg_quote($context->nodeName)."\b[^>]*>(.*)<\/".preg_quote($context->nodeName).">/s";
    $matches = array();
    if (preg_match($pattern, $this->saveXML($context), $matches)) {
      return $matches[1];
    }
    else {
      return '';
    }
  }

  /**
   * Create an DOMElement from XML and attach it to the DOMDocument
   * 
   * Note that this does not place it anywhere in the dom tree, it merely imports it.
   * 
   * @param string $xml 
   *  XML string to import
   */
  public function createElementFromXML($xml) {
    
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

    $dom = new DOMDoc($xml, $this->auto_ns);
    if (!$dom->documentElement) {
      trigger_error('BetterDomDocument\DOMDoc Error: Invalid XML: ' . $xml);
    }
    $element = $dom->documentElement;
    
    // Merge the namespaces
    foreach ($dom->getNamespaces() as $prefix => $url) {
      $this->registerNamespace($prefix, $url);
    }
    
    return $this->importNode($element, true);
  }

  /**
   * Append a child to the context node, make it the last child
   * 
   * @param mixed $newnode
   *  $newnode can either be an XML string, a DOMDocument, or a DOMElement. 
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Omiting $context results in using the root document element as the context
   * 
   * @return DOMElement|false
   *  The $newnode, properly attached to DOMDocument. If you passed $newnode as a DOMElement
   *  then you should replace your DOMElement with the returned one.
   */
  public function append($newnode, $context = NULL) {
    $this->createContext($newnode, 'xml');
    $this->createContext($context, 'xpath');
    
    if (!$context || !$newnode) {
      return FALSE;
    }

    return $context->appendChild($newnode);
  }
  
  /**
   * Append a child to the context node, make it the first child
   * 
   * @param mixed $newnode
   *  $newnode can either be an XML string, a DOMDocument, or a DOMElement. 
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Omiting $context results in using the root document element as the context
   *
   * @return DOMElement|false
   *  The $newnode, properly attached to DOMDocument. If you passed $newnode as a DOMElement
   *  then you should replace your DOMElement with the returned one.
   */
  public function prepend($newnode, $context = NULL) {
    $this->createContext($newnode, 'xml');
    $this->createContext($context, 'xpath');
    
    if (!$context || !$newnode) {
      return FALSE;
    }
    
    return $context->insertBefore($newnode, $context->firstChild);
  }

  /**
   * Prepend a sibling to the context node, put it just before the context node
   * 
   * @param mixed $newnode
   *  $newnode can either be an XML string, a DOMDocument, or a DOMElement. 
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Omiting $context results in using the root document element as the context 
   * 
   * @return DOMElement|false
   *  The $newnode, properly attached to DOMDocument. If you passed $newnode as a DOMElement
   *  then you should replace your DOMElement with the returned one.
   */
  public function prependSibling($newnode, $context = NULL) {
    $this->createContext($newnode, 'xml');
    $this->createContext($context, 'xpath');
    
    if (!$context || !$newnode) {
      return FALSE;
    }
    
    return $context->parentNode->insertBefore($newnode, $context);
  }
  
  /**
   * Append a sibling to the context node, put it just after the context node
   * 
   * @param mixed $newnode
   *  $newnode can either be an XML string, a DOMDocument, or a DOMElement. 
   * 
   * @param mixed $context
   *  $context can either be an xpath string, or a DOMElement
   *  Omiting $context results in using the root document element as the context 
   * 
   * @return DOMElement|false
   *  The $newnode, properly attached to DOMDocument. If you passed $newnode as a DOMElement
   *  then you should replace your DOMElement with the returned one.
   */
  public function appendSibling($newnode, $context) {
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
      return $context->parentNode->appendChild($newnode); 
    }
  }
  
  /**
   * Given an xpath or DOMElement, return a new DOMDoc.
   * 
   * @param mixed $node
   *  $node can either be an xpath string or a DOMElement. 
   * 
   * @return DOMDoc
   *  A new DOMDoc created from the xpath or DOMElement
   */
  public function extract($node, $auto_register_namespaces = TRUE, $error_checking = 'none') {
    $this->createContext($node, 'xpath');
    $dom = new DOMDoc($node, $auto_register_namespaces, $error_checking);
    $dom->ns = $this->ns;
    return $dom;
  }
  
  /**
   * Given a pair of nodes, replace the first with the second
   * 
   * @param mixed $node
   *  Node to be replaced. Can either be an xpath string or a DOMDocument (or even a DOMNode).
   * 
   * @param mixed $replace
   *  Replace $node with $replace. Replace can be an XML string, or a DOMNode
   * 
   * @return mixed
   *   The overwritten / replaced node.
   */
  public function replace($node, $replace) {
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

  /**
   * Given a node(s), remove / delete them
   * 
   * @param mixed $node
   *  Can pass a DOMNode, a NodeList, DOMNodeList, an xpath string, or an array of any of these.
   */
  public function remove($node) {
    // We can't use createContext here because we want to use the entire nodeList (not just a single element)
    if (is_string($node)) {
      $node = $this->xpath($node);
    }
    
    if ($node) {
      if (is_array($node) || get_class($node) == 'BetterDOMDocument\DOMList') {
        foreach($node as $item) {
          $this->remove($item);
        }
      }
      else if (get_class($node) == 'DOMNodeList') {
        $this->remove(new DOMList($node, $this));
      }
      else {
        $parent = $node->parentNode;
        $parent->removeChild($node);
      }
    }
  }
  
  /**
   * Given an XSL string, transform the DOMDoc (or a passed context node)
   * 
   * @param string $xsl
   *   XSL Transormation
   * 
   * @param mixed $context
   *   $context can either be an xpath string, or a DOMElement. Ommiting it
   *   results in transforming the entire document
   * 
   * @return a new DOMDoc
   */
  public function tranform($xsl, $context = NULL) {
    if (!$context) {
      $doc = $this;
    }
    else {
      if (is_string($context)) {
        $context = $this->xpathSingle($context);
      }
      $doc = new DOMDoc($context);
    }
    
    $xslDoc = new DOMDoc($xsl);
    $xslt = new \XSLTProcessor();
    $xslt->importStylesheet($xslDoc);
    
    return new DOMDoc($xslt->transformToDoc($doc));
  }

  /**
   * Given a node, change it's namespace to the specified namespace in situ
   * 
   * @param mixed $node
   *  Node to be changed. Can either be an xpath string or a DOMElement.
   * 
   * @param mixed $prefix
   *   prefix for the new namespace
   * 
   * @param mixed $url
   *   The URL for the new namespace
   * 
   * @return mixed
   *   The node with the new namespace. The node will also be changed in-situ in the document as well.
   */
  public function changeNamespace($node, $prefix, $url) {
    $this->createContext($node, 'xpath');
    
    if (!$node) {
      return FALSE;
    }
    
    $this->registerNamespace($prefix, $url);

    if (get_class($node) == 'DOMElement') {
      $xsl = '
        <xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
          <xsl:template match="*">
            <xsl:element name="' . $prefix . ':{local-name()}" namespace="' . $url . '">
             <xsl:copy-of select="@*"/>
             <xsl:apply-templates/>
            </xsl:element>
          </xsl:template>
        </xsl:stylesheet>';

      $transformed = $this->tranform($xsl, $node);
      return $this->replace($node, $transformed->documentElement);   
    }
    else {
      // @@TODO: Report the correct calling file and number
      throw new \Exception("Changing the namespace of a " . get_class($node) . " is not supported");
    }
  }

  /**
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
   * @param array $options
   *   Options for transforming the HTML into XML. The following options are supported:
   *   'xlink' => {TRUE or xpath}
   *     Transform xlink links into <a href> elements. If you specify 'xlink' => TRUE then 
   *     it will transform all elements with xlink:type = simple into a <a href> element. 
   *     Alternatively you may specify your own xpath for selecting which elements get transformed
   *     into <a href> tags. 
   * @return HTML string
   */  
  public function asHTML($context = NULL, $options = array()) {
    $xslSimple = '
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

    $xslOptions = '
      <xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xlink="http://www.w3.org/1999/xlink" ||namespaces||>
      <xsl:template match="*">
        <xsl:choose>
          <xsl:when test="||xlink||">
            <a class="{translate(name(.),\':\',\'-\')}">
              <xsl:for-each select="./@*">
                <xsl:attribute name="data-{translate(name(.),\':\',\'-\')}">
                  <xsl:value-of select="."/>
                </xsl:attribute>
              </xsl:for-each>
              <xsl:attribute name="href">
                <xsl:value-of select="@xlink:href"/>
              </xsl:attribute>
              <xsl:apply-templates/>
            </a>
          </xsl:when>
          <xsl:otherwise>
            <span class="{translate(name(.),\':\',\'-\')}">
              <xsl:for-each select="./@*">
                <xsl:attribute name="data-{translate(name(.),\':\',\'-\')}">
                  <xsl:value-of select="." />
                </xsl:attribute>
              </xsl:for-each>
              <xsl:apply-templates/>
            </span>
          </xsl:otherwise>
        </xsl:choose>
      </xsl:template>
      </xsl:stylesheet>';

    if (!empty($options)) {
      // Add in the namespaces
      foreach ($this->getNamespaces() as $prefix => $url) {
        $namespaces = '';
        if ($prefix != 'xsl' && $prefix != 'xlink') {
          $namespaces .= 'xmlns:' . $prefix . '="' . $url. '" ';
        }
        $xslOptions = str_replace("||namespaces||", $namespaces, $xslOptions);
      }

      // Add in xlink options
      if ($options['xlink'] === TRUE) {
        $options['xlink'] = "@xlink:type = 'simple'";
      }
      else if (empty($options['xlink'])) {
        $options['xlink'] = "false()";
      }
      $xslOptions = str_replace("||xlink||", $options['xlink'], $xslOptions);
      $transformed = $this->tranform($xslOptions, $context);
    }
    else {
      $transformed = $this->tranform($xslSimple, $context);
    }
    
    return $transformed->out();
  }

  /**
   * Output the DOMDoc as an XML string
   * 
   * @param mixed $context
   *   $context can either be an xpath string, or a DOMElement. Ommiting it
   *   results in outputting the entire document
   * 
   * @return XML string
   */  
  public function out($context = NULL) {
    $this->createContext($context, 'xpath');
    if (!$context) {
      return '';
    }

    // Copy namespace prefixes
    foreach ($this->ns as $prefix => $namespace) {
      if (!empty($namespace) && !$context->hasAttribute('xmlns:' . $prefix)) {
        $context->setAttribute('xmlns:' . $prefix, $namespace);
      }
    }
    
    // Check to seee if it's HTML, if it is we need to fix broken html void elements.
    if ($this->documentElement->lookupNamespaceURI(NULL) == 'http://www.w3.org/1999/xhtml' || $this->documentElement->tagName == 'html') {
      $output = $this->saveXML($context, LIBXML_NOEMPTYTAG);
      // The types listed are html "void" elements. 
      // Find any of these elements that have no child nodes and are therefore candidates for self-closing, replace them with a self-closed version. 
      $pattern = '<(area|base|br|col|command|embed|hr|img|input|keygen|link|meta|param|source|track|wbr)(\b[^<]*)><\/\1>';
      return preg_replace('/' . $pattern . '/', '<$1$2/>', $output);
    }
    else {
      return $this->saveXML($context, LIBXML_NOEMPTYTAG);
    }
  }

  /**
   * Magic method for casting a DOMDoc as a string
   */ 
  public function __toString() {
    return $this->out();
  }

  /**
   * Magic method to get good debug info view var_dump
   */ 
  public function __debugInfo() {
    return array(
      'auto_ns' => $this->auto_ns,
      'ns' => $this->ns,
      'default_ns' => $this->default_ns,
      'error_checking' => $this->error_checking, 
      'content' => $this->out(),
    );
  }

  public function setErrorChecking($error_checking) {
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
  }

  public static function loadFile($file_or_url, $auto_register_namespaces = TRUE) {
    $dom = @parent::load($file_or_url, LIBXML_COMPACT);
    if (empty($dom)) {
      return FALSE;
    }

    return new DOMDoc($dom, $auto_register_namespaces);
  }

  public function loadHTML($source, $options = NULL) {
    $success = parent::loadHTML($source, $options);
    $this->AutoRegisterNamespace(TRUE);

    return boolval($success);
  }

  public function loadXML($source, $options = NULL) {
    $success = parent::loadXML($source, $options);
    $this->AutoRegisterNamespace(TRUE);
    
    return boolval($success);
  }

  /**
   * Removes a namespace from the document, moving the
   * namespaced nodes to the default namespace.
   * 
   * @param string $prefix
   *   Namespace prefix.
   */ 
  public function removeNamespace($prefix) {
    $ns_uri = $this->ns[$prefix];
    if (empty($ns_uri)) {
      throw new \Exception("Unknown prefix $prefix. Try calling registerNamespace() first.");
    }
    $nodes = $this->xpath("//*[namespace::{$prefix} and not(../namespace::{$prefix})]");
    foreach ($nodes as $node) {
      $node->removeAttributeNS($ns_uri, $prefix);
    }
    unset($this->ns[$prefix]);
  }

  protected function AutoRegisterNamespace($auto_register_namespaces) {
    $this->auto_ns = TRUE;

    // If it's an "XML" document, then get namespaces via xpath
    $xpath = new \DOMXPath($this);
    foreach($xpath->query('namespace::*') as $namespace) {
      if (!empty($namespace->prefix)) {
        if ($namespace->prefix != 'xml' && $namespace->nodeValue != 'http://www.w3.org/XML/1998/namespace') {
          $this->registerNamespace($namespace->prefix, $namespace->nodeValue);
        }
      }
      else {
        $this->default_ns = $namespace->nodeValue;
        if (is_string($auto_register_namespaces)) {
          $this->registerNamespace($auto_register_namespaces, $namespace->nodeValue);
        }
        // Otherwise, automatically set-up the root element tag name as the prefix for the default namespace
        else {
          $tagname = $this->documentElement->tagName;
          if (empty($this->ns[$tagname])) {
            $this->registerNamespace($tagname, $this->documentElement->getAttribute('xmlns'));
          }
        }
      }
    }

    // If it's an "HTML" document, we get namespaces via attributes
    if (empty($this->ns) && !empty($this->documentElement)) {
      foreach ($this->documentElement->attributes as $attr) {
        if ($attr->name == 'xmlns') {
          $this->default_ns = $attr->value;
          // If auto_register_namespaces is a prefix string, then we register the default namespace to that string
          if (is_string($auto_register_namespaces)) {
            $this->registerNamespace($auto_register_namespaces, $attr->value);
          }
          // Otherwise, automatically set-up the root element tag name as the prefix for the default namespace
          else {
            $tagname = $this->documentElement->tagName;
            if (empty($this->ns[$tagname])) {
              $this->registerNamespace($tagname, $attr->value);
            } 
          }
        }
        else if (substr($attr->name,0,6) == 'xmlns:') {
          $prefix = substr($attr->name,6);
          $this->registerNamespace($prefix, $attr->value); 
        }
      }
    }
  }
  
  protected function createContext(&$context, $type = 'xpath', $createDocument = TRUE) {
    if (!$context && $createDocument) {
      $context = $this->documentElement;
      return;
    }

    if (!$context) {
      return FALSE;
    }

    if ($context && is_string($context)) {
      if ($type == 'xpath') {
        $context = $this->xpathSingle($context);
        return;
      }
      if ($type == 'xml') {
        $context = $this->createElementFromXML($context);
        return;
      }
    }

    if (is_object($context) && is_a($context, 'DOMNode')) {
      if ($context->ownerDocument === $this) {
        return;
      }
      if (is_a($context, 'BetterDOMDocument\DOMDoc')) {
        foreach ($context->ns as $prefix => $uri) {
          $this->registerNamespace($prefix, $uri);
        }
        $context = $this->importNode($context->documentElement, TRUE);
        return;
      }
      else if (is_a($context, 'DOMDocument')) {
        $context = $this->importNode($context->documentElement, TRUE);
        return;
      }
      else {
        $context = $this->importNode($context, TRUE);
        return;
      }
    }

    return FALSE;
  }

  protected function loadFromString($xml) {
    if ($this->error_checking == 'none') {
      @$this->loadXML($xml, LIBXML_COMPACT);
    }
    else if (!$this->loadXML($xml, LIBXML_COMPACT)) {
      trigger_error('BetterDOMDocument\DOMDoc: Could not load: ' . htmlspecialchars($xml), E_USER_WARNING);
    }
  }
}
