<?php

include_once('BetterDOMDocument.php');

$xml = '<?xml version="1.0"?><!-- This will fail -->
<atom:author test="123" xmlns:atom = "http://www.w3.org/2005/Atom" xmlns:nlm="http://schema.highwire.org/NLM/Journal" xmlns:hwp="http://schema.highwire.org/Journal" nlm:contrib-type="author"><atom:name>Shubhayan Sanatani</atom:name><nlm:name name-style="western" hwp:sortable="Sanatani Shubhayan"><nlm:surname>Sanatani</nlm:surname><nlm:given-names>Shubhayan</nlm:given-names></nlm:name></atom:author>';

$dom = new BetterDOMDocument($xml);
print var_dump(count($dom->query('//nlm:surname')));

