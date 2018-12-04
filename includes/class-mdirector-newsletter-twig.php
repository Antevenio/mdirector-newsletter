<?php

namespace MDirectorNewsletter\includes;

use Twig_Environment;
use Twig_Loader_Filesystem;
use Twig_SimpleFilter;

class Mdirector_Newsletter_Twig
{
    // Twig System
    const ADMIN_TEMPLATE_PATH = MDIRECTOR_NEWSLETTER_PLUGIN_DIR
    . 'admin/templates/';
    const ADMIN_TEMPLATE = 'admin-template.twig';
    const USER_TEMPLATE = 'template.twig';
    const TWIG_CACHE_PATH = MDIRECTOR_NEWSLETTER_PLUGIN_DIR . 'cache';
    const TWIG_AUTO_RELOAD = true;
    const TWIG_AUTO_ESCAPE = false;

    public function translate($string)
    {
        return __($string, Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN);
    }

    public function shortCode($shortCode)
    {
        return do_shortcode($shortCode);
    }

    private function getEnvironmentOptions()
    {
        return [
            'cache' => self::TWIG_CACHE_PATH,
            'auto_reload' => self::TWIG_AUTO_RELOAD,
            'autoescape' => self::TWIG_AUTO_ESCAPE
        ];
    }

    /**
     * @return \Twig_TemplateInterface
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function initAdminTemplate()
    {
        return $this->initAdminTwig()->loadTemplate(self::ADMIN_TEMPLATE);
    }

    /**
     * @return Twig_Environment
     */
    private function initAdminTwig()
    {
        $loader = new Twig_Loader_Filesystem(self::ADMIN_TEMPLATE_PATH);
        $twigEnvironment =
            new Twig_Environment($loader, $this->getEnvironmentOptions());

        $this->initTranslations($twigEnvironment);

        return $twigEnvironment;
    }

    /**
     * @param Twig_Environment $twigInstance
     */
    private function initTranslations(Twig_Environment $twigInstance)
    {
        $filter = new Twig_SimpleFilter('translate', [$this, 'translate']);
        $twigInstance->addFilter('translate', $filter);
    }

    /**
     * @param Twig_Environment $twigInstance
     */
    private function initShortCodes(Twig_Environment $twigInstance)
    {
        $filter = new Twig_SimpleFilter('do_shortcode', [$this, 'shortCode']);
        $twigInstance->addFilter('do_shortcode', $filter);
    }

    /**
     * @param $templateData
     *
     * @return Twig_Environment
     */
    public function initUserTemplate($templateData)
    {
        return $this->initUserTwig($templateData['templatePath']);
    }

    /**
     * @param $templatePath
     *
     * @return Twig_Environment
     */
    private function initUserTwig($templatePath)
    {
        $loader = new Twig_Loader_Filesystem($templatePath);
        $twigEnvironment =
            new Twig_Environment($loader, $this->getEnvironmentOptions());
        $this->initShortCodes($twigEnvironment);

        return $twigEnvironment;
    }
}
