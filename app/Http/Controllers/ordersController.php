<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Order;
use Pixelpeter\Woocommerce\WoocommerceServiceProvider;
use Woocommerce;

class ordersController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function orders()
    {
        include(app_path() . '/functions/config.php');
        $shops = getShops();
        $orders =  Woocommerce::get('orders');
        $old_orders = order::all();


    // Filtering new orders 
    foreach ($orders as $order){
    if( $order['status'] == 'processing'){

    $wporderid = $order['id'];
    $address2 = ' ';
     if(isset($order['shipping']['address_2'])){
        $address2 = $order['shipping']['address_2'];
     }
      // Getting the right counrty code 
     $country = code_to_country($order['shipping']['country']);
     $shipping_method = ' ';

     foreach ($order['shipping_lines'] as $lines ) {
        if (isset($lines['method_title'])){
     $shipping_method = $lines['method_title'];}
     
     }
     // getting shipping method
     $note = ' ';
     if (strpos($shipping_method, 'Standard') != false){
        $note = 'standard';
     }elseif (strpos($shipping_method, 'Premium') != false){
        $note = 'premium';
     }


        
        foreach ($order['line_items'] as $items) {
            $splitsku = explode('_', $items['sku']);
            $shopmatch = $splitsku[0];


            if(isset($splitsku[1])){
            $realsku = $splitsku[1];
            }else { $realsku = ' ';} 



        //check if order exists in the db we do nothing else we create the order 
        $order_match = $realsku .'_' . $wporderid;
        $match = Order::where('sku', $order_match)->first();
            if (!$match) {


                
            foreach ($shops as $key => $value) {
                if ( $key == $shopmatch){
                  if($value['type'] == 'shopify'){  // CODE FOR SHOPIFY STORES
                    $store = $value['website'];
                    $key = $value['API_key'];
                    $secret = $value['API_PASS'];
                    if ($note != ' '){
                        $note =  $value['shipping'][$note];
                    }

                //Product Variant id 
            $curl = curl_init('https://'.$key.':'.$secret.'@'.$store.'/admin/products.json');
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            $result = json_decode(curl_exec($curl), true);  
            curl_close($curl);
            

            foreach ($result['products'] as $result) {
                foreach ($result['variants'] as $variant) {
                    if($variant['sku'] == $realsku){
                        $id = $variant['id'];
                        
                    }
                }
            }

            echo 'this product id is ' . $id . '<br/>';

            // Create the order 

            $data = array("order" => [ "line_items" => [
                [
                "variant_id" => $id,
                "quantity" => $items['quantity']
                ]
            ],
            "customer" =>[
                "first_name" => 'Artur',
                "last_name" => 'cbdee.com',
                "email" => 'nmbdesign@gmail.com'
            ],
             "billing_address" => [
                "first_name" => $order['billing']['first_name'],
                "last_name" => $order['billing']['first_name'],
                "address1" => $order['billing']['address_1'],
                "phone" => $order['billing']['phone'],
                "city" => $order['shipping']['city'],
                "province" => $order['billing']['state'],
                "country" => $country,
                "zip" => $order['billing']['postcode']

             ],
             "shipping_address" => [
                "first_name" => $order['shipping']['first_name'],
                "last_name" => $order['shipping']['first_name'],
                "address1" => $order['shipping']['address_1'],
                "address2" => $address2,
                "phone" => $order['billing']['phone'],
                "city" => $order['shipping']['city'],
                "province" => $order['shipping']['state'],
                "country" => $country,
                "zip" => $order['shipping']['postcode']
                ],
                 "email" => $order['billing']['email'],
                "fulfillment_status" => "unfulfilled",
                "financial_status" => "paid",
                "status" => "open",
                "shipping_lines" => [
                    [
                      "custom" => True,
                      "price" => "0.00",
                      "title" => $note
                   ]
               ],
               

              ]);


                $data_string = json_encode($data);

                $ch = curl_init('https://'.$key.':'.$secret.'@'.$store.'/admin/orders.json');
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($data_string))
                        );

                        $result = json_decode(curl_exec($ch), true);
                        
                        var_dump($result);

                       
                        // Updaing data base with orders info 
                         $order = new Order();
                            $order->shopify_id = $result['order']['id'];
                            $order->wp_id = $wporderid;
                            $order->sku = $realsku .'_' . $wporderid;
                            $order->qty = $items['quantity'];
                            $order->status = 'sent';
                            $order->store = $shopmatch;
                            $order->save();
                        

                        echo 'done';







                }

                } // CODE FOR SHOPIFY STORES ENDS HERE

            }
            


            

        }

    }



    }

}



    
  } 


      /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function tracking()
    {

        include(app_path() . '/functions/config.php');
        $shops = getShops();
         $old_orders = order::all(); Order::where('status' , 'sent');

         foreach ($old_orders as $order) {
            if ($order->status == 'sent') {

              if ( $order->shopify_id != ''){  //CODE FOR SHOPIFY STORES STARTS HERE
            $order_id = $order->shopify_id; 
            $store = $order->store;
            $wp_id = $order->wp_id;

                $api = $shops[$store]['API_key'];
                $pass = $shops[$store]['API_PASS'];
                $website = $shops[$store]['website'];

                $curl = curl_init('https://'.$api.':'.$pass.'@'.$website.'/admin/orders/'. $order_id . '/fulfillments.json');
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                $results = json_decode(curl_exec($curl), true);
                curl_close($curl);

                foreach ($results["fulfillments"] as $status) {
                    if (isset($status['status'])) {
                        //change order status 
                        
                        if ($status['status'] == 'success'){


                           
                            $sku = explode('_', $order->sku);
                            $sku = $sku[0];
                            $data = [
                                'note' => 'tracking number for '. $order->qty . ' items of '. $sku .' is : ' . $status['tracking_number'] .' tracking url is : ' . $status['tracking_url'] .' <br/> <br/ > '
                            ];
    
                             Woocommerce::post('orders/'.$wp_id.'/notes', $data);

                              $data = [ 
                              "custom_tracking_provider"=> "Custom",
                              "custom_tracking_link"=> $status['tracking_url'],
                              "tracking_number"=> $status['tracking_number']
                                   ];

                           Woocommerce::POST('orders/'.$wp_id.'/shipment-trackings', $data);

                        // update order status to shipped
                         Order::where('shopify_id' , $order_id)->update(['status' => 'shipped']);
                         echo 'done';

                       }
                        }
                      }
                    } //CODE FOR SHOPIFY STORES ENDS HERE 
                }
            }
         
    }

}



