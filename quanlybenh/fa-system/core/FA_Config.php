<?php
NAMESPACE FA\CORE;

defined('BASE_PATH') OR exit('No direct script access allowed');

/**
 * Config Class
 * This class contains functions that enable config files to be managed
 *
 * Class FA_Config
 *
 * @package FA\CORE
 */
class FA_Config
{

    /**
     * List of all loaded config values
     *
     * @var	array
     */
    public $config = array();

    /**
     * List of all loaded config files
     *
     * @var	array
     */
    public $is_loaded =	array();

    /**
     * List of paths to search when trying to load a config file.
     *
     * @used-by	CI_Loader
     * @var		array
     */
    public $_config_paths =	array(APP_PATH);

    /**
     * Class constructor
     *
     * Sets the $config data from the primary config.php file as a class variable.
     *
     * FA_Config constructor.
     */
    public function __construct()
    {
        $this->config =& get_config();

        /**
         * Set the base_url automatically if none was provided
         */
        if (empty($this->config['base_url']))
        {
            $base_url = get_http_host() . '/';

            $this->set_item('base_url', $base_url);
        }

        log_message(MSG_INFO, 'Config Class Initialized');
    }

    /**
     * Load Config File
     *
     * @param	string	$file			Configuration file name
     * @param	bool	$use_sections		Whether configuration values should be loaded into their own section
     * @param	bool	$fail_gracefully	Whether to just return FALSE or display an error message
     * @return	bool	TRUE if the file was loaded correctly or FALSE on failure
     */
    public function load($file = '', $use_sections = FALSE, $fail_gracefully = FALSE)
    {
        $file = ($file === '') ? 'config' : str_replace('.php', '', $file);
        $loaded = FALSE;

        foreach ($this->_config_paths as $path)
        {
            foreach (array($file) as $location)
            {
                $file_path = $path.'config/'.$location.'.php';
                if (in_array($file_path, $this->is_loaded, TRUE))
                {
                    return TRUE;
                }

                if ( !file_exists($file_path))
                {
                    continue;
                }

                include $file_path;

                if ( !isset($config) OR !is_array($config))
                {
                    if ($fail_gracefully === TRUE)
                    {
                        return FALSE;
                    }

                    show_error('Your '.$file_path.' file does not appear to contain a valid configuration array.');
                    return FALSE;
                }

                if ($use_sections === TRUE)
                {
                    $this->config[$file] = isset($this->config[$file])
                        ? array_merge($this->config[$file], $config)
                        : $config;
                }
                else
                {
                    $this->config = array_merge($this->config, $config);
                }

                $this->is_loaded[] = $file_path;
                $config = NULL;
                $loaded = TRUE;
                log_message(MSG_DEBUG, 'Config file loaded: '.$file_path);
            }
        }

        if ($loaded === TRUE)
        {
            return TRUE;
        }
        elseif ($fail_gracefully === TRUE)
        {
            return FALSE;
        }

        show_error('The configuration file ' . $file . '.php does not exist.');
        return FALSE;
    }

    /**
     * Fetch a config file item
     *
     * @param	string	$item	Config item name
     * @param	string	$index	Index name
     * @return	string|null	The configuration item or NULL if the item doesn't exist
     */
    public function item($item, $index = '')
    {
        if ($index == '')
        {
            return isset($this->config[$item]) ? $this->config[$item] : NULL;
        }

        return isset($this->config[$index], $this->config[$index][$item]) ? $this->config[$index][$item] : NULL;
    }


    /**
     * Fetch a config file item with slash appended (if not empty)
     *
     * @param	string		$item	Config item name
     * @return	string|null	The configuration item or NULL if the item doesn't exist
     */
    public function slash_item($item)
    {
        if ( !isset($this->config[$item]))
        {
            return NULL;
        }
        if (trim($this->config[$item]) === '')
        {
            return '';
        }

        return rtrim($this->config[$item], '/').'/';
    }

    /**
     * Site URL
     *
     * Returns base_url . index_page [. uri_string]
     *
     * @uses	FA_Config::_uri_string()
     *
     * @param	string|string[]	$uri	URI string or an array of segments
     * @param	string	$protocol
     * @return	string
     */
    public function site_url($uri = '', $protocol = NULL)
    {
        $base_url = $this->slash_item('base_url');

        if (isset($protocol))
        {
            /**
             * For protocol-relative links
             */
            if ($protocol === '')
            {
                $base_url = substr($base_url, strpos($base_url, '//'));
            }
            else
            {
                $base_url = $protocol.substr($base_url, strpos($base_url, '://'));
            }
        }

        if (empty($uri))
        {
            return $base_url . $this->item('index_page');
        }

        $uri = $this->_uri_string($uri);

        if ($this->item('url_friendly') === TRUE)
        {
            $suffix = isset($this->config['url_suffix']) ? $this->config['url_suffix'] : '';

            if ($suffix !== '')
            {
                if (($offset = strpos($uri, '?')) !== FALSE)
                {
                    $uri = substr($uri, 0, $offset).$suffix.substr($uri, $offset);
                }
                else
                {
                    $uri .= $suffix;
                }
            }

            return $base_url.$this->slash_item('index_page').$uri;
        }
        elseif (strpos($uri, '?') === FALSE)
        {
            $uri = '?'.$uri;
        }

        return $base_url.$this->item('index_page').$uri;
    }

    /**
     * Base URL
     *
     * Returns base_url [. uri_string]
     *
     * @uses	FA_Config::_uri_string()
     *
     * @param	string|string[]	$uri	URI string or an array of segments
     * @param	string	$protocol
     * @return	string
     */
    public function base_url($uri = '', $protocol = NULL)
    {
        $base_url = $this->slash_item('base_url');

        if (isset($protocol))
        {
            // For protocol-relative links
            if ($protocol === '')
            {
                $base_url = substr($base_url, strpos($base_url, '//'));
            }
            else
            {
                $base_url = $protocol . substr($base_url, strpos($base_url, '://'));
            }
        }

        return $base_url . ltrim($this->_uri_string($uri), '/');
    }

    /**
     * Build URI string
     *
     * @used-by	FA_Config::site_url()
     * @used-by	FA_Config::base_url()
     *
     * @param	string|string[]	$uri	URI string or an array of segments
     * @return	string
     */
    protected function _uri_string($uri)
    {
        if ($this->item('url_friendly') === TRUE)
        {
            if (is_array($uri))
            {
                $uri = implode('/', $uri);
            }
            return trim($uri, '/');
        }
        elseif (is_array($uri))
        {
            return http_build_query($uri);
        }

        return $uri;
    }

    // --------------------------------------------------------------------

    /**
     * System URL
     *
     * @return	string
     */
    public function system_url()
    {
        $x = explode('/', preg_replace('|/*(.+?)/*$|', '\\1', BASE_PATH));
        return $this->slash_item('base_url').end($x).'/';
    }

    // --------------------------------------------------------------------

    /**
     * Set a config file item
     *
     * @param	string	$item	Config item key
     * @param	string	$value	Config item value
     * @return	void
     */
    public function set_item($item, $value)
    {
        $this->config[$item] = $value;
    }

}
