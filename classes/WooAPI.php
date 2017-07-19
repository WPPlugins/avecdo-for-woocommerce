<?php

namespace Avecdo\Classes;

use AvecdoSDK\API;

if (!defined('ABSPATH')) {
    exit;
}

class WooAPI extends API
{

    /**
     *
     * @param $page
     * @param $limit
     * @param $lastRun
     * @return array
     */
    public function products($page, $limit, $lastRun)
    {
        return $this->context->getProducts((int) $page, (int) $limit, $lastRun);
    }

    /**
     * @return array
     */
    public function categories()
    {
        return $this->context->getCategories();
    }

    /**
     * @return array
     */
    public function shop()
    {
        return $this->context->getShop();
    }
}