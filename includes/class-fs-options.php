<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       1.2.3
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * Class FS_Options
     *
     * A wrapper class for handling network level and single site level options.
     */
    class FS_Options {
        /**
         * @var string
         */
        private $_id;

        /**
         * @var array[string]FS_Options {
         * @key   string
         * @value FS_Options
         * }
         */
        private static $_instances;

        /**
         * @var FS_Option_Manager Site level options.
         */
        private $_options;

        /**
         * @var FS_Option_Manager Network level options.
         */
        private $_network_options;

        /**
         * @var int The ID of the blog that is associated with the current site level options.
         */
        private $_blog_id = 0;

        /**
         * @var bool
         */
        private $_is_multisite;

        /**
         * @var string[] Lazy collection of params on the site level.
         */
        private static $_SITE_LEVEL_PARAMS;

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.4
         *
         * @param string $id
         * @param bool   $load
         *
         * @return FS_Options
         */
        static function instance( $id, $load = false ) {
            if ( ! isset( self::$_instances[ $id ] ) ) {
                self::$_instances[ $id ] = new FS_Options( $id, $load );
            }

            return self::$_instances[ $id ];
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.4
         *
         * @param string $id
         * @param bool   $load
         */
        private function __construct( $id, $load = false ) {
            $this->_id           = $id;
            $this->_is_multisite = is_multisite();

            if ( $this->_is_multisite ) {
                $this->_blog_id         = get_current_blog_id();
                $this->_network_options = FS_Option_Manager::get_manager( $id, $load, true );
            }

            $this->_options = FS_Option_Manager::get_manager( $id, $load, $this->_blog_id );
        }

        /**
         * Switch the context of the site level options manager.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.4
         *
         * @param $blog_id
         */
        function set_site_blog_context( $blog_id ) {
            $this->_blog_id = $blog_id;

            $this->_options = FS_Option_Manager::get_manager( $this->_id, false, $this->_blog_id );
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @param string        $option
         * @param mixed         $default
         * @param null|bool|int $network_level_or_blog_id When an integer, use the given blog storage. When `true` use the multisite storage (if there's a network). When `false`, use the current context blog storage. When `null`, the decision which storage to use (MS vs. Current S) will be handled internally and determined based on the $option (based on self::$_SITE_LEVEL_PARAMS).
         *
         * @return mixed
         */
        function get_option( $option, $default = null, $network_level_or_blog_id = null ) {
            if ( $this->should_use_network_storage( $option, $network_level_or_blog_id ) ) {
                return $this->_network_options->get_option( $option, $default );
            }

            $site_options = $this->get_site_options( $network_level_or_blog_id );

            return $site_options->get_option( $option, $default );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.4
         *
         * @param string        $option
         * @param mixed         $value
         * @param bool          $flush
         * @param null|bool|int $network_level_or_blog_id When an integer, use the given blog storage. When `true` use the multisite storage (if there's a network). When `false`, use the current context blog storage. When `null`, the decision which storage to use (MS vs. Current S) will be handled internally and determined based on the $option (based on self::$_SITE_LEVEL_PARAMS).
         */
        function set_option( $option, $value, $flush = false, $network_level_or_blog_id = null ) {
            if ( $this->should_use_network_storage( $option, $network_level_or_blog_id ) ) {
                $this->_network_options->set_option( $option, $value, $flush );
            } else {
                $site_options = $this->get_site_options( $network_level_or_blog_id );
                $site_options->set_option( $option, $value, $flush );
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.4
         *
         * @param string        $option
         * @param bool          $flush
         * @param null|bool|int $network_level_or_blog_id When an integer, use the given blog storage. When `true` use the multisite storage (if there's a network). When `false`, use the current context blog storage. When `null`, the decision which storage to use (MS vs. Current S) will be handled internally and determined based on the $option (based on self::$_SITE_LEVEL_PARAMS).
         */
        function unset_option( $option, $flush = false, $network_level_or_blog_id = null ) {
            if ( $this->should_use_network_storage( $option, $network_level_or_blog_id ) ) {
                $this->_network_options->unset_option( $option, $flush );
            } else {
                $site_options = $this->get_site_options( $network_level_or_blog_id );
                $site_options->unset_option( $option, $flush );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.4
         *
         * @param bool $flush
         * @param bool $network_level
         */
        function load( $flush = false, $network_level = true ) {
            if ( $this->_is_multisite && $network_level ) {
                $this->_network_options->load( $flush );
            } else {
                $this->_options->load( $flush );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.4
         *
         * @param null|bool|int $network_level_or_blog_id When an integer, use the given blog storage. When `true` use the multisite storage (if there's a network). When `false`, use the current context blog storage. When `null`, store both network storage and the current context blog storage.
         */
        function store( $network_level_or_blog_id = null ) {
            if ( ! $this->_is_multisite ||
                 false === $network_level_or_blog_id ||
                 0 == $network_level_or_blog_id ||
                 is_null( $network_level_or_blog_id )
            ) {
                $site_options = $this->get_site_options( $network_level_or_blog_id );
                $site_options->store();
            }

            if ( $this->_is_multisite &&
                 ( is_null( $network_level_or_blog_id ) || true === $network_level_or_blog_id )
            ) {
                $this->_network_options->store();
            }
        }

        #--------------------------------------------------------------------------------
        #region Helper Methods
        #--------------------------------------------------------------------------------

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.4
         *
         * @param string $option
         *
         * @return bool
         */
        private function is_site_option( $option ) {
            if ( WP_FS__ACCOUNTS_OPTION_NAME != $this->_id ) {
                return false;
            }

            if ( ! isset( self::$_SITE_LEVEL_PARAMS ) ) {
                self::$_SITE_LEVEL_PARAMS = array(
                    'sites'          => true,
                    'theme_sites'    => true,
                    'unique_id'      => true,
                    'active_plugins' => true,
                );
            }

            return isset( self::$_SITE_LEVEL_PARAMS[ $option ] );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.4
         *
         * @param int $blog_id
         *
         * @return FS_Option_Manager
         */
        private function get_site_options( $blog_id = 0 ) {
            if ( 0 == $blog_id || $blog_id == $this->_blog_id ) {
                return $this->_options;
            }

            return FS_Option_Manager::get_manager( $this->_id, true, $blog_id );
        }

        /**
         * Check if an option should be stored on the MS network storage.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.4
         *
         * @param string        $option
         * @param null|bool|int $network_level_or_blog_id When an integer, use the given blog storage. When `true` use the multisite storage (if there's a network). When `false`, use the current context blog storage. When `null`, the decision which storage to use (MS vs. Current S) will be handled internally and determined based on the $option (based on self::$_SITE_LEVEL_PARAMS).
         *
         * @return bool
         */
        private function should_use_network_storage( $option, $network_level_or_blog_id = null ) {
            if ( ! $this->_is_multisite ) {
                // Not a multisite environment.
                return false;
            }

            if ( is_numeric( $network_level_or_blog_id ) ) {
                // Explicitly asked to use a specified blog storage.
                return false;
            }

            if ( is_bool( $network_level_or_blog_id ) ) {
                // Explicitly specified whether should use the network or blog level storage.
                return $network_level_or_blog_id;
            }

            // Determine which storage to use based on the option.
            return ! $this->is_site_option( $option );
        }

        #endregion
    }