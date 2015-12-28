<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$sep = DIRECTORY_SEPARATOR;
require('models/lengow.install.class.php');
require_once _PS_MODULE_DIR_ . 'lengow' . $sep . 'loader.php';

class Lengow extends Module
{

    private $installClass;

    public function __construct()
    {

        $this->name = 'lengow';
        $this->tab = 'lengow_tab';
        $this->version = '3.0.0';
        $this->author = 'Lengow';
        $this->module_key = '92f99f52f2bc04ed999f02e7038f031c';
        $this->ps_versions_compliancy = array('min' => '1.4', 'max' => '1.7');

        parent::__construct();

        if (_PS_VERSION_ < '1.5')
        {
            $sep = DIRECTORY_SEPARATOR;
            require_once _PS_MODULE_DIR_.$this->name.$sep.'backward_compatibility'.$sep.'backward.php';
            $this->context = Context::getContext();
            $this->smarty = $this->context->smarty;
        }

        $this->displayName = $this->l('Lengow');
        $this->description = $this->l('Lengow allows you to easily export your product catalogue from your Prestashop
        store and sell on Amazon, Cdiscount, Google Shopping, Criteo, LeGuide.com, Ebay, Rakuten, Priceminister..
        Choose from our 1,800 available marketing channels!');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the Lengow module ?');

        $this->installClass = new LengowInstall($this);

        $protocol_link = (Configuration::get('PS_SSL_ENABLED')) ? 'https://' : 'http://';
        $protocol_content = (isset($useSSL) and $useSSL and Configuration::get('PS_SSL_ENABLED')) ? 'https://' : 'http://';
        $link = new Link($protocol_link, $protocol_content);
        $this->context->smarty->assign('link', $link);
    }

    public function install()
    {
        if (!parent::install()) {

            return false;
        }
        return $this->installClass->install();
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        return $this->installClass->uninstall();
    }

    public function update()
    {
        return $this->installClass->update();
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCss(($this->_path).'/views/css/lengow-back-office.css');
    }
}
