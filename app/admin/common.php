<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.2 & 7.3
 * @ Decoder version: 1.0.6
 * @ Release: 10/08/2022
 */

Lang::load(APP_PATH . "admin/lang/zh-cn.php");
function get_modules($type = "plugin")
{
    $model = new app\admin\model\PluginModel();
    $plugins = $model->getList($type);
    return $plugins;
}
function get_order_random_num()
{
    return round(microtime(true), 4) * 10000 . mt_rand(1000, 9999);
}
function getCountryConfig($order = false, $sort_key = "sort", $sort_value = "SORT_DESC")
{
    $country = config("country.country");
    if ($order) {
        array_multisort(array_column($country, $sort_key), SORT_DESC, $country);
    }
    return $country;
}
function zjmf_public_decrypt($encryptData, $public_key)
{
    $crypted = "";
    foreach (str_split(base64_decode($encryptData), 256) as $chunk) {
        openssl_public_decrypt($chunk, $decryptData, $public_key);
        $crypted .= $decryptData;
    }
    return $crypted;
}

?>