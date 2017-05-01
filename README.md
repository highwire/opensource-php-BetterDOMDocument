# BetterDOMDocument

[![Build Status](https://scrutinizer-ci.com/g/highwire/opensource-php-BetterDOMDocument/badges/build.png?b=2.x)](https://scrutinizer-ci.com/g/highwire/opensource-php-BetterDOMDocument/build-status/2.x)
[![Code Coverage](https://scrutinizer-ci.com/g/highwire/opensource-php-BetterDOMDocument/badges/coverage.png?b=2.x)](https://scrutinizer-ci.com/g/highwire/opensource-php-BetterDOMDocument/?branch=2.x)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/highwire/opensource-php-BetterDOMDocument/badges/quality-score.png?b=2.x)](https://scrutinizer-ci.com/g/highwire/opensource-php-BetterDOMDocument/?branch=2.x)
[![Dependency Status](https://www.versioneye.com/user/projects/58ff6aad710da2402c670664/badge.svg?style=flat-square)](https://www.versioneye.com/user/projects/58ff6aad710da2402c670664)

BetterDOMDocument is a handy PHP utility class for working with XML. It's a wrapper for PHP's built in DOMDocument that provides a bunch of nice shortcuts that
makes working with XML in PHP a breeze. It has great built-in support for namespaces, and xpath queries.

```
composer require highwire/better-dom-document
```

```php
<?php

use BetterDOMDocument\DOMDoc;

// We can load a new BetterDOMDocument from either a string or a DOMNode object
$dom = new DOMDoc($xmlstring);

// It's easy to output the entire document as an array, which is sometimes easier to work with in PHP
$array = $dom->getArray();

// It's easy to query too!
$node_list = $dom->xpath('//xpath/to/node', $optional_context_node);

// If you know you're only going to find a single DOMNode, you can use a querySingle
$dom_node = $dom->xpathSingle('//xpath/to/node');

// Swapping out DOMNodes is really easy
$dom->replace($dom_node, $replacementNode);

// Removing a node is easy
$dom->remove($dom_node);

// Most places where you want to pass a DOMNode or element, you can just pass an xpath instead
$dom->remove('//xpath/to/element/to/remove');

// The same goes for replacments
$dom->replace('//xpath/to/replace', '<xml>You can pass a string, a DOMNode, or a document</xml>');

// It's easy to output the XML
$xml = $dom->out();

// Or just output a single DOMNode
$xml = $dom->out($dom_node);


// Working with namespaced documents is made really easy
$xml = '
  <entry xmlns="http://www.w3.org/2005/Atom" xmlns:nlm="http://schema.highwire.org/NLM/Journal">
    <author>
      <name>Li Xu</name>
      <nlm:name name-style="eastern">
        <nlm:surname>Li</nlm:surname>
        <nlm:given-names>Xu</nlm:given-names>
      </nlm:name>
    </author>
  </entry>';

// If your document (like the one above) has a default namespace, you should declare 
// it's prefix as the second value when constructing a new BetterDOMDocument
$dom = new DOMDoc($xml, 'atom'); // We register the 'atom' prefix against the default namespace

// Now we can do mixed namespace queries!
$surname = $dom->querySingle('//atom:author/nlm:name/nlm:surname')->nodeValue;

// If you need to register another namespace before doing a query, thats a snap.
// Note that by default all namespace declarations in the root element are automatically registered. 
$dom->registerNamespace('kml','http://www.opengis.net/kml/2.2');

// If you want to query with CSS selectors, no problem!
$dom->select('nlm:name[@name-style="eastern"]');

