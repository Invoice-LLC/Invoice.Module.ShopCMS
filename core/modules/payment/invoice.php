<?php

require "InvoiceSDK/RestClient.php";
require "InvoiceSDK/common/SETTINGS.php";
require "InvoiceSDK/common/ORDER.php";
require "InvoiceSDK/CREATE_TERMINAL.php";
require "InvoiceSDK/CREATE_PAYMENT.php";
require "InvoiceSDK/GET_TERMINAL.php";

/**
 * @connect_module_class_name CInvoice
 *
 */
class CInvoice extends PaymentModule
{
    function _initVars()
    {

        $this->title         = "Invoice";
        $this->description     = "Invoice Payment Module";
        $this->sort_order     = 1;

        $this->Settings = array(
            "CONF_PAYMENTMODULE_INVOICE_API_KEY",
            "CONF_PAYMENTMODULE_INVOICE_LOGIN",
            "CONF_PAYMENTMODULE_INVOCIE_DEFAULT_TERMINAL_NAME"
        );
    }

    function _initSettingFields()
    {

        $this->SettingsFields['CONF_PAYMENTMODULE_INVOICE_API_KEY'] = array(
            'settings_value'         => '',
            'settings_title'             => 'API key',
            'settings_description'     => 'Ваш API ключ(из ЛК Invoice)',
            'settings_html_function'     => 'setting_TEXT_BOX(0,',
            'sort_order'             => 1,
        );

        $this->SettingsFields['CONF_PAYMENTMODULE_INVOICE_LOGIN'] = array(
            'settings_value'         => '',
            'settings_title'             => 'Login',
            'settings_description'     => 'Логин от ЛК Invoice',
            'settings_html_function'     => 'setting_TEXT_BOX(0,',
            'sort_order'             => 1,
        );

        $this->SettingsFields['CONF_PAYMENTMODULE_INVOICE_DEFAULT_TERMINAL_NAME'] = array(
            'settings_value'         => '',
            'settings_title'             => 'Имя терминала',
            'settings_description'     => 'Имя терминала по умолчанию',
            'settings_html_function'     => 'setting_TEXT_BOX(0,',
            'sort_order'             => 1,
        );
    }

    function after_processing_html($orderID)
    {

        $order = ordGetOrder($orderID);
        $amount = round(100 * $order["order_amount"] * $order["currency_value"]) / 100;
        $terminal = $this->getTerminal();

        $request = new CREATE_PAYMENT();
        $request->order = $this->getOrder($amount, $orderID);
        $request->settings = $this->getSettings($terminal);
        $request->receipt = $this->getReceipt();

        $response = $this->getRestClient()->CreatePayment($request);

        if ($response == null or isset($response->error)) return "В данный момент оплата недоступна!";

        $form = "";

        $form .= "<table width='100%'>\n" .
            "	<tr>\n" .
            "		<td align='center'>\n";
        $form .= '<form name="invoice" action="' . $response->payment_url . '" method="get">';
        $form .= '<input class="button" type="submit" value="К оплате">';
        $form .= '</form>';

        $form .= "		</td>\n" .
            "	</tr>\n" .
            "</table>";

        return $form;
    }

    /**
     * @return INVOICE_ORDER
     */
    function getOrder($amount, $id)
    {
        $order = new INVOICE_ORDER();
        $order->amount = $amount;
        $order->id = "$id" . "-" . bin2hex(random_bytes(5));
        $order->currency = "RUB";

        return $order;
    }

    /**
     * @return SETTINGS
     */
    function getSettings($terminal)
    {
        $url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

        $settings = new SETTINGS();
        $settings->terminal_id = $terminal;
        $settings->success_url = $url;
        $settings->fail_url = $url;

        return $settings;
    }

    /**
     * @return ITEM
     */
    function getReceipt()
    {
        $receipt = array();

        return $receipt;
    }

    function after_payment_php($data)
    {
        $this->_initVars();

        $notification = $this->getNotification();
        $type = $notification["notification_type"];
        $id = strstr($notification["order"]["id"], "-", true);

        $signature = $notification["signature"];

        if ($signature != $this->getSignature($notification["id"], $notification["status"], $this->_getSettingValue('CONF_PAYMENTMODULE_INVOICE_API_KEY'))) {
            die("wrong signature");
        }

        $order = ordGetOrder($id);

        if ($type == "pay") {

            if ($notification["status"] == "successful") {
                $this->pay($order);
                die("successful");
            }
            if ($notification["status"] == "error") {
                $this->error($order);
                die("failed");
            }
        }

        die("null");
    }

    public function pay($order_id)
    {
        ostSetOrderStatusToOrder($order_id, 'Paid');
    }

    public function error($order_id)
    {
        ostSetOrderStatusToOrder($order_id, 'Failed');
    }

    public function getSignature($id, $status, $key)
    {
        return md5($id . $status . $key);
    }

    public function getNotification()
    {
        $postData = file_get_contents('php://input');
        return json_decode($postData, true);
    }

    function getTerminal()
    {
        if (!file_exists("invoice_tid")) file_put_contents("invoice_tid", "");
        $tid = file_get_contents("invoice_tid");
        $terminal = new GET_TERMINAL();
        $terminal->alias =  $tid;
        $info = $this->getRestClient()->GetTerminal($terminal);

        if ($tid == null or empty($tid) || $info->id == null || $info->id != $terminal->alias) {
            $request = new CREATE_TERMINAL();
            $request->name = $this->_getSettingValue('CONF_PAYMENTMODULE_INVOICE_DEFAULT_TERMINAL_NAME');
            $request->type = "dynamical";
            $request->description = "ShopCMS Terminal";
            $request->defaultPrice = 0;
            $response = $this->getRestClient()->CreateTerminal($request);

            if ($response == null or isset($response->error)) throw new Exception("Terminal error");

            $tid = $response->id;
            file_put_contents("invoice_tid", $tid);
        }

        return $tid;
    }

    function getRestClient()
    {
        $login = $this->_getSettingValue('CONF_PAYMENTMODULE_INVOICE_LOGIN');
        $api_key = $this->_getSettingValue('CONF_PAYMENTMODULE_INVOICE_API_KEY');
        return new RestClient($login, $api_key);
    }
}
