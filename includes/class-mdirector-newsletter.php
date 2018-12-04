<?php

namespace MDirectorNewsletter\includes;

use Mdirector_Newsletter_Public;
use MDirectorNewsletter\admin\MDirector_Newsletter_Admin;

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://mdirector.com
 * @since      1.0.0
 *
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/includes
 * @author     MDirector
 */
class Mdirector_Newsletter
{
    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Mdirector_Newsletter_Loader $loader Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $mdirector_newsletter The string used to uniquely identify this plugin.
     */
    protected $mdirectorNewsletter;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $version The current version of the plugin.
     */
    protected $version;

    /**
     * Mdirector_Newsletter constructor.
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function __construct()
    {
        $this->mdirectorNewsletter = MDIRECTOR_NEWSLETTER;
        $this->version = MDIRECTOR_NEWSLETTER_VERSION;

        $this->loadDependencies();
        $this->setLocale();
        $this->defineAdminHooks();
        $this->definePublicHooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Mdirector_Newsletter_Loader. Orchestrates the hooks of the plugin.
     * - Mdirector_Newsletter_i18n. Defines internationalization functionality.
     * - Mdirector_Newsletter_Admin. Defines all hooks for the admin area.
     * - Mdirector_Newsletter_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function loadDependencies()
    {
        $pluginPath = plugin_dir_path(dirname(__FILE__));
        $dependenciesPath = $pluginPath . 'includes/';
        $adminPath = $pluginPath . 'admin/';
        $publicPath = $pluginPath . 'public/';

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once $dependenciesPath
            . 'class-mdirector-newsletter-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once $dependenciesPath . 'class-mdirector-newsletter-i18n.php';

        /**
         * The class responsible for calling Mdirector API
         */
        require_once $dependenciesPath . 'class-mdirector-newsletter-api.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once $adminPath . 'class-mdirector-newsletter-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once $publicPath . 'class-mdirector-newsletter-public.php';

        $this->loader = new Mdirector_Newsletter_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Mdirector_Newsletter_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function setLocale()
    {
        $pluginI18n = new Mdirector_Newsletter_i18n();
        $pluginI18n->setDomain($this->getMdirectorNewsletter());

        $this->loader->add_action('plugins_loaded', $pluginI18n,
            'loadPluginTextDomain');
    }

    /**
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    private function defineAdminHooks()
    {
        $plugin_admin =
            new Mdirector_Newsletter_Admin($this->getMdirectorNewsletter(),
                $this->getVersion());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin,
            'enqueueStyles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin,
            'enqueueScripts');
    }

    /**
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    private function definePublicHooks()
    {
        $plugin_public =
            new Mdirector_Newsletter_Public($this->getMdirectorNewsletter(),
                $this->getVersion());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public,
            'enqueueStyles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public,
            'enqueueScripts');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function getMdirectorNewsletter()
    {
        return $this->mdirectorNewsletter;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Mdirector_Newsletter_Loader    Orchestrates the hooks of the plugin.
     */
    public function getLoader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function getVersion()
    {
        return $this->version;
    }
}
