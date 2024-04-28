<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

use MarSalDev\Module\DemoCarrier\Database\Install;

class DemoCarrier extends CarrierModule
{
    public const MODULE_NAME = 'democarrier';

    public const DEFAULT_CARRIER = 'DEMOCARRIER_DEFAULT_CARRIER_ID';
    public const EXTERNAL_CARRIER = 'DEMOCARRIER_EXTERNAL_CARRIER_ID';

    public function __construct()
    {
        $this->name = self::MODULE_NAME;
        $this->author = 'Marco Salvatore';
        $this->version = '1.0.0';
        $this->need_instance = 0;
        $this->tab = 'shipping_logistics';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans(
            'Demo Carrier Module',
            [],
            'Modules.Democarrier.Admin'
        );

        $this->description =
            $this->trans(
                'Help developers to understand how to develop a carrier Module',
                [],
                'Modules.Democarrier.Admin'
            );

        $this->ps_versions_compliancy = [
            'min' => '1.7.6.0',
            'max' => '8.99.99',
        ];
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('actionCarrierUpdate') &&
            (new Install(
                $this->get('prestashop.adapter.data_provider.language'),
                $this->get('prestashop.adapter.legacy.configuration')
            ))->runInstallation();
    }

    /**
     * @param $cart
     * @param $shipping_cost
     * @param $products
     *
     * @return float
     *
     * The getPackageShippingCost() method can also be used to compute the shipping price depending on the products:
     * $shipping_cost = $module->getPackageShippingCost($cart, $shipping_cost, $products);
     *
     * Pay attention: if you implement this method and getOrderShippingCost(), this one has the priority
     */
    public function getPackageShippingCost($cart, $shipping_cost, $products): float
    {
        return 222;
    }

    /**
     * @param $cart
     * @param $shipping_cost
     *
     * @return float
     *
     * getOrderShippingCost(): to compute the shipping price depending on the ranges that were set in the back office
     */
    public function getOrderShippingCost($cart, $shipping_cost): float
    {
        return 111;
    }

    /**
     * @return float
     *
     * getOrderShippingCostExternal(): to compute the shipping price without using the ranges.
     *
     * This method is called only if !$carrier->need_range (bug in Carrier->getCarriersForOrder() method)
     * in that case the other methods will be ignored
     */
    public function getOrderShippingCostExternal($cart): float // never called, probably due to a bug
    {
        return 333;
    }

    public function hookActionCarrierUpdate($params)
    {
        $id_carrier_old = (int) $params['id_carrier'];
        $id_carrier_new = (int) $params['carrier']->id;
        if ($id_carrier_old === (int) Configuration::get(self::DEFAULT_CARRIER)) {
            Configuration::updateValue(self::DEFAULT_CARRIER, $id_carrier_new);
        }

        if ($id_carrier_old === (int) Configuration::get(self::EXTERNAL_CARRIER)) {
            Configuration::updateValue(self::EXTERNAL_CARRIER, $id_carrier_new);
        }
    }
}
