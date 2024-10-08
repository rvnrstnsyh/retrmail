<?php

/**
 * Class for wrapping a imap_fetchstructure() object
 * 
 * This file is part of NVLL. NVLL is free software under the terms of the
 * GNU General Public License. You should have received a copy of the license
 * along with NVLL. If not, see <http://www.gnu.org/licenses>.
 */

require_once dirname(__FILE__) . '/NVLL_Encoding.php';
require_once dirname(__FILE__) . '/NVLL_Internetmediatype.php';

/**
 * Wrapping a imap_fetchstructure() object
 */
class NVLL_MailStructure
{
    /**
     * imap_fetchstructure() object
     * @var object
     * @access private
     */
    private $_structure;

    /**
     * Internet media type
     * @var NVLL_InternetMediaType
     * @access private
     */
    private $_internetMediaType;

    /**
     * Encoding
     * @var NVLL_Encoding
     * @access private
     */
    private $_encoding;

    /**
     * contains mimeIDs and content_transfer_encoding for each part
     * @var array
     * @access private
     */
    private $_parts_info;

    /**
     * Initialize the wrapper
     * @param object $structure imap_fetchstructure() object
     * @param array $parts_info contains mimeIDs and content_transfer_encoding for each part
     * @todo Throw exception, if no vaild structure? 
     */
    public function __construct($structure, $parts_info = array())
    {
        $this->_parts_info = $parts_info;
        $this->_structure = $structure;
        $this->_internetMediaType = NVLL_MailStructure::getInternetMediaTypeFromStructure($structure);
        $this->_encoding = NVLL_MailStructure::getEncodingFromStructure($structure, $parts_info);
    }

    /**
     * @return array
     */
    public function getPartsInfo()
    {
        return $this->_parts_info;
    }

    /**
     * Get the complete imap_fetchstructure() object
     * @return object
     */
    public function getStructure()
    {
        return $this->_structure;
    }

    /**
     * Get the transfer encoding from the structure
     * @return NVLL_Encoding Transfer encoding
     */
    public function getEncoding()
    {
        return $this->_encoding;
    }

    /**
     * Get the content description from the structure
     * @return string Content description
     */
    public function getDescription()
    {
        if ($this->_structure->ifdescription) return $this->_structure->description;
        return '';
    }

    /**
     * Get the identification from the structure
     * @return string Identification
     */
    public function getId()
    {
        $result = "";
        if ($this->_structure->ifid) $result = $this->_structure->id;

        return $result;
    }

    /**
     * Has the structure a identification?
     * @return bool Has identification?
     */
    public function hasId()
    {
        $id = $this->getId();
        return !empty($id);
    }

    //removed, because not used anywhere
    ///**
    // * Get the number of lines from the structure
    // * @return integer Number of lines
    // */
    //public function getLines() {
    //    if (isset($this->_structure->lines)) {
    //        return $this->_structure->lines;
    //    }
    //    return 0;
    //}

    /**
     * Get the number of bytes from the structure
     * @return integer Number of bytes
     */
    public function getBytes()
    {
        if (isset($this->_structure->bytes)) return $this->_structure->bytes;
        return 0;
    }

    /**
     * Get the total number of bytes from the structure
     * @return integer Total number of bytes
     */
    public function getTotalBytes()
    {
        $totalbytes = $this->getBytes();
        if ($totalbytes == 0) { //if a mail has ANY attachements, $structure->bytes is ALWAYS empty...
            if (isset($this->_structure->parts)) {
                for ($i = 0; $i < count($this->_structure->parts); $i++) { //for all parts...
                    if (isset($this->_structure->parts[$i]->bytes)) {
                        $totalbytes += $this->_structure->parts[$i]->bytes;
                    }
                }
            }
        }
        return $totalbytes;
    }

    /**
     * Get the size from the structure in kilobyte
     * @return integer Size in kilobyte
     */
    public function getSize()
    {
        $totalBytes = $this->getTotalBytes();
        //if more then 1024 bytes...
        if ($totalBytes > 1024) return ceil($totalBytes / 1024);
        return 1;
    }

    /**
     * Get the disposition from the structure
     * @return string Disposition
     */
    public function getDisposition()
    {
        if ($this->_structure->ifdisposition) return $this->_structure->disposition;
        return '';
    }

    // removed because never called
    ///**
    // * Get the Content-disposition MIME header parameters from the structure
    // * @return array Content-disposition MIME header parameters
    // */
    //public function getDparameters() {
    //	if ($this->_structure->ifdparameters) {
    //		return $this->_structure->dparameters;
    //	}
    //        return array();
    //    }

    /**
     * Get a value from the Content-disposition MIME header parameters
     * @param string $attribute Attribute
     * @param string $defaultvalue Default value
     * @return string Value
     */
    public function getValueFromDparameters($attribute, $defaultvalue = '')
    {
        $attribute = strtolower($attribute);
        if ($this->_structure->ifdparameters) {
            foreach ($this->_structure->dparameters as $parameter) { //for all parameters...
                if (strtolower($parameter->attribute) == $attribute) return $parameter->value;
            }
        }

        return $defaultvalue;
    }

    // removed because never called
    ///**
    // * Get the parameters from the structure
    // * @return array Parameters
    // */
    //public function getParameters() {
    //    if ($this->_structure->ifparameters) {
    //      return $this->_structure->parameters;
    //    }
    //    return array();
    //}

    /**
     * Get a value from the parameters
     * @param string $attribute Attribute
     * @param string $defaultvalue Default value
     * @return string Value
     */
    public function getValueFromParameters($attribute, $defaultvalue = '')
    {
        $attribute = strtolower($attribute);
        if ($this->_structure->ifparameters) {
            foreach ($this->_structure->parameters as $parameter) { //for all parameters...
                if (strtolower($parameter->attribute) == $attribute) return $parameter->value;
            }
        }
        return $defaultvalue;
    }

    /**
     * Has the structure parts?
     * @return bool Has parts?
     */
    public function hasParts()
    {
        if (isset($this->_structure->parts)) {
            if (count($this->_structure->parts) > 0) return true;
        }
        return false;
    }

    /**
     * Get the parts from the structure
     * @return array Parts
     */
    public function getParts()
    {
        if ($this->hasParts()) return $this->_structure->parts;
        return array();
    }

    /**
     * Get the (file) name from the structure
     * @param string $defaultname Default (file) name
     * @return string (File) name
     * @todo I got a mail which use "name*" as parameter: string(52) "UTF-8''Bestellliste%20f%C3%BCr%20das%20Fotoalbum.doc"
     */
    public function getName($defaultname = '')
    {
        $name = $this->getValueFromParameters('name');
        if (!empty($name)) { //if "name" parameter exists...
            return $name;
        } else { //if "name" parameter NOT exists...
            $filename = $this->getValueFromDparameters('filename');
            //if "filename" parameter exists...
            if (!empty($filename)) return $filename;
        }
        return $defaultname;
    }

    /**
     * Get the charset from the structure
     * @param string $defaultcharset Default charset
     * @return string Charset
     */
    public function getCharset($defaultcharset = '')
    {
        return $this->getValueFromParameters('Charset', $defaultcharset);
    }

    /**
     * Get the internet media type (MIME type) from the structure
     * @return NVLL_InternetMediaType Internet media type
     */
    public function getInternetMediaType()
    {
        return $this->_internetMediaType;
    }

    /**
     * Is attachment?
     * @return bool Is attachment?
     */
    public function isAttachment()
    {
        //if attachment...
        if (strtolower($this->getDisposition()) == 'attachment') return true;
        return false;
    }

    /**
     * Is inline?
     * @return bool Is inline?
     */
    public function isInline()
    {
        //if inline...
        if (strtolower($this->getDisposition()) == 'inline') return true;
        return false;
    }

    /**
     * ...
     * @param object $structure imap_fetchstructure() object
     * @return NVLL_InternetMediaType ...
     */
    public static function getInternetMediaTypeFromStructure($structure)
    {
        if (isset($structure->type) && isset($structure->subtype)) return new NVLL_InternetMediaType($structure->type, $structure->subtype);
        return new NVLL_InternetMediaType();
    }

    /**
     * ...
     * @param object $structure imap_fetchstructure() object
     * @return NVLL_Encoding ...
     */
    public static function getEncodingFromStructure($structure, $parts_info = array())
    {
        if (isset($structure->encoding)) return new NVLL_Encoding($structure->encoding);
        return new NVLL_Encoding();
    }
}
