<?php
/**
 * Copyright 2016 Lengow SAS.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 *
 * @author    Team Connector <team-connector@lengow.com>
 * @copyright 2016 Lengow SAS
 * @license   http://www.apache.org/licenses/LICENSE-2.0
 */

/**
 * The Lengow Hook class
 *
 */
class LengowHook
{

    const LENGOW_TRACK_HOMEPAGE = 'homepage';
    const LENGOW_TRACK_PAGE = 'page';
    const LENGOW_TRACK_PAGE_LIST = 'listepage';
    const LENGOW_TRACK_PAGE_PAYMENT = 'payment';
    const LENGOW_TRACK_PAGE_CART = 'basket';
    const LENGOW_TRACK_PAGE_LEAD = 'lead';
    const LENGOW_TRACK_PAGE_CONFIRMATION = 'confirmation';

    static private $_CURRENT_PAGE_TYPE = 'page';
    static private $_USE_SSL = false;
    static private $_ID_ORDER = '';
    static private $_ORDER_TOTAL = '';
    static private $_IDS_PRODUCTS = '';
    static private $_IDS_PRODUCTS_CART = '';
    static private $_ID_CATEGORY = '';

    protected $_alreadyShipped = array();

    private $module;

    public function __construct($module)
    {
        $this->module = $module;
        $this->context = Context::getContext();
    }

    public function registerHooks()
    {
        $error = false;
        $lengow_hook = array(
            // Common version
            'footer'                => '1.4',
            'postUpdateOrderStatus' => '1.4',
            'paymentTop'            => '1.4',
            'adminOrder'            => '1.4',
            'home'                  => '1.4',
            'updateOrderStatus'     => '1.4',
            'orderConfirmation'     => '1.4',
            // Version 1.5
            'actionAdminControllerSetMedia' => '1.5',
            'actionObjectUpdateAfter'       => '1.5',
        );
        foreach ($lengow_hook as $hook => $version) {
            if ($version <= Tools::substr(_PS_VERSION_, 0, 3)) {
                $log = 'Registering hook - ';
                if (!$this->module->registerHook($hook)) {
                    LengowMain::log($log . $hook . ': error');
                    $error = true;
                } else {
                    LengowMain::log($log . $hook . ': success');
                }
            }
        }
        return ($error ? false : true);
    }

    public function hookHome()
    {
        self::$_CURRENT_PAGE_TYPE = self::LENGOW_TRACK_HOMEPAGE;
    }

    /**
     * Generate tracker on front footer page.
     *
     * @return varchar The data.
     */
    public function hookFooter()
    {
        if (!Configuration::get('LENGOW_TRACKING_ENABLED')) {
            return '';
        }

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            self::$_USE_SSL = true;
        }

        $current_controller = $this->context->controller;
        if ($current_controller instanceof OrderConfirmationController) {
            self::$_CURRENT_PAGE_TYPE = self::LENGOW_TRACK_PAGE_CONFIRMATION;
        } elseif ($current_controller instanceof ProductController) {
            self::$_CURRENT_PAGE_TYPE = self::LENGOW_TRACK_PAGE;
        } elseif ($current_controller instanceof OrderController) {
            if ($current_controller->step == -1 || $current_controller->step == 0) {
                self::$_CURRENT_PAGE_TYPE = self::LENGOW_TRACK_PAGE_CART;
            } elseif ($current_controller instanceof IndexController) {
                self::$_CURRENT_PAGE_TYPE = self::LENGOW_TRACK_HOMEPAGE;
            }
        }

        // ID category
        if (!(self::$_ID_CATEGORY = (int)Tools::getValue('id_category'))) {
            if (isset($_SERVER['HTTP_REFERER'])
                && preg_match(
                    '!^(.*)\/([0-9]+)\-(.*[^\.])|(.*)id_category=([0-9]+)(.*)$!',
                    $_SERVER['HTTP_REFERER'],
                    $regs
                )
                && !strstr($_SERVER['HTTP_REFERER'], '.html')
            ) {
                if (isset($regs[2]) && is_numeric($regs[2])) {
                    self::$_ID_CATEGORY = (int)$regs[2];
                } elseif (isset($regs[5]) && is_numeric($regs[5])) {
                    self::$_ID_CATEGORY = (int)$regs[5];
                }
            } elseif ($id_product = (int)Tools::getValue('id_product')) {
                $product = new Product($id_product);
                self::$_ID_CATEGORY = $product->id_category_default;
            }
            if (self::$_ID_CATEGORY == 0) {
                self::$_ID_CATEGORY = '';
            }
        } else {
            self::$_CURRENT_PAGE_TYPE = self::LENGOW_TRACK_PAGE_LIST;
        }

        // Basket
        if (self::$_CURRENT_PAGE_TYPE == self::LENGOW_TRACK_PAGE_CART ||
            self::$_CURRENT_PAGE_TYPE == self::LENGOW_TRACK_PAGE_PAYMENT
        ) {
            self::$_ORDER_TOTAL = $this->context->cart->getOrderTotal();
        }

        // Product IDS
        if (self::$_CURRENT_PAGE_TYPE == self::LENGOW_TRACK_PAGE_LIST
            || self::$_CURRENT_PAGE_TYPE == self::LENGOW_TRACK_PAGE
            || self::$_CURRENT_PAGE_TYPE == self::LENGOW_TRACK_PAGE_CART
        ) {
            $array_products = array();
            $products_cart = array();
            $products = (
                isset($this->context->smarty->tpl_vars['products'])
                ? $this->context->smarty->tpl_vars['products']->value
                : array()
            );

            if (!empty($products)) {
                $i = 1;
                foreach ($products as $p) {
                    if (is_object($p)) {
                        switch (Configuration::get('LENGOW_TRACKING_ID')) {
                            case 'upc':
                                $id_product = $p->upc;
                                break;
                            case 'ean':
                                $id_product = $p->ean13;
                                break;
                            case 'ref':
                                $id_product = $p->reference;
                                break;
                            default:
                                if (isset($p->id_product_attribute)) {
                                    $id_product = $p->id . '_' . $p->id_product_attribute;
                                } else {
                                    $id_product = $p->id;
                                }
                                break;
                        }
                        $products_cart[] = 'i'.$i.'='.$id_product
                            .'&p'.$i.'='.(isset($p->price_wt) ? $p->price_wt : $p->price)
                            .'&q'.$i.'='.$p->quantity;
                    } else {
                        switch (Configuration::get('LENGOW_TRACKING_ID')) {
                            case 'upc':
                                $id_product = $p['upc'];
                                break;
                            case 'ean':
                                $id_product = $p['ean13'];
                                break;
                            case 'ref':
                                $id_product = $p['reference'];
                                break;
                            default:
                                if (array_key_exists('id_product_attribute', $p) && $p['id_product_attribute']) {
                                    $id_product = $p['id_product'] . '_' . $p['id_product_attribute'];
                                } else {
                                    $id_product = $p['id_product'];
                                }
                                break;
                        }
                        $products_cart[] = 'i'.$i.'='.$id_product
                            .'&p'.$i.'='.(isset($p['price_wt']) ? $p['price_wt'] : $p['price'])
                            .'&q'.$i.'='.$p['quantity'];
                    }
                    $i++;
                    $array_products[] = $id_product;
                }
            } else {
                $p = (
                    isset($this->context->smarty->tpl_vars['product'])
                    ? $this->context->smarty->tpl_vars['product']->value
                    : null
                );
                if ($p instanceof Product) {
                    switch (Configuration::get('LENGOW_TRACKING_ID')) {
                        case 'upc':
                            $id_product = $p->upc;
                            break;
                        case 'ean':
                            $id_product = $p->ean13;
                            break;
                        case 'ref':
                            $id_product = $p->reference;
                            break;
                        default:
                            if (isset($p->id_product_attribute)) {
                                $id_product = $p->id . '_' . $p->id_product_attribute;
                            } else {
                                $id_product = $p->id;
                            }
                            break;
                    }
                    $array_products[] = $id_product;
                }
            }
            self::$_IDS_PRODUCTS_CART = implode('&', $products_cart);
            self::$_IDS_PRODUCTS = implode('|', $array_products);
        }

        if (!isset($this->smarty)) {
            $this->smarty = $this->context->smarty;
        }

        // Generate tracker
        if (self::$_CURRENT_PAGE_TYPE == self::LENGOW_TRACK_PAGE_CONFIRMATION) {
            $currency = $this->context->currency;
            $shop_id = $this->context->shop->id;
            $this->context->smarty->assign(
                array(
                    'account_id'        => LengowMain::getIdAccount(),
                    'order_ref'         => self::$_ID_ORDER,
                    'amount'            => self::$_ORDER_TOTAL,
                    'currency_order'    => $currency->iso_code,
                    'payment_method'    => self::$_ID_ORDER,
                    'cart'              => self::$_IDS_PRODUCTS_CART,
                    'newbiz'            => 1,
                    'secure'            => 0,
                    'valid'             => 1,
                    'page_type'         => self::$_CURRENT_PAGE_TYPE
                )
            );
            return $this->module->display(_PS_MODULE_LENGOW_DIR_, 'views/templates/front/tagpage.tpl');
        }
        return '';
    }

    /**
     * Hook before an status' update to synchronize status with lengow.
     *
     * @param array $args Arguments of hook
     */
    public function hookUpdateOrderStatus($args)
    {
        $lengow_order = new LengowOrder($args['id_order']);
        // Not send state if we are on lengow import module
        if (LengowOrder::isFromLengow($args['id_order']) && LengowImport::$current_order != $lengow_order->id_lengow) {
            LengowMain::disableMail();
        }
    }

    /**
     * Hook after an status' update to synchronize status with lengow.
     *
     * @param array $args Arguments of hook
     */
    public function hookPostUpdateOrderStatus($args)
    {
        $lengow_order = new LengowOrder($args['id_order']);
        // do nothing if order is not from Lengow or is being imported
        if (LengowOrder::isFromLengow($args['id_order'])
            && LengowImport::$current_order != $lengow_order->id_lengow
            && !array_key_exists($lengow_order->id_lengow, $this->_alreadyShipped)
        ) {
            $new_order_state = $args['newOrderStatus'];
            $id_order_state = $new_order_state->id;
            $id_shop = (_PS_VERSION_ < 1.5 ? null : (int)$lengow_order->id_shop);
            // Compatibility V2
            if ($lengow_order->id_flux != null) {
                $lengow_order->checkAndChangeMarketplaceName();
            }
            $marketplace = LengowMain::getMarketplaceSingleton((string)$lengow_order->lengow_marketplace, $id_shop);
            if ($marketplace->isLoaded()) {
                // Call Lengow API WSDL to send shipped state order
                if ($id_order_state == LengowMain::getOrderState('shipped')) {
                    $marketplace->wsdl('ship', $lengow_order->id_lengow, $args);
                    $this->_alreadyShipped[$lengow_order->id_lengow] = true;
                }
                // Call Lengow API WSDL to send refuse state order
                if ($id_order_state == LengowMain::getOrderState('canceled')) {
                    $marketplace->wsdl('cancel', $lengow_order->id_lengow, $args);
                    $this->_alreadyShipped[$lengow_order->id_lengow] = true;
                }
            }
        }
    }

    /**
     * Update, if isset tracking number
     */
    public function hookActionObjectUpdateAfter($args)
    {
        if ($args['object'] instanceof Order) {
            if (LengowOrder::isFromLengow($args['object']->id)) {
                $lengow_order = new LengowOrder($args['object']->id);
                if ($lengow_order->shipping_number != ''
                    && $args['object']->current_state == LengowMain::getOrderState('shipped')
                    && LengowImport::$current_order != $lengow_order->id_lengow
                    && !array_key_exists($lengow_order->id_lengow, $this->_alreadyShipped)
                ) {
                    $params = array();
                    $params['id_order'] = $args['object']->id;
                    $id_shop = (_PS_VERSION_ < 1.5 ? null : (int)$lengow_order->id_shop);
                    // Compatibility V2
                    if ($lengow_order->id_flux != null) {
                        $lengow_order->checkAndChangeMarketplaceName();
                    }
                    $marketplace = LengowMain::getMarketplaceSingleton(
                        (string)$lengow_order->lengow_marketplace,
                        $id_shop
                    );
                    if ($marketplace->isLoaded()) {
                        $marketplace->wsdl('ship', $lengow_order->id_lengow, $params);
                        $this->_alreadyShipped[$lengow_order->id_lengow] = true;
                    }
                }
            }
        }
    }

    /**
     * Hook on order confirmation page to init order's product list.
     *
     * @param array $args Arguments of hook
     */
    public function hookOrderConfirmation($args)
    {
        self::$_CURRENT_PAGE_TYPE = self::LENGOW_TRACK_PAGE_CONFIRMATION;
        self::$_ID_ORDER = $args['objOrder']->id;
        self::$_ORDER_TOTAL = $args['total_to_pay'];
        $ids_products = array();
        $products_list = $args['objOrder']->getProducts();
        $i = 0;
        $products_cart = array();
        foreach ($products_list as $p) {
            $i++;
            switch (Configuration::get('LENGOW_TRACKING_ID')) {
                case 'upc':
                    $id_product = $p['upc'];
                    break;
                case 'ean':
                    $id_product = $p['ean13'];
                    break;
                case 'ref':
                    $id_product = $p['reference'];
                    break;
                default:
                    if ($p['product_attribute_id']) {
                        $id_product = $p['product_id'] . '_' . $p['product_attribute_id'];
                    } else {
                        $id_product = $p['product_id'];
                    }
                    break;
            }
            // Ids Product
            $ids_products[] = $id_product;

            // Basket Product
            $products_cart[] = 'i' . $i . '=' . $id_product . '&p' . $i .
                '=' . Tools::ps_round($p['unit_price_tax_incl'], 2) . '&q' . $i . '=' . $p['product_quantity'];
        }
        self::$_IDS_PRODUCTS_CART = implode('&', $products_cart);
        self::$_IDS_PRODUCTS = implode('|', $ids_products);
    }

    /**
     * Hook on Payment page.
     *
     * @param array $args Arguments of hook
     */
    public function hookPaymentTop($args)
    {
        self::$_CURRENT_PAGE_TYPE = self::LENGOW_TRACK_PAGE;
        $args = 0; // Prestashop validator
    }

    /**
     * Hook on header dashboard.
     *
     * @param array $args Arguments of hook
     */
    public function hookActionAdminControllerSetMedia($args)
    {
        $this->context = Context::getContext();

        $controllers = array('admindashboard', 'adminhome', 'adminlengow');
        if (in_array(Tools::strtolower(Tools::getValue('controller')), $controllers)) {
            $this->context->controller->addJs($this->module->getPathUri() . 'views/js/chart.min.js');
        }

        if (Tools::getValue('controller') == 'AdminModules' && Tools::getValue('configure') == 'lengow') {
            $this->context->controller->addJs($this->module->getPathUri() . '/views/js/admin.js');
            $this->context->controller->addCss($this->module->getPathUri() . '/views/css/admin.css');
        }
        if (Tools::getValue('controller') == 'AdminOrders') {
            $this->context->controller->addJs($this->module->getPathUri() . '/views/js/admin.js');
        }
        $args = 0; // Prestashop validator
    }

    /**
     * Hook on admin page's order.
     *
     * @param array $args Arguments of hook
     *
     * @return display
     */
    public function hookAdminOrder($args)
    {
        if (LengowOrder::isFromLengow($args['id_order'])) {
            $lengow_order = new LengowOrder($args['id_order']);
            if (Tools::getValue('action') == 'synchronize') {
                $id_shop = (_PS_VERSION_ < 1.5 ? null : (int)$lengow_order->id_shop);
                if ($lengow_order->id_flux != null) {
                    $lengow_order->checkAndChangeMarketplaceName();
                }
                $order_ids = LengowOrder::getAllOrderIdsFromLengowOrder(
                    $lengow_order->id_lengow,
                    $lengow_order->lengow_marketplace
                );
                if (count($order_ids) > 0) {
                    $presta_ids = array();
                    foreach ($order_ids as $order_id) {
                        $presta_ids[] = $order_id['id_order'];
                    }
                    $connector  = new LengowConnector(
                        LengowMain::getAccessToken($id_shop),
                        LengowMain::getSecretCustomer($id_shop)
                    );
                    $orders = $connector->patch(
                        '/v3.0/orders',
                        array(
                            'account_id'            => LengowMain::getIdAccount($id_shop),
                            'marketplace_order_id'  => $lengow_order->id_lengow,
                            'marketplace'           => $lengow_order->lengow_marketplace,
                            'merchant_order_id'     => $presta_ids
                        )
                    );
                }
            }

            if (_PS_VERSION_ < '1.5') {
                $action_reimport = _PS_MODULE_LENGOW_DIR_.'v14/ajax.php?';
                $action_synchronize = 'index.php?tab=AdminOrders&id_order='.$lengow_order->id.'&vieworder&action=synchronize&token='.Tools::getAdminTokenLite('AdminOrders');
                $add_script = true;
            } else {
                $action_reimport = 'index.php?controller=AdminLengow&id_order='.$lengow_order->id.'&lengoworderid='.$lengow_order->id_lengow.'&action=reimportOrder&ajax&token='.Tools::getAdminTokenLite('AdminLengow');
                $action_synchronize = 'index.php?controller=AdminOrders&id_order='.$lengow_order->id.'&vieworder&action=synchronize&token='.Tools::getAdminTokenLite('AdminOrders');
                $add_script = false;
            }

            $template_data = array(
                'id_order_lengow'       => $lengow_order->id_lengow,
                'id_flux'               => $lengow_order->id_flux,
                'marketplace'           => $lengow_order->lengow_marketplace,
                'total_paid'            => $lengow_order->lengow_total_paid,
                'carrier'               => $lengow_order->lengow_carrier,
                'tracking_method'       => $lengow_order->lengow_method,
                'tracking'              => $lengow_order->lengow_tracking,
                'tracking_carrier'      => $lengow_order->lengow_carrier,
                'sent_markeplace'       => $lengow_order->lengow_sent_marketplace ? $this->module->l('yes') : $this->module->l('no'),
                'message'               => $lengow_order->lengow_message,
                'action_synchronize'    => $action_synchronize,
                'action_reimport'       => $action_reimport,
                'order_id'              => $args['id_order'],
                'add_script'            => $add_script,
                'url_script'            => _PS_MODULE_LENGOW_DIR_.'views/js/admin.js',
                'version'               => _PS_VERSION_
            );

            $this->context->smarty->assign($template_data);
            if (_PS_VERSION_ >= '1.6') {
                return $this->module->display(_PS_MODULE_LENGOW_DIR_, 'views/templates/admin/order/info_16.tpl');
            }
            return $this->module->display(_PS_MODULE_LENGOW_DIR_, 'views/templates/admin/order/info.tpl');
        }
        return '';
    }
}