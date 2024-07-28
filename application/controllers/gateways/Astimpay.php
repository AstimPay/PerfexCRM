<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Astimpay extends App_Controller
{
    /**
     * Show message to the customer whether the payment is successfully
     *
     * @return mixed
     */
    public function verify_payment()
    {
        $invoice_id = $this->input->get('invoice_id');

        $invoiceid = $this->input->get('invoiceid');
        $hash = $this->input->get('hash');
        check_invoice_restrictions($invoiceid, $hash);

        $this->db->where('id', $invoiceid);
        $invoice = $this->db->get(db_prefix() . 'invoices')->row();

        try {
            $response = $this->astimpay_gateway->fetch_payment($invoice_id);

            if ($response['status'] === 'COMPLETED') {
                // New payment
                $this->astimpay_gateway->addPayment([
                    'amount'        => $invoice->total,
                    'invoiceid'     => $invoice->id,
                    'paymentmethod' => $response['payment_method'],
                    'transactionid' => $response['transaction_id'],
                ]);
                set_alert('success', _l('online_payment_recorded_success'));
            } else {
                set_alert('danger', 'Payment is pending for verification.');
            }
        } catch (\Exception $e) {
            set_alert('danger', $e->getMessage());
        }

        redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
    }

    /**
     * Handle the astimpay webhook
     *
     * @param  string $key
     *
     * @return mixed
     */
    public function webhook($key = null)
    {

        $response = $this->astimpay_gateway->fetch_payment();

        log_activity('AstimPay payment webhook called.');

        if (!$response) {
            log_activity('AstimPay payment not found via webhook.');

            return;
        }

        if ($response['metadata']['webhook_key'] !== $key) {
            log_activity('AstimPay payment webhook key does not match. Url Key: "' . $key . '", Metadata Key: "' . $response['metadata']['webhook_key'] . '"');

            return;
        }

        if ($response['status'] == 'COMPLETED') {
            $this->db->where('id', $response['metadata']['invoice_id']);
            $invoice = $this->db->get(db_prefix() . 'invoices')->row();
            // New payment
            $this->astimpay_gateway->addPayment([
                'amount'        => $invoice->total,
                'invoiceid'     => $invoice->id,
                'paymentmethod' => $response['payment_method'],
                'transactionid' => $response['transaction_id'],
            ]);
        } else {
            log_activity('AstimPay payment failed. Status: ' . $response['status']);
        }
    }
}
