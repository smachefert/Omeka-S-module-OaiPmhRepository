<?php
/**
 * @author John Flatness, Yu-Hsun Lin
 * @copyright Copyright 2009 John Flatness, Yu-Hsun Lin
 * @copyright BibLibre, 2016
 * @copyright Daniel Berthereau, 2014-2018
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */
namespace OaiPmhRepository\OaiPmh\Metadata;

use DOMElement;
use Omeka\Api\Representation\ItemRepresentation;

/**
 * Class implementing metadata output for the oai_dcterms metadata format.
 * oai_dcterms is output of the 55 Dublin Core terms.
 *
 * This format is not standardized, but used by some repositories.
 * Note: the namespace and the schema don’t exist. It is designed as an extended
 * version of oai_dc.
 *
 * @link http://www.bl.uk/schemas/
 * @link http://dublincore.org/documents/dc-xml-guidelines/
 * @link http://dublincore.org/schemas/xmls/qdc/dcterms.xsd
 */
class OaiDctermsNavigae extends AbstractMetadata
{
    /** OAI-PMH metadata prefix */
    const METADATA_PREFIX = 'oai_dcterms_navigae';

    /** XML namespace for output format */
    const METADATA_NAMESPACE = 'http://www.openarchives.org/OAI/2.0/oai_dcterms/';

    /** XML schema for output format */
    const METADATA_SCHEMA = 'http://www.openarchives.org/OAI/2.0/oai_dcterms.xsd';

    /** XML namespace for Dublin Core */
    const DCTERMS_NAMESPACE_URI = 'http://purl.org/dc/terms/';

    /**
     * Appends Dublin Core terms metadata.
     *
     * {@inheritDoc}
     */
    public function appendMetadata(DOMElement $metadataElement, ItemRepresentation $item)
    {
        $document = $metadataElement->ownerDocument;

        $oai = $document->createElementNS(self::METADATA_NAMESPACE, 'oai_dcterms:dcterms');
        $metadataElement->appendChild($oai);

        /* Must manually specify XML schema uri per spec, but DOM won't include
         * a redundant xmlns:xsi attribute, so we just set the attribute
         */
        $oai->setAttribute('xmlns:dcterms', self::DCTERMS_NAMESPACE_URI);
        $oai->setAttribute('xmlns:xsi', parent::XML_SCHEMA_NAMESPACE_URI);
        $oai->setAttribute('xsi:schemaLocation', self::METADATA_NAMESPACE . ' ' .
            self::METADATA_SCHEMA);

        // Each of the 55 Dublin Core terms, in the Omeka order.
        $localNames = [

            // Dublin Core Elements.
            'title',
            'creator',
            'subject',
            'description',
            'publisher',
            'contributor',
            'date',
            'type',
            'format',
            'identifier',
            'source',
            'language',
            'relation',
            'coverage',
            'rights',
            // Dublin Core terms.
            'audience',
            'alternative',
            'tableOfContents',
            'abstract',
            'created',
            'valid',
            'available',
            'issued',
            'modified',
            'extent',
            'medium',
            'isVersionOf',
            'hasVersion',
            'isReplacedBy',
            'replaces',
            'isRequiredBy',
            'requires',
            'isPartOf',
            'hasPart',
            'isReferencedBy',
            'references',
            'isFormatOf',
            'hasFormat',
            'conformsTo',
            'spatial',
            'temporal',
            'mediator',
            'dateAccepted',
            'dateCopyrighted',
            'dateSubmitted',
            'educationLevel',
            'accessRights',
            'bibliographicCitation',
            'license',
            'rightsHolder',
            'provenance',
            'instructionalMethod',
            'accrualMethod',
            'accrualPeriodicity',
            'accrualPolicy',
        ];

        $formatUri = $this->params['format_uri'];
        $formatUriAttributes = in_array($formatUri, ['uri', 'uri_attr_label'])
            ? ['xsi:type' => 'dcterms:URI']
            : [];


        // Dealing with 123$a field
        $data123 = $item->value("1886:donneesCodeesRessourcesCartographiques", ['all' => true]) ?: [];
        $array123 = null;
        if (sizeof($data123) == 1) {
            $array123 = array();
            $data123 = simplexml_load_string($data123[0]->__toString());
            foreach ($data123->subfield as $subfield) {
                $code = strval($subfield["code"]);
                $array123[$code] = strval($subfield);
            }
        } else {
            // On n'a pas défini le 123 :(
            $data123 = null;
        }

        /* Must create elements using createElement to make DOM allow a
         * top-level xmlns declaration instead of wasteful and non-
         * compliant per-node declarations.
         */
        $values = $this->filterValuesPre($item);
        foreach ($localNames as $localName) {
            $vals = [];
            $term = 'dcterms:' . $localName;
            $termValues = $values[$term]['values'] ?? [];
            foreach ($termValues as $value) {
                [$text, $attributes] = $this->formatValue($value);
                $vals[] = $text;

                switch ($localName)
                {
                    case 'date':
                            $date = $text;
                            $date = preg_replace("(\?|\]|\[)", "", $date);
                            $date = preg_replace("/ca/", "", $date);
                            $date = trim($date);
                            if (!preg_match("/^\d{4}$/", $date)) {
                                $date = "";
                            }
                            if ($date != '') {
                                $this->appendNewElement($oai, 'dcterms:created', $date);
                            }
                        $text = null;
                        break;
    
                    case 'extent':
                        if (isset($array123["b"])) {
                            $this->appendNewElement($oai,'dcterms:extent',
                                "1:" . $array123["b"]);
                        }
                        break;

                    case 'rights':
                        
                        break;
                    default:
                        break;
                }

                if ($text != "") {
                    $this->appendNewElement($oai, $term, $text, $attributes);
                }
            }
        }

        // Ajout automatique du dcterms:spatial
        $spatial_element = null;
        if (
            (
                (isset($array123["e"])) and
                (isset($array123["g"]))
            )
            and
            (
                ($array123["e"] != $array123["d"]) and
                ($array123["f"] != $array123["g"])
            )
        ) {
            
            $coords_formated =
                sprintf("
                                northlimit=%s;
                                southlimit=%s;
                                westlimit=%s;
                                eastlimit=%s
                               ",
                    $this->DMStoDD($array123["f"]),
                    $this->DMStoDD($array123["g"]),
                    $this->DMStoDD($array123["d"]),
                    $this->DMStoDD($array123["e"])
                );
            $spatial_element = $document->createElement("dcterms:spatial", $coords_formated);
            $spatial_element->setAttribute("xsi:type", "dcterms:Box");
        }
        elseif (
            (isset($array123["d"])) and
            (isset($array123["f"]))
        ) {
            $coords_formated =
                sprintf("
                                east=%s; 
                                north=%s;
                                name=Point central de la carte
                                ",
                    $this->DMStoDD($array123["d"]),
                    $this->DMStoDD($array123["f"])
                );
            $spatial_element = $document->createElement("dcterms:spatial", $coords_formated);
            $spatial_element->setAttribute("xsi:type", "dcterms:Point");
        }
        if (!is_null($spatial_element)) {
            $oai->appendChild($spatial_element);
        }

        // Ajout de la licence
        $this->appendNewElement($oai,"dcterms:license", "https://creativecommons.org/publicdomain/mark/1.0/");


        $appendIdentifier = $this->singleIdentifier($item);
        if ($appendIdentifier) {
            $this->appendNewElement($oai, 'dcterms:identifier', $appendIdentifier, $formatUriAttributes);
        }

        // Also append an identifier for each file
        if ($this->params['expose_media']) {
            foreach ($item->media() as $media) {
                $this->appendNewElement($oai, 'dcterms:identifier', $media->originalUrl(), $formatUriAttributes);
            }
        }
    }


    private function DMStoDD($input)
    {
        $orientation = strtoupper(substr($input, 0, 1));
        // Converting DMS ( Degrees / minutes / seconds ) to decimal format
        $deg = substr($input, 1, 3);
        $min = substr($input, 4, 2);
        $sec = substr($input, 6, 2);
        $dd = number_format($deg+((($min*60)+($sec))/3600), 2);
        if ( ($orientation == "W") or ($orientation == "S") ) {
            $dd = "-".$dd;
        } elseif ( ($orientation == "N") or ($orientation == "E") ) {
            $dd = $dd;
        }
        return $dd;
    }
}
