<h1>Invoice Payment Module</h1>

<h3>Установка</h3>

1. Скачайте [плагин](https://github.com/Invoice-LLC/Invoice.Module.ShopCMS/archive/master.zip) и распакуйте архив в корень вашего сайта
2. В админ-панели перейдите вовкладку **Модули->Модули оплаты**, затем нажмите "Установить"
3. Перейдите в редактирование модуля Invoice и впишите свои данные, затем нажмите "Сохранить"
4. Перейдите во вкладку **Настройки->Варианты оплаты** и добавьте новый вариант оплаты "Invoice"
5. В конец файла core/includes/helper.php добавьте следующий код:
```php
if(isset($_GET["invoice"])){
   $postData = file_get_contents('php://input');
   $notification =  json_decode($postData, true);
        
   if(!isset($notification['order'])) die();
    
   $id = $notification['order']['id'];

   $q = db_query( "select paymethod  from ".ORDERS_TABLE." where orderID=".$id;
   $order = db_fetch_row($q);

   if (!$order) die();
   
   $paymentMethod = payGetPaymentMethodById( $order["paymethod"] );
   $currentPaymentModule = modGetModuleObj( $paymentMethod["module_id"], PAYMENT_MODULE );
   die($currentPaymentModule->after_payment_php( $_GET ));
   
}
   
 ```
6. Добавьте уведомление в личном кабинете Invoice(Вкладка Настройки->Уведомления->Добавить)
      с типом **WebHook** и адресом: **%URL сайта%/index.php?invoice=true**<br>
      ![Imgur](https://imgur.com/lMmKhj1.png)
