<?php

namespace MarSalDev\Module\DemoCarrier\Database;

use DemoCarrier;
use Carrier;
use Delivery;
use Db;
use RangeWeight;
use Zone;
use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Adapter\Language\LanguageDataProvider;

class Install
{

    /**
     * @var LanguageDataProvider $languageDataProvider
     */
    private $languageDataProvider;

    /**
     * @var Configuration $configuration
     */
    private $configuration;

    /**
     * @var int $defaultZone
     */
    private $defaultZone;

    public function __construct(LanguageDataProvider $languageDataProvider, Configuration $configuration)
    {
        $this->languageDataProvider = $languageDataProvider;
        $this->configuration = $configuration;

        $this->defaultZone = Zone::getIdByName('Europe');
    }

    public function runInstallation()
    {
        return $this->createStandardCarrier() && $this->createNoRangeNeededCarrier();
    }

    /**
     * @return true|false
     */
    public function createStandardCarrier()
    {
        /**
         * @var Carrier
         */
        $carrier = new Carrier();

        $carrier->name = 'Fast Standard Carrier';
        $carrier->active = true;
        $carrier->is_free = false;
        $carrier->need_range = true;
        $carrier->shipping_external = true; // This must be true in the case of a Carrier related to a module
        $carrier->external_module_name = DemoCarrier::MODULE_NAME;
        $carrier->is_module = true;
        $carrier->shipping_method = Carrier::SHIPPING_METHOD_WEIGHT;
        $carrier->shipping_handling = false;

        foreach ($this->languageDataProvider->getLanguages() as $language) {
            $carrier->delay[$language['id_lang']] = '2 working days';
        }

        // Save Carrier into database
        if (!$carrier->add())
            return false;

        // Assign to Customer groups
        $carrier->setGroups([1, 2, 3]); // ids 1, 2, 3 are the default ids for the default customer groups

        // Save id in a custom Configuration (not mandatory)
        $this->configuration->set(DemoCarrier::DEFAULT_CARRIER, $carrier->id);

        // Assign Carrier to a Zone
        $carrier->addZone($this->defaultZone);

        /**
         * @var RangeWeight $range
         *
         * Set ranges for the shipping calculation by weight (in this case)
         */
        $range = $carrier->getRangeObject(Carrier::SHIPPING_METHOD_WEIGHT);
        $range->id_carrier = $carrier->id;
        $range->delimiter1 = 0; // zero Kg (or the default weight measure unit set in the shop)
        $range->delimiter2 = 10; // ten Kg (or the default weight measure unit set in the shop)
        $range->add();

        // Clean delivery info
        $carrier->deleteDeliveryPrice('range_weight');

        // Add default delivery price (per zone/per range)
        $price_list[] = [
            'id_range_price' => null,
            'id_range_weight' => (int)$range->id,
            'id_carrier' => (int)$carrier->id,
            'id_zone' => (int)$this->defaultZone,
            'price' => 10,
            'id_shop' => null,
            'id_shop_group' => null
        ];
        $this->addDeliveryPrice($price_list);

        return true;
    }

    /**
     * @return true|false
     */
    public function createNoRangeNeededCarrier()
    {
        /**
         * @var Carrier
         */
        $carrier = new Carrier();

        $carrier->name = 'Fast External Carrier';
        $carrier->active = true;
        $carrier->is_free = false;
        $carrier->need_range = false; // Carrier with no range needed (price or weight)
        $carrier->shipping_external = true; // This must be true in the case of a Carrier related to a module
        $carrier->external_module_name = DemoCarrier::MODULE_NAME;
        $carrier->is_module = true;
        $carrier->shipping_method = Carrier::SHIPPING_METHOD_DEFAULT;
        $carrier->shipping_handling = false;

        foreach ($this->languageDataProvider->getLanguages() as $language) {
            $carrier->delay[$language['id_lang']] = 'Delivery calculated in the checkout';
        }

        // Save Carrier into database
        if (!$carrier->add())
            return false;

        // Assign to Customer groups
        $carrier->setGroups([1, 2, 3]); // ids 1, 2, 3 are the default ids for the default customer groups

        // Save id in a custom Configuration (not mandatory)
        $this->configuration->set(DemoCarrier::EXTERNAL_CARRIER, $carrier->id);

        // Assign Carrier to a Zone
        $carrier->addZone($this->defaultZone);


        /**
         * @var RangeWeight $range
         *
         * Set ranges for the shipping calculation by weight (in this case)
         */
        $range = $carrier->getRangeObject(Carrier::SHIPPING_METHOD_DEFAULT);
        $range->id_carrier = $carrier->id;
        $range->delimiter1 = 0;
        $range->delimiter2 = 999; //  Kg (or the default weight measure unit set in the shop)
        $range->add();

        // Clean delivery info
        $carrier->deleteDeliveryPrice('range_weight');
//
//        // Add correct default delivery price (per zone/per range)
//        $price_list[] = [
//            'id_range_price' => null,
//            'id_range_weight' => (int)$range->id,
//            'id_carrier' => (int)$carrier->id,
//            'id_zone' => (int)$this->defaultZone,
//            'price' => 0,
//            'id_shop' => null,
//            'id_shop_group' => null
//        ];
//        $this->addDeliveryPrice($price_list);

        return true;
    }

    public function addDeliveryPrice($price_list, $delete = false)
    {
        if (!$price_list) {
            return false;
        }

        $keys = array_keys($price_list[0]);
        if (!in_array('id_shop', $keys)) {
            $keys[] = 'id_shop';
        }
        if (!in_array('id_shop_group', $keys)) {
            $keys[] = 'id_shop_group';
        }

        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'delivery` (' . implode(', ', $keys) . ') VALUES ';
        foreach ($price_list as $values) {
            if (!isset($values['id_shop']) && !is_null($values['id_shop'])) {
                $values['id_shop'] = $values['id_shop'] = (Shop::getContext() == Shop::CONTEXT_SHOP) ? Shop::getContextShopID() : null;
            }
            if (!isset($values['id_shop_group']) && !is_null($values['id_shop_group'])) {
                $values['id_shop_group'] = (Shop::getContext() != Shop::CONTEXT_ALL) ? Shop::getContextShopGroupID() : null;
            }

            if ($delete) {
                Db::getInstance()->execute(
                    'DELETE FROM `' . _DB_PREFIX_ . 'delivery`
                    WHERE ' . (null === $values['id_shop'] ? 'ISNULL(`id_shop`) ' : 'id_shop = ' . (int)$values['id_shop']) . '
                    AND ' . (null === $values['id_shop_group'] ? 'ISNULL(`id_shop`) ' : 'id_shop_group=' . (int)$values['id_shop_group']) . '
                    AND id_carrier=' . (int)$values['id_carrier'] .
                    ($values['id_range_price'] !== null ? ' AND id_range_price=' . (int)$values['id_range_price'] : ' AND (ISNULL(`id_range_price`) OR `id_range_price` = 0)') .
                    ($values['id_range_weight'] !== null ? ' AND id_range_weight=' . (int)$values['id_range_weight'] : ' AND (ISNULL(`id_range_weight`) OR `id_range_weight` = 0)') . '
                    AND id_zone=' . (int)$values['id_zone']
                );
            }

            $sql .= '(';
            foreach ($values as $v) {
                if (null === $v) {
                    $sql .= 'NULL';
                } elseif (is_int($v) || is_float($v)) {
                    $sql .= $v;
                } else {
                    $sql .= '\'' . Db::getInstance()->escape($v, false, true) . '\'';
                }
                $sql .= ', ';
            }
            $sql = rtrim($sql, ', ') . '), ';
        }
        $sql = rtrim($sql, ', ');

        return Db::getInstance()->execute($sql);
    }
}
