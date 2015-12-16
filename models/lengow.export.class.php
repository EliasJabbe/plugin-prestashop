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
 * The Lengow Export Class.
 *
 */

class LengowExport
{

    /**
     * Default fields.
     */
    public static $DEFAULT_FIELDS = array(
        'id_product' => 'id',
        'name_product' => 'name',
        'reference_product' => 'reference',
        'supplier_reference' => 'supplier_reference',
        'manufacturer' => 'manufacturer',
        'category' => 'breadcrumb',
        'description' => 'description',
        'description_short' => 'short_description',
        'price_product' => 'price',
        'wholesale_price' => 'wholesale_price',
        'price_ht' => 'price_duty_free',
        'price_reduction' => 'price_sale',
        'pourcentage_reduction' => 'price_sale_percent',
        'quantity' => 'quantity',
        'weight' => 'weight',
        'ean' => 'ean',
        'upc' => 'upc',
        'ecotax' => 'ecotax',
        'active' => 'active',
        'available_product' => 'available',
        'url_product' => 'url',
        'image_product' => 'image_1',
        'fdp' => 'price_shipping',
        'id_mere' => 'id_parent',
        'delais_livraison' => 'delivery_time',
        'image_product_2' => 'image_2',
        'image_product_3' => 'image_3',
        'reduction_from' => 'sale_from',
        'reduction_to' => 'sale_to',
        'meta_keywords' => 'meta_keywords',
        'meta_description' => 'meta_description',
        'url_rewrite' => 'url_rewrite',
        'product_type' => 'type',
        'product_variation' => 'variation',
        'currency' => 'currency',
        'condition' => 'condition',
        'supplier' => 'supplier',
        'minimal_quantity' => 'minimal_quantity',
        'is_virtual' => 'is_virtual',
        'available_for_order' => 'available_for_order',
        'available_date' => 'available_date',
        'show_price' => 'show_price',
        'visibility' => 'visibility',
        'available_now' => 'available_now',
        'available_later' => 'available_later',
        'stock_availables' => 'stock_availables',
        'description_html' => 'description_html',
        'availability' => 'availability',
    );

    /**
     * Additional head attributes export.
     */
    protected $head_attributes_export;

    /**
     * Additional head image export.
     */
    protected $head_images_export;

    /**
     * Format to return.
     */
    protected $format;

    /**
     * Product's Carrier.
     */
    protected $carrier;

    protected $feed;

    /**
     * Full export products + attributes.
     */
    protected $full = true;

    /**
     * Export selected products.
     */
    protected $all = false;

    /**
     * Max images.
     */
    protected $max_images = 0;

    /**
     * Attributes to export.
     */
    protected $attributes = array();

    /**
     * Features to export.
     */
    protected $features = array();

    /**
     * Stream return.
     */
    protected $stream = true;

    /**
     * Product data.
     */
    protected $data = array();

    /**
     * Product data.
     */
    public $full_title = true;

    /**
     * Include active products.
     */
    protected $showInactiveProduct = false;

    /**
     * Export out of stock product
     */
    protected $export_out_stock = false;

    /**
     * @var boolean Export active product.
     */
    protected $export_features = false;

    /**
     * @var integer amount of products to export
     */
    protected $limit = 0;

    /**
     * @var array product ids to be exported
     */
    protected $product_ids = array();

    /**
     * @var boolean export limited by a timeout
     */
    protected $export_timeout = false;

    /**
     * Construct new Lengow export.
     *
     * @param array params optional options
     * string #format : Export Format (csv|yaml|xml|json)
     * boolean #stream : Display file when call script (1) | Save File (0)
     * boolean #out_stock : Export product in stock and out stock (1) | Export Only in stock product (0)
     * int #limit : Limit product to export
     * boolean #show_inactive_product : Export active and inactive product (1) | Export Only active product (0)
     * boolean #show_product_combination : Export product declinaison (1) | Export Only simple product (0)
     * @return LengowExport
     */
    public function __construct($params = array())
    {
        $this->setFormat(isset($params["format"]) ? $params["format"] : 'csv');

        $this->product_ids = (isset($params["product_ids"]) ? $params["product_ids"] : false);
        $this->stream = (isset($params["stream"]) ? $params["stream"] : false);
        $this->export_out_stock =  (isset($params["out_stock"]) ? $params["out_stock"] : false);
        $this->limit =  (isset($params["limit"]) ? (int)$params["limit"] : false);
        $this->showInactiveProduct = (isset($params["show_inactive_product"]) ?
            (bool)$params["show_inactive_product"] : false);
        $this->showProductCombination = (isset($params["show_product_combination"]) ?
            $params["show_product_combination"] : false);

        $this->checkCurrency();
        $this->setCarrier();

//        $format = null,
//        $fullmode = null,
//        $all = null,
//        $stream = null,
//        $full_title = null,
//        $inactive_products = null,
//        $export_features = null,
//        $limit = 0,
//        $out_stock = null,
//        $product_ids = array()

//        $this->export_features = (bool)$export_features;
//        $this->all = (bool)$all;

//        $this->full_title = (bool)$full_title;

//        $this->product_ids = $product_ids;
        return $this;
    }

    /**
     * Check currency to export.
     *
     * @throws LengowExportException
     *
     * @return boolean.
     */
    public function checkCurrency()
    {
        if (!Context::getContext()->currency) {
            throw new LengowExportException('Illegal Currency');
        }
        return true;
    }


    /**
     * Set Carrier to export.
     *
     * @throws LengowExportException
     *
     * @return boolean.
     */
    public function setCarrier()
    {
        $carrier = LengowCore::getExportCarrier();
        if (!$carrier->id) {
            throw new LengowExportException('You must select a carrier in Lengow Export Tab');
        }
        $this->carrier = $carrier;
        return true;
    }


    /**
     * Set format to export.
     *
     * @param string $format The export format
     *
     * @throws LengowExportException
     *
     * @return boolean.
     */
    public function setFormat($format)
    {
        if (!in_array($format, LengowFeed::$AVAILABLE_FORMATS)) {
            throw new LengowExportException('Illegal export format');
        }
        $this->format = $format;
        return true;
    }

    /**
     * Execute the export.
     *
     * @return mixed.
     */
    public function exec()
    {
        try {
            // if timeout : force export in file
            if (Configuration::get('LENGOW_EXPORT_TIMEOUT') && (int)Configuration::get('LENGOW_EXPORT_TIMEOUT') > 0) {
                $this->stream = false;
            }

            $shop = new Shop(Context::getContext()->shop->id);
            $shop_name = '';
            if (_PS_VERSION_ >= '1.5') {
                $shop_name = $shop->name;
            }
            LengowCore::log('Export - init ' . $shop_name, !$this->stream);
            if ((int)Configuration::get('LENGOW_EXPORT_TIMEOUT') > 0) {
                $this->export_timeout = true;
                Configuration::updateValue('LENGOW_EXPORT_START_' . Context::getContext()->language->iso_code, time());
                Configuration::updateValue(
                    'LENGOW_EXPORT_END_' . Context::getContext()->language->iso_code,
                    time() + Configuration::get('LENGOW_EXPORT_TIMEOUT')
                );
            } else {
                $this->export_timeout = false;
            }
            if (Configuration::get('LENGOW_IMAGES_COUNT') == 'all') {
                $this->max_images = LengowProduct::getMaxImages();
            } else {
                $this->max_images = (int)Configuration::get('LENGOW_IMAGES_COUNT');
            }

            // get fields to export
            $export_fields = $this->getFields();
            // get products to be exported
            if ($this->export_timeout) {
                $products = LengowProduct::exportIds(
                    $this->all,
                    $this->showInactiveProduct,
                    $this->product_ids,
                    $this->export_out_stock,
                    Configuration::get('LENGOW_EXPORT_LAST_ID_' . Context::getContext()->language->iso_code)
                );
            } else {
                $products = LengowProduct::exportIds(
                    $this->all,
                    $this->showInactiveProduct,
                    $this->product_ids,
                    $this->export_out_stock
                );
            }
            // if no products : export all
            if (!$products) {
                $products = LengowProduct::exportIds(true);
            }

            LengowCore::log(
                'Export - ' . count($products) . ' product' . (count($products) > 1 ? 's' : '') . ' found',
                !$this->stream
            );
            $this->export($products, $export_fields, $shop);

            LengowCore::log('Export - end', !$this->stream);

        } catch (Exception $e) {
            LengowCore::log('Export - error : ' . $e->getMessage(), true);
        }
    }

    /**
     * Export products
     *
     * @param array $products list of products to be exported
     * @param array $fields list of fields
     * @param Shop $shop shop being exported
     */
    public function export($products, $fields, $shop)
    {
        $product_count = 0;
        $file_feed = null;
        if ($this->export_timeout) {
            $file_feed = 'flux-'.$shop->id.'-'.Context::getContext()->language->iso_code.'-temp.'.$this->format;
        }

        $this->feed = new LengowFeed($this->stream, $this->format, isset($shop->name) ? $shop->name : 'default', $file_feed);
        $this->feed->write('header', $fields);
        $is_first = true;
        foreach ($products as $p) {

            $product_data = array();
            $combinations_data = array();

            $product = new LengowProduct(
                $p['id_product'],
                Context::getContext()->language->id,
                array("carrier" => $this->carrier)
            );
            foreach ($fields as $field) {
                if (isset(LengowExport::$DEFAULT_FIELDS[$field])) {
                    $product_data[$field] = $product->getData(
                        LengowExport::$DEFAULT_FIELDS[$field],
                        null,
                        $this->full_title
                    );
                } else {
                    $product_data[$field] = $product->getData($field, null, $this->full_title);
                }

                // export product attributes ?
                if ($this->showProductCombination && $product->hasAttributes()) {
                    $combinations = $product->getCombinations();
                    if (empty($combinations)) {
                        throw new LengowExportException('Unable to retrieve product combinations');
                    }
                    foreach ($combinations as $combination) {
                        if (!$this->export_out_stock &&
                            $product->getData('quantity', $combination['id_product_attribute']) <= 0
                        ) {
                            continue;
                        }
                        $key = $product->id . '_' . $combination['id_product_attribute'];
                        if (isset(LengowExport::$DEFAULT_FIELDS[$field])) {
                            $combinations_data[$key][$field] = $product->getData(
                                LengowExport::$DEFAULT_FIELDS[$field],
                                $combination['id_product_attribute'],
                                $this->full_title
                            );
                        } else {
                            $combinations_data[$key][$field] = $product->getData(
                                $field,
                                $combination['id_product_attribute'],
                                $this->full_title
                            );
                        }

                    }
                }
            }
            // write parent product
            $this->feed->write('body', $product_data, $is_first);
            $product_count++;

            // write combinations
            if (!empty($combinations_data)) {
                foreach ($combinations_data as $combination_data) {
                    $this->feed->write('body', $combination_data);
                }
            }
            if ($product_count > 0 && $product_count % 10 == 0) {
                LengowCore::log('Export - ' . $product_count . ' products', !$this->stream);
            }

            if ($this->limit > 0 && $product_count >= $this->limit) {
                break;
            }

            $exportEndIso = Configuration::get('LENGOW_EXPORT_END_' . Context::getContext()->language->iso_code);
            if ($this->export_timeout && time() > $exportEndIso) {
                Configuration::updateValue(
                    'LENGOW_EXPORT_LAST_ID_' . Context::getContext()->language->iso_code,
                    $p['id_product']
                );
                LengowCore::log(
                    'Export - stopped by timeout. ' . $product_count . ' products exported.',
                    !$this->stream
                );
                return;
            }
            $is_first = false;
        }

        Configuration::updateValue('LENGOW_EXPORT_LAST_ID_' . Context::getContext()->language->iso_code, 0);
        $success = $this->feed->end();

        if (!$success) {
            throw new LengowFileException(
                'Export file generation did not end properly. Please make sure the export folder is writable.',
                true
            );
        }
        if (!$this->stream) {
            $feed_url = $this->feed->getUrl();
            if ($feed_url && php_sapi_name() != "cli") {
                echo date('Y-m-d : H:i:s') . ' - Export - your feed is available here:
                <a href="' . $feed_url . '" target="_blank">' . $feed_url . '</a><br />';
                LengowCore::log('Export - your feed is available here: ' . $feed_url);
            }
        }
    }

    /**
     * Get fields to export
     *
     * @return array
     */
    protected function getFields()
    {
        $fields = array();

        // fields chosen in module config
        $export_fields = Tools::jsonDecode(Configuration::get('LENGOW_EXPORT_FIELDS'));
        if (is_array($export_fields)) {
            foreach ($export_fields as $field) {
                $fields[] = $field;
            }
        } else {
            foreach (LengowExport::$DEFAULT_FIELDS as $field) {
                $fields[] = $field;
            }
        }

        //Features
        if ($this->export_features) {
            $features = Feature::getFeatures(Context::getContext()->language->id);
            $features_selected = (array)Tools::jsonDecode(Configuration::get('LENGOW_EXPORT_SELECT_FEATURES'));
            foreach ($features as $feature) {
                if (!in_array($feature['id_feature'], $features_selected)) {
                    continue;
                }
                if (in_array($feature['name'], $fields)) {
                    $fields[] = $feature['name'] . '_1';
                } else {
                    $fields[] = $feature['name'];
                }
            }
        }
        // if export product variations -> get variations attributes
        if ($this->showProductCombination) {
            $attributes = AttributeGroup::getAttributesGroups(Context::getContext()->language->id);
            foreach ($attributes as $attribute) {
                if (!in_array($attribute['name'], $fields)) {
                    $fields[] = $attribute['name'];
                } else {
                    $fields[] = $attribute['name'] . '_2';
                }
            }
        }
        // Images
        if ($this->max_images > 3) {
            for ($i = 3; $i <= ($this->max_images - 1); $i++) {
                $fields[] = 'image_' . ($i + 1);
            }
        }
        // Allow to add extra fields
        return LengowExport::setAdditionalFields($fields);
    }

    /**
     * Get filename of generated feeds
     *
     * @return string
     */
    public function getFileName()
    {
        return $this->feed->getFilename();
    }

    /**
     * Get the default fields to be exported as HTML options
     *
     * @return array
     */
    public static function getDefaultFields()
    {
        $array_fields = array();
        foreach (self::$DEFAULT_FIELDS as $fields => $value) {
            $array_fields[] = new LengowOption($fields, $value . ' - (' . $fields . ')');
        }
        return $array_fields;
    }

    /**
     * Override this function in override/lengow.export.class.php to add header
     */
    public static function setAdditionalFields($fields)
    {
        /**
         * Write here your process
         *
         * ex : fields[] = 'my_header_value';
         */
        return $fields;
    }

    /**
     * Override this function to assign data for additional fields
     *
     * @param $product LengowProduct
     * @param $id_product_attribute
     * @return $array_product
     */
    public static function setAdditionalFieldsValues($product, $id_product_attribute = null, $array_product = null)
    {
        /**
         * Write here your process
         * $array_product['my_header_value'] = 'your value';
         */
        // This two lines are useless, but Prestashop validator require it.
        $product = $product;
        $id_product_attribute = $id_product_attribute;
        return $array_product;
    }
}
