<?php

namespace Mhor\MediaInfo\Parser;

abstract class AbstractXmlOutputParser implements OutputParserInterface
{
    /**
     * @param string $xmlString
     *
     * @return array
     */
    protected function transformXmlToArray($xmlString)
    {
        $xml = simplexml_load_string($xmlString);
        $json = json_encode($xml);

        return json_decode($json, true);
    }
}
