<?php

namespace JiaLeo\Payment\Wechatpay;

use JiaLeo\Payment\Common\PaymentException;


class Refund extends BaseWechatpay
{
    public $payUrl = '/secapi/pay/refund';
    public $rawData = array();

    /**
     * 回调
     * @return array
     * @throws PaymentException
     */
    public function handle($params)
    {

        $this->checkParams($params);
        $data = array(
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mchid'],
            'nonce_str' => $this->getNonceStr(),
            'out_refund_no' => $params['out_refund_no'],
            'total_fee' => $params['total_fee'],
            'refund_fee' => $params['refund_fee'],
            'refund_account' => isset($params['refund_account']) ? $params['refund_account'] : 'REFUND_SOURCE_RECHARGE_FUNDS',//余额退款
        );

        if (isset($params['out_trade_no'])) {
            $data['out_trade_no'] = $params['out_trade_no'];
        } else {
            $data['transaction_id'] = $params['transaction_id'];
        }

        //签名
        $data['sign'] = $this->makeSign($data);
        $xml = $this->toXml($data);
        $url = $this->gateway . $this->payUrl;

        $res = $this->postXmlCurl($url, $xml, $this->config['sslcert_path'], $this->config['sslkey_path']);
        $get_result = $this->fromXml($res);

        if (!isset($get_result['return_code']) || $get_result['return_code'] != 'SUCCESS') {
            throw new PaymentException('退款失败!接口返回错误信息:' . (isset($get_result['return_msg']) ? $get_result['return_msg'] : ''));
        }

        if (!isset($get_result['result_code']) || $get_result['result_code'] != 'SUCCESS') {
            throw new PaymentException('退款失败!接口返回错误信息:' . (isset($get_result['err_code_des']) ? $get_result['err_code_des'] : ''));
        }

        //验证签名
        $result_sign = $this->makeSign($get_result);
        if ($result_sign != $get_result['sign']) {
            throw new PaymentException('返回数据签名失败!');
        }

        return $get_result;
    }

    /**
     * 验证参数
     * @param $params
     * @return bool
     * @throws PaymentException
     */
    public function checkParams($params)
    {
        //验证
        if (empty($params['out_trade_no']) && empty($params['transaction_id'])) {
            throw new PaymentException('out_trade_no字段或transaction_id字段中的一个不能为空!');
        }

        if (isset($params['out_trade_no']) && mb_strlen($params['out_trade_no']) > 32) {
            throw new PaymentException('out_trade_no字段不能大于32位!');
        }

        if (isset($params['transaction_id']) && mb_strlen($params['transaction_id']) > 28) {
            throw new PaymentException('微信订单号,transaction_id字段不能大于28位!');
        }

        if (empty($params['out_refund_no']) || mb_strlen($params['out_refund_no']) > 64) {
            throw new PaymentException('商户退款单号(out_refund_no)不能为空,且不能大于64位!');
        }

        if (empty($params['total_fee']) || mb_strlen($params['total_fee']) > 100) {
            throw new PaymentException('订单金额(total_fee)不能为空,且不能大于100位!');
        }

        if (empty($params['refund_fee']) || mb_strlen($params['refund_fee']) > 100) {
            throw new PaymentException('退款金额(refund_fee)不能为空,且不能大于100位!');
        }

        if (isset($params['refund_desc']) && mb_strlen($params['refund_desc']) > 80) {
            throw new PaymentException('退款原因(refund_desc)能大于80位!');
        }

        return true;
    }


}