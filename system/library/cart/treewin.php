<?php

namespace Cart;
use DateTime;

class Treewin
{
    protected $db = null;

    private $user = 'APPKEY';
    private $pass = 'APPSECRET';
    private $api = [
        'base' => 'https://ws01.treewin.com.tr/',
        'login' => 'https://ws01.treewin.com.tr/login',
        'save' => 'https://ws01.treewin.com.tr/rest/treewin/order/save'
    ];

    private $token = null;

    public function __construct($registry)
    {
        $this->db = $registry->get('db');

        if (!$this->token) {
            $this->token = $this->auth();
        }
    }

    private function auth()
    {
        $data = [
            'appKey' => $this->user,
            'appSecret' => $this->pass
        ];

        $ch = curl_init($this->api['login']);
        $payload = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $this->token = $result;
    }

    public function saveContract($order_id)
    {
        $orderData = $this->getOrder($order_id);
        $products = $this->getOrderProducts($order_id);


        $productsArr = [];

        foreach ($products as $product) {
            $productData = $this->db->query("SELECT m.name as manufacturer, p.*, pd.* FROM oc_product p 
            LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id 
            LEFT JOIN oc_manufacturer m ON m.manufacturer_id = p.manufacturer_id
            WHERE p.product_id = {$product['product_id']}")->row;

            $productsArr[] = [
                'barcode' => isset($productData['model']) ? $productData['model'] : '-',
                'brand' => isset($productData['manufacturer']) ? $productData['manufacturer'] : '-',
                'discountAmount' => 0,
                'finalTotalAmountWithVat' => 0,
                'groupCode' => '-',
                'itemId' => $product['product_id'],
                'price' => $product['price'],
                'quantity' => $product['quantity'],
                'siteSku' => isset($productData['sku']) ? $productData['sku'] : '-',
                'sku' => isset($productData['sku']) ? $productData['sku'] : '-',
                'status' => 1,
                'title' => isset($product['name']) ? $product['name'] : '-',
                'totalAmount' => $product['total'],
                'totalAmountWithVat' => 0,
                'variantText' => '-',
                'vatAmount' => 0,
                'vatRate' => 0
            ];
        }


        $datetime = new DateTime($orderData['date_added']);

        $date =  $datetime->format(DateTime::ATOM);

        $data = [
            [
                'billingAddress' => isset($orderData['payment_address_1']) ? $orderData['payment_address_1'] : '-',
                'billingCity' => isset($orderData['payment_city']) ? $orderData['payment_city'] : '-',
                'billingCountry' => isset($orderData['payment_country']) ? $orderData['payment_country'] : '-',
                'billingDistrict' => isset($orderData['payment_zone']) ? $orderData['payment_zone'] : '-',
                'billingFullName' => $orderData['payment_firstname'] . ' ' . $orderData['payment_firstname'],
                'billingMail' => isset($orderData['email']) ? $orderData['email'] : '-',
                'billingPhone' => isset($orderData['telephone']) ? $orderData['telephone'] : '-',
                'billingTaxOffice' => '-',
                'billingTc' => '-',
                'billingVkn' => '-',
                'cargoCode' => '-',
                'cargoCompanyId' => '-',
                'cargoTrackingCode' => '-',
                'companyVendorNumber' => '-',
                'customerFullName' => $orderData['payment_firstname'] . ' ' . $orderData['payment_firstname'],
                'customerMail' => isset($orderData['email']) ? $orderData['email'] : '-',
                'customerPhone' => isset($orderData['telephone']) ? $orderData['telephone'] : '-',
                'customerTc' => '-',
                'customerVkn' => '-',
                'extraAmount' => 0,
                'installment' => 0,
                'installmentAmount' => 0,
                'items' => $productsArr
                ,
                'orderDate' => $date,
                'orderId' => $order_id,
                'orderNo' => $order_id,
                'paymentProvider' => 'Online POS',
                'paymentTypeId' => 4,
                'shippingAddress' => isset($orderData['shipping_address_1']) ? $orderData['shipping_address_1'] : '-',
                'shippingAmount' => 0,
                'shippingCity' => isset($orderData['shipping_city']) ? $orderData['shipping_city'] : '-',
                'shippingCountry' => isset($orderData['shipping_country']) ? $orderData['shipping_country'] : '-',
                'shippingDistrict' => isset($orderData['shipping_zone']) ? $orderData['shipping_zone'] : '-',
                'shippingFullName' => (isset($orderData['shipping_firstname']) ? $orderData['shipping_firstname'] : '-') . ' ' . (isset($orderData['shipping_lastname']) ? $orderData['shipping_lastname'] : '-'),
                'shippingMail' => isset($orderData['email']) ? $orderData['email'] : ' -',
                'shippingNote' => isset($orderData['comment']) ? $orderData['comment'] : '-',
                'shippingPhone' => isset($orderData['telephone']) ? $orderData['telephone'] : '-',
                'status' => 1,
                'totalAmount' => $orderData['total']
            ]
        ];

        $headers = [
            'Content-Type: Application/JSON',
            "Authorization: {$this->token}"
        ];

        $ch = curl_init($this->api['save']);
        $payload = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        return print_r($result);
    }

    public function getOrderProducts($order_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

        return $query->rows;
    }

    public function getOrder($order_id)
    {
        $order_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "' AND order_status_id > '0'");

        if ($order_query->num_rows) {
            $country_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$order_query->row['payment_country_id'] . "'");

            if ($country_query->num_rows) {
                $payment_iso_code_2 = $country_query->row['iso_code_2'];
                $payment_iso_code_3 = $country_query->row['iso_code_3'];
            } else {
                $payment_iso_code_2 = '';
                $payment_iso_code_3 = '';
            }

            $zone_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = '" . (int)$order_query->row['payment_zone_id'] . "'");

            if ($zone_query->num_rows) {
                $payment_zone_code = $zone_query->row['code'];
            } else {
                $payment_zone_code = '';
            }

            $country_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$order_query->row['shipping_country_id'] . "'");

            if ($country_query->num_rows) {
                $shipping_iso_code_2 = $country_query->row['iso_code_2'];
                $shipping_iso_code_3 = $country_query->row['iso_code_3'];
            } else {
                $shipping_iso_code_2 = '';
                $shipping_iso_code_3 = '';
            }

            $zone_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = '" . (int)$order_query->row['shipping_zone_id'] . "'");

            if ($zone_query->num_rows) {
                $shipping_zone_code = $zone_query->row['code'];
            } else {
                $shipping_zone_code = '';
            }

            return array(
                'order_id'                => $order_query->row['order_id'],
                'invoice_no'              => $order_query->row['invoice_no'],
                'invoice_prefix'          => $order_query->row['invoice_prefix'],
                'store_id'                => $order_query->row['store_id'],
                'store_name'              => $order_query->row['store_name'],
                'store_url'               => $order_query->row['store_url'],
                'customer_id'             => $order_query->row['customer_id'],
                'firstname'               => $order_query->row['firstname'],
                'lastname'                => $order_query->row['lastname'],
                'telephone'               => $order_query->row['telephone'],
                'email'                   => $order_query->row['email'],
                'payment_firstname'       => $order_query->row['payment_firstname'],
                'payment_lastname'        => $order_query->row['payment_lastname'],
                'payment_company'         => $order_query->row['payment_company'],
                'payment_address_1'       => $order_query->row['payment_address_1'],
                'payment_address_2'       => $order_query->row['payment_address_2'],
                'payment_postcode'        => $order_query->row['payment_postcode'],
                'payment_city'            => $order_query->row['payment_city'],
                'payment_zone_id'         => $order_query->row['payment_zone_id'],
                'payment_zone'            => $order_query->row['payment_zone'],
                'payment_zone_code'       => $payment_zone_code,
                'payment_country_id'      => $order_query->row['payment_country_id'],
                'payment_country'         => $order_query->row['payment_country'],
                'payment_iso_code_2'      => $payment_iso_code_2,
                'payment_iso_code_3'      => $payment_iso_code_3,
                'payment_address_format'  => $order_query->row['payment_address_format'],
                'payment_method'          => $order_query->row['payment_method'],
                'shipping_firstname'      => $order_query->row['shipping_firstname'],
                'shipping_lastname'       => $order_query->row['shipping_lastname'],
                'shipping_company'        => $order_query->row['shipping_company'],
                'shipping_address_1'      => $order_query->row['shipping_address_1'],
                'shipping_address_2'      => $order_query->row['shipping_address_2'],
                'shipping_postcode'       => $order_query->row['shipping_postcode'],
                'shipping_city'           => $order_query->row['shipping_city'],
                'shipping_zone_id'        => $order_query->row['shipping_zone_id'],
                'shipping_zone'           => $order_query->row['shipping_zone'],
                'shipping_zone_code'      => $shipping_zone_code,
                'shipping_country_id'     => $order_query->row['shipping_country_id'],
                'shipping_country'        => $order_query->row['shipping_country'],
                'shipping_iso_code_2'     => $shipping_iso_code_2,
                'shipping_iso_code_3'     => $shipping_iso_code_3,
                'shipping_address_format' => $order_query->row['shipping_address_format'],
                'shipping_method'         => $order_query->row['shipping_method'],
                'comment'                 => $order_query->row['comment'],
                'total'                   => $order_query->row['total'],
                'order_status_id'         => $order_query->row['order_status_id'],
                'language_id'             => $order_query->row['language_id'],
                'currency_id'             => $order_query->row['currency_id'],
                'currency_code'           => $order_query->row['currency_code'],
                'currency_value'          => $order_query->row['currency_value'],
                'date_modified'           => $order_query->row['date_modified'],
                'date_added'              => $order_query->row['date_added'],
                'ip'                      => $order_query->row['ip']
            );
        } else {
            return false;
        }
    }
}
