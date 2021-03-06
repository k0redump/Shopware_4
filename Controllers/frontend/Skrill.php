<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License and of our
 * proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 *
 * @category   Shopware
 * @package    Shopware_Plugins
 * @subpackage Skrill
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Skrill Holdings Ltd.
 * @author     $Author$
 */

class Shopware_Controllers_Frontend_PaymentSkrill extends Shopware_Controllers_Frontend_Payment
    {
    private static $pay_to_email;
    private static $secret_word;
    private static $hide_login = 1;
    private static $logo_url;
    private static $recipient_description;
    private static $skrill_url;
    
    private $sid;
    private $_logh = NULL;
    private $_logpath = NULL;
    private $_loglevel = Array('debug', 'info', 'warn', 'error');
    
    const debug_e   = 0;
    const info_e    = 1;
    const warn_e    = 2;
    const error_e   = 3;
    
    const payment_complete_e    = 12;
    const payment_pending_e     = 17;
    
    public function indexAction ()
        {
        switch ($this->getPaymentShortName())
            {
	    case 'skrill_wlt' :
	    case 'skrill_vsa' :
	    case 'skrill_msc' :
	    case 'skrill_vsd' :
	    case 'skrill_vse' :
	    case 'skrill_amx' :
	    case 'skrill_din' :
	    case 'skrill_jcb' :
	    case 'skrill_mae' :
	    case 'skrill_lsr' :
	    case 'skrill_slo' :
	    case 'skrill_gcb' :
	    case 'skrill_sft' :
	    case 'skrill_did' :
	    case 'skrill_gir' :
	    case 'skrill_ent' :
	    case 'skrill_ebt' :
	    case 'skrill_so2' :
	    case 'skrill_npy' :
	    case 'skrill_pli' :
	    case 'skrill_dnk' :
	    case 'skrill_csi' :
	    case 'skrill_psp' :
	    case 'skrill_epy' :
	    case 'skrill_bwi' :
	    case 'skrill_pwy' :
            case 'skrill_pay' :
		if (preg_match('/skrill_(.+)/',$this->getPaymentShortName(), $matches))
		    $payment_methods = strtoupper($matches[1]);
		return $this->redirect(array('action' => 'gateway',
					     'payment' => $payment_methods,
					     'forceSecure' => true));
		break;
	    case 'skrill_acc' :
            case 'skrill' :
		$payment_methods = 'ACC';
		return $this->redirect(array('action' => 'gateway',
					     'payment' => $payment_methods,
					     'forceSecure' => true));
		break;
	    default :
                return $this->redirect(array('controller' => 'checkout'));
            }
	}
        
    public function gatewayAction()
        {
	$config = $this->Config();
        $router = $this->Front()->Router();
	
	mt_srand(time());
	$transaction_id = mt_rand();
        if ($this->Request()->payment == 'PAY')
            $transaction_id = 'PAYOLUTION_INVOICE-' . $transaction_id;
	
	$userinfo = $this->getUser();
	if (!$userinfo) // Redirect to payment failed page
	    $this->forward('cancel');
	
	$uniquePaymentID = $this->createPaymentUniqueId();
	$post_vars = array( // General details
		    'prepare_only'	=> '1',
		    'hide_login'	=> $config->hideLogin,
		    
		    'return_url'	=> $router->assemble(array('action' => 'finish',
								   'forceSecure' => true)) .
					    '?uniquePaymentID=' . $uniquePaymentID .
					    '&transactionID=' . $transaction_id,
		    'status_url'	=> $router->assemble(array('action' => 'status', 'forceSecure' => true)),
		    'cancel_url'	=> $router->assemble(array('action' => 'cancel', 'forceSecure' => true)),
		    
		    // Merchant details
		    'payment_methods'	=> $this->Request()->payment,
		    'pay_to_email'	=> $config->merchantEmail,
		    'recipient_description'
					=> Shopware()->Config()->ShopName,
		    'logo_url'		=> $config->logoUrl,
		    'transaction_id'	=> $transaction_id,
		    'merchant_fields'	=> 'shopware_paymentid',
		    'shopware_paymentid'
					=> $uniquePaymentID,

		    // Customer details
		    'pay_from_email'	=> $userinfo["additional"]["user"]["email"],
		    'firstname'		=> $userinfo["billingaddress"]["firstname"],
		    'lastname'		=> $userinfo["billingaddress"]["lastname"],
		    'address'		=> $userinfo["billingaddress"]["street"] . ' ' .
					    $userinfo["billingaddress"]["streetnumber"],
		    'city'		=> $userinfo["billingaddress"]["city"],
		    'phone_number'	=> $userinfo["billingaddress"]["phone"],
		    'postal_code'	=> $userinfo["billingaddress"]["zipcode"],
		    'country'		=> $userinfo["additional"]["country"]["iso3"],
		    'language' 		=> Shopware()->Locale()->getLanguage(),
		    
		    // Payment details
		    'amount'		=> $this->getAmount(),
		    'currency'		=> $this->getCurrencyShortName()
		    );
        
        //Payolution code
        if ($this->Request()->payment == 'PAY')
            {
            $post_vars['wpf_redirect'] = '1';
            }
            
        if ($config->debug)
            $this->_logMessage(var_export($post_vars, true));
	
	$this->View()->errorStatus = 0;
	if (!$this->_preparePayment($post_vars))
	    {
            $this->forward('fail');
            }
        else
            {
            $this->View()->addTemplateDir(dirname(__FILE__) . '/Views/frontend/payment_skrill/');
            $this->View()->gatewayUrl = $config->skrillUrl . '?sid=' . $this->sid;
            
            if ($this->Request()->payment == 'PAY')
                {
                $this->View()->iframeHeight = 700;
                }
            elseif ($config->hideLogin)
                {
                $this->View()->iframeHeight = 600;
                }
            else
                {
                $this->View()->iframeHeight = 720;
                }

            $this->View()->hideLogin =  $config->hideLogin;
            }
	}
        
     public function failAction ()
	{
        $this->View()->extendsTemplate('frontend/payment_skrill/fail.tpl');
	}

    public function cancelAction ()
	{
	return $this->redirect(array('controller' => 'checkout'));
	}

    public function finishAction ()
	{
	$request = $this->Request();
        $paymentStatus = $this->_getPaymentStatus($request->getParam('transactionID'));
	$orderNumber = $this->saveOrder($request->getParam('transactionID'),
					$request->getParam('uniquePaymentID'),
					$paymentStatus,
					true);
        //$order = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')
        //                            ->findOneBy(array('transactionId' => $request->getParam('transaction_id')));
       // $order->setPaymentStatus($paymentStatus);
        //Shopware()->Models()->persist($order);
        //Shopware()->Models()->flush();
	$this->redirect(array('controller' => 'checkout',
			      'action' => 'finish',
			      'sUniqueID' => $request->getParam('uniquePaymentID')));
	}
	
    public function statusAction ()
	{
	$request = $this->Request();
	$config = $this->Config();

	$status = $request->getParam('status');
	$status_verbose = '';
	$sw_status = 0;
        
        switch ($status)
            {
            case 2 :
                $status_verbose = 'Completed';
                $sw_status = self::payment_complete_e;
                break;
            case 0 :
                $status_verbose = 'Pending';
                $sw_status = self::payment_pending_e;
                break;
            case -1 :
                $status_verbose = 'Cancelled';
                break;
            case -2 :
                $status_verbose = 'Failed';
                break;
            case -3 :
                $status_verbose = 'Chargeback';
                break;
            }

        $md5data = $request->getParam('merchant_id') .
		$request->getParam('transaction_id') .
                strtoupper(md5($config->secretWord)) .
		$request->getParam('mb_amount') .
		$request->getParam('mb_currency') .
                $status;

        $calcmd5 = md5($md5data);
        if (strcmp(strtoupper($calcmd5), $request->getParam('md5sig')))
            {
	    $this->View()->transactionStatus = $status_verbose;
	    $this->forward('cancel');
	    }
	
        if ($config->debug)
            $this->_logMessage('Transaction status for trn: '.
                               $request->getParam('transaction_id') . ": $sw_status");

	if ((int)$status == 2 ||
	    (int)$status == 0)
	    {
	    /*$this->savePaymentStatus($request->getParam('transaction_id'),
				     $request->getParam('shopware_paymentid'),
				     $sw_status,
				     true);*/

            $sql = 'INSERT INTO `s_paymentskrill_status` (`transaction_id` ,
                                                        `shopware_paymentid`,
                                                        `status`,
                                                        `sw_status`)
                    VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE
                    status=values(status), sw_status=values(sw_status)';
            Shopware()->Db()->query($sql, array($request->getParam('transaction_id'),
                                                $request->getParam('shopware_paymentid'),
                                                (int)$status,
                                                (int)$sw_status));
	    }
        
        $this->View()->extendsTemplate('frontend/payment_skrill/status.tpl');
	}
    
    public function Config()
	{
	return Shopware()->Plugins()->Frontend()->PaymentSkrill()->Config();
	}
        
    public function __destruct ()
        {
        if ($this->_logh)
            fclose($this->_logh);
        $this->_logh = NULL;
        }

    private function _logMessage ($message, $level = self::debug_e)
        {
        $this->_logpath = dirname(dirname(dirname(__FILE__))) . '/messages.log';
        
        if (NULL === $this->_logh)
            {
            $sz = @filesize($this->_logpath);
            $this->_logh = @fopen($this->_logpath, "a+");
            if (FALSE === $this->_logh)
                throw new Exception(sprintf('Cannot open logfile %s!', $this->_logpath));
            
            if (!$sz)
                fprintf($this->_logh, "%s - [info] - Logfile started!\n\n",
                        strftime('%d %b %Y %H:%M:%S', time()));
            }
    
        fprintf($this->_logh, "%s - [%s] - %s\n",
                strftime('%d %b %Y %H:%M:%S', time()),
                strtolower($this->_loglevel[$level]),
                $message);
        }
    
    private function _getPaymentStatus ($transactionID)
        {
        $sql = 'SELECT `sw_status` FROM `s_paymentskrill_status` WHERE `transaction_id`=?';
        $sw_status = Shopware()->Db()->fetchOne($sql, array($transactionID));
        
        return $sw_status;
        }

    private function _parseSID ($response)
	{
	$matches = array();
        $rlines = explode("\r\n", $response);

        foreach ($rlines as $line)
            {
            if (preg_match('/([^:]+): (.*)/im', $line, $matches))
                continue;

            if (preg_match('/([0-9a-f]{32})/im', $line, $matches))
                return $matches;
            }

        return $matches;
        }
	
    private function _preparePayment ($params)
	{
	$config = $this->Config();
	$this->sid = false;
	
	$matches = array();
	if (preg_match('/https?\:\/\/([^\:\/]+)+/', $config->skrillUrl, $matches))	
	    $url = $matches[1];
	$content = http_build_query($params);

	$header = "POST /app/payment.pl HTTP/1.1\r\n";
        $header .= "Host: $url\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= "Content-Length: " . strlen($content) . "\r\n\r\n";

    	$fps = fsockopen('ssl://' . $url, 443, $errno, $errstr);
        if (!$fps || !stream_set_blocking($fps, 0))
            return false;
	
	fwrite($fps, $header);
        fwrite($fps, $content);

	//stream_set_timeout($fps, 10);
	$read = array($fps);
	$write = $except = null;
	$msg = $rbuff = '';
	
        if (stream_select($read, $write, $except, 10))
            {
	    $rbuff = fread($fps, 1024);
            $msg .= $rbuff;
            }
        $response = $this->_parseSID($msg);

        if (!count($response))
	    {
	    fclose($fps);
	    return false;
	    }
	fclose($fps);
	
        $this->sid = $response[0];
	
	return true;
	}

    }