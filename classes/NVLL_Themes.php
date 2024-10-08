<?php

/**
 * Class for wrapping the themes
 * 
 * This file is part of NVLL. NVLL is free software under the terms of the
 * GNU General Public License. You should have received a copy of the license
 * along with NVLL. If not, see <http://www.gnu.org/licenses>.
 */

/**
 * Wrapping the themes
 */
class NVLL_Themes
{
    /**
     * Themes
     * @var array
     * @access private
     */
    private $_themes;

    /**
     * Default theme name
     * @var string
     * @access private
     */
    private $_defaultThemeName;

    /**
     * Selected theme name
     * @var string
     * @access private
     */
    private $_selectedThemeName;

    /**
     * Initialize the themes wrapper
     * @param string $path Themes path (relative)
     * @param string $defaultThemeName Default theme name
     */
    public function __construct($path, $defaultThemeName = '')
    {
        $this->_themes = array();
        $this->_defaultThemeName = 'default';
        $this->_selectedThemeName = '';

        if (isset($path) && is_string($path) && !empty($path)) { //if path is set...
            if (is_dir($path)) { //if is directory...
                if (substr($path, -1) != '/') { //if NOT ends with '/'...
                    $path .= '/';
                }

                //TODO: Move some code to a NVLL_Directory class?
                if ($handle = opendir($path)) { //if can open the directory...
                    while (false !== ($name = readdir($handle))) { //for each item...
                        if ($name != '.' && $name != '..' && is_dir($path . $name)) { //if subdirectory...
                            if (is_file($path . $name . '/style.css')) { //if style.css exists...
                                $this->_themes[strtolower($name)] = $path . $name;
                            }
                        }
                    }
                    closedir($handle);
                }

                //if the theme exists...
                if ($this->exists($defaultThemeName)) $this->_defaultThemeName = strtolower($defaultThemeName);
            }
        }
    }

    /**
     * Get the count from the themes
     * @return int Count
     */
    public function count()
    {
        return count($this->_themes);
    }

    /**
     * Exists the theme?
     * @param string $themeName Theme name
     * @return bool Exists?
     */
    public function exists($themeName)
    {
        //if theme name is set...
        if (isset($themeName) && is_string($themeName) && !empty($themeName)) {
            $themeName = strtolower($themeName);
            return array_key_exists($themeName, $this->_themes);
        }
        return false;
    }

    /**
     * Get the theme names
     * @return array Theme names
     */
    public function getThemeNames()
    {
        return array_keys($this->_themes);
    }

    /**
     * Get the default theme name
     * @return string Default theme name
     */
    public function getDefaultThemeName()
    {
        return $this->_defaultThemeName;
    }

    /**
     * Set the default theme name
     * @param string $themeName Default theme name
     * @return bool Successful?
     */
    public function setDefaultThemeName($themeName)
    {
        if ($this->exists($themeName)) { //if the theme exists...
            $this->_defaultThemeName = strtolower($themeName);
            return true;
        }
        return false;
    }

    /**
     * Get the selected theme name
     * @return string Selected theme name
     */
    public function getSelectedThemeName()
    {
        //if a theme is selected...
        if (!empty($this->_selectedThemeName)) return $this->_selectedThemeName;
        return $this->_defaultThemeName;
    }

    /**
     * Set the selected theme name
     * @param string $themeName Selected theme name
     * @return bool Successful?
     */
    public function setSelectedThemeName($themeName)
    {
        if ($this->exists($themeName)) { //if the theme exists...
            $this->_selectedThemeName = strtolower($themeName);
            return true;
        }
        return false;
    }
}
