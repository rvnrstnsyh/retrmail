<?php

/**
 * Class for wrapping the internet media type (MIME type) from a imap_fetchstructure() object
 * 
 * This file is part of NVLL. NVLL is free software under the terms of the
 * GNU General Public License. You should have received a copy of the license
 * along with NVLL. If not, see <http://www.gnu.org/licenses>.
 */

/**
 * Wrapping the internet media type (MIME type) from a imap_fetchstructure() object
 */
class NVLL_InternetMediaType
{
    /**
     * Type
     * @var integer
     * @access private
     */
    private $_type;

    /**
     * Subtype
     * @var string
     * @access private
     */
    private $_subtype;

    /**
     * Initialize the wrapper
     * @param integer $type Type
     * @param string $subtype Subtype
     */
    public function __construct($type = null, $subtype = null)
    {
        $this->_type = -1;
        $this->_subtype = '';
        if (is_int($type) && is_string($subtype)) { //if valid types...
            $this->_type = $type;
            $this->_subtype = strtolower($subtype);
        }
        //TODO: Maybe allow $type also as string if $subtype is string?
        //TODO: Maybe allow $type also as string if $subtype is empty?
    }

    /**
     * Get the internet media subtype
     * @return string Internet media subtype
     */
    public function getSubtype()
    {
        return $this->_subtype;
    }

    /**
     * Is text?
     * @return bool Is text?
     */
    public function isText()
    {
        //if text...
        if ($this->_type == 0) return true;
        return false;
    }

    /**
     * Is plain text?
     * @return bool Is plain text?
     */
    public function isPlainText()
    {
        //if text...
        if ($this->isText()) {
            //if plain text...
            if ($this->_subtype == 'plain') return true;
        }
        return false;
    }

    /**
     * Is HTML text?
     * @return bool Is HTML text?
     */
    public function isHtmlText()
    {
        //if text...
        if ($this->isText()) {
            //if HTML text...
            if ($this->_subtype == 'html') return true;
        }
        return false;
    }

    /**
     * Is plain or HTML text?
     * @return bool Is plain or HTML text?
     */
    public function isPlainOrHtmlText()
    {
        //if text...
        if ($this->isText()) {
            //if plain or HTML text...
            if ($this->_subtype == 'plain' || $this->_subtype == 'html') return true;
        }
        return false;
    }

    /**
     * Is multipart?
     * @return bool Is multipart?
     */
    public function isMultipart()
    {
        //if multipart...
        if ($this->_type == 1) return true;
        return false;
    }

    /**
     * Is alternative multipart?
     * @return bool Is alternative multipart?
     */
    public function isAlternativeMultipart()
    {
        //if multipart...
        if ($this->isMultipart()) {
            //if alternative multipart...
            if ($this->isAlternative()) return true;
        }
        return false;
    }

    /**
     * Is related multipart?
     * @return bool Is related multipart?
     */
    public function isRelatedMultipart()
    {
        //if multipart...
        if ($this->isMultipart()) {
            //if related multipart...
            if ($this->isRelated()) return true;
        }
        return false;
    }

    /**
     * Is message?
     * @return bool Is message?
     */
    public function isMessage()
    {
        //if message...
        if ($this->_type == 2) return true;
        return false;
    }

    /**
     * Is RFC822 message?
     * @return bool Is RFC822 message?
     */
    public function isRfc822Message()
    {
        //if message...
        if ($this->isMessage()) {
            //if RFC822 message...
            if ($this->_subtype == 'rfc822') return true;
        }
        return false;
    }

    /**
     * Is application?
     * @return bool Is application?
     */
    public function isApplication()
    {
        //if application...
        if ($this->_type == 3) return true;
        return false;
    }

    /**
     * Is audio?
     * @return bool Is audio?
     */
    public function isAudio()
    {
        //if audio...
        if ($this->_type == 4) return true;
        return false;
    }

    /**
     * Is image?
     * @return bool Is image?
     */
    public function isImage()
    {
        //if image...
        if ($this->_type == 5) return true;
        return false;
    }

    /**
     * Is video?
     * @return bool Is video?
     */
    public function isVideo()
    {
        //if video...
        if ($this->_type == 6) return true;
        return false;
    }

    /**
     * Is other?
     * @return bool Is other?
     */
    public function isOther()
    {
        //if other...
        if ($this->_type == 7) return true;
        return false;
    }

    /**
     * Is alternative?
     * @return bool Is alternative?
     */
    public function isAlternative()
    {
        //if alternative...
        if ($this->_subtype == 'alternative') return true;
        return false;
    }

    /**
     * Is related?
     * @return bool Is related?
     */
    public function isRelated()
    {
        //if related...
        if ($this->_subtype == 'related') return true;
        return false;
    }

    /**
     * ...
     * @return string Internet media type text
     */
    public function __toString()
    {
        switch ($this->_type) {
            case 0:
                return 'text/' . $this->_subtype;
            case 1:
                return 'multipart/' . $this->_subtype;
            case 2:
                return 'message/' . $this->_subtype;
            case 3:
                return 'application/' . $this->_subtype;
            case 4:
                return 'audio/' . $this->_subtype;
            case 5:
                return 'image/' . $this->_subtype;
            case 6:
                return 'video/' . $this->_subtype;
            case 7:
                return 'other/' . $this->_subtype;
        }
        return '';
    }
}
