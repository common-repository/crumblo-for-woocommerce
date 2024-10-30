<?php

/**
 *
 */
class CrumbloGateway extends WC_Payment_Gateway
{
    const ID = 'crumblo';

    const OPENAPI_URL = 'https://openapi.forpeeps.eu';

    /**
     *
     */
    public function __construct()
    {
        $this->id = self::ID;
        $this->icon = plugins_url('public/img/powered-by.svg', __DIR__);
        $this->exclamation_icon = plugins_url('public/img/exclamation.svg', __DIR__);
        $this->has_fields = true;
        $this->method_title = 'Crumblo';
        $this->method_description = 'Online payments for WooCommerce powered by Crumblo';

        $this->supports = [
            'products'
        ];

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->api_key = $this->get_option('api_key');

        if (is_admin()) {
            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                [$this, 'process_admin_options']
            );
        }

        add_filter('woocommerce_available_payment_gateways', [$this, 'available_payment_gateways']);

        add_action('woocommerce_api_forpeeps_webhook', [$this, 'webhook']);

        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
    }

    /**
     *
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Enable/Disable',
                'label' => 'Enable Crumblo',
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no'
            ],
            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'default' => 'Bank account',
            ],
            'api_key' => [
                'title' => 'API key',
                'type' => 'text'
            ],
        ];
    }

    public function admin_options() {
        parent::admin_options();

        if (empty($this->getSecretToken())) {
            echo '
                <table class="form-table">
                    <tr>
                        <th></th>
                        <td>
                            <div style="color: red">Bank payments do not work without a valid API key!</div>
                            <div>
                                You need to subscribe 
                                <a style="color: #8155F4; text-decoration: none" href="https://selfservice.forpeeps.eu">here</a> 
                                to obtain it.
                            </div>
                        </td>
                    </tr>
                </table>
            ';
        }
    }

    /**
     * @inheritDoc
     */
    public function is_available()
    {
        if ($this->enabled === 'no') {
            return false;
        }
        return parent::is_available();
    }

    /**
     * @param array $available_gateways
     * @return array
     */
    public function available_payment_gateways($available_gateways)
    {
        if (is_checkout()
            && is_wc_endpoint_url('order-pay')
            && isset($available_gateways[self::ID])
        ) {
            $available_gateways = [self::ID => $available_gateways[self::ID]];
        }
        return $available_gateways;
    }

    /**
     *
     */
    public function payment_fields()
    {
        $banks = $this->fetchBankList('EE');

        if (!is_array($banks)) {
            echo '
                <div class="crumblo__payment-form__container">
                  <div class="crumblo__payment-form__warning-message">
                    <img src="'.esc_url($this->exclamation_icon).'" class="crumblo__payment-form__warning-message-icon" alt="" />
                    <div class="crumblo__payment-form__warning-message-title">Unknown error has occurred</div>
                    <div class="crumblo__payment-form__warning-message-text">
                      <div>We cannot get the bank list.</div>
                      <div>Please contact <a href="mailto:support@forpeeps.eu">support</a></div>
                    </div>
                  </div>
                </div>
            ';
            return;
        }

        $bankIcons = [];

        foreach ($banks as $bank) {
            $bankIcons[] = '
                <input 
                    type="radio"
                    name="forpeeps_method" 
                    value="'.esc_attr($bank['bic']).'" 
                    id="crumblo-bank-'.esc_attr($bank['bic']).'"
                    class="crumblo__payment-form__bank-radio"
                >
                <label for="crumblo-bank-'.esc_attr($bank['bic']).'" 
                    class="crumblo__payment-form__bank-label"
                >
                    <span 
                        class="crumblo__payment-form__bank-icon crumblo__payment-form__bank-icon--active"
                        style="background-image: url('.esc_url($bank['image_active']).') !important" 
                    ></span>
                    <span 
                        class="crumblo__payment-form__bank-icon crumblo__payment-form__bank-icon--inverted" 
                        style="background-image: url('.esc_url($bank['image_inactive']).') !important" 
                    ></span>
                    <img
                        src="'.esc_url($bank['image_inactive']).'" 
                        class="crumblo__payment-form__bank-icon-preload" 
                        alt=""
                    />
                </label>
            ';
        }

        $bankIconsString = implode('', $bankIcons);

        $form = '
            <div class="crumblo__payment-form__container">
              <div class="crumblo__payment-form__row crumblo__payment-form__row--country">
                <label class="crumblo__payment-form__row-label">
                  <span>Select Country</span>
                  <span class="crumblo__payment-form__row-label-required">*</span>
                </label>
                <select name="crumblo_country" id="crumblo_country">
                  <option value="EE">Estonia</option>
                </select>
              </div>
              <div class="crumblo__payment-form__row crumblo__payment-form__row--bank">
                <label class="crumblo__payment-form__row-label">
                  <span>Select Bank</span>
                  <span class="crumblo__payment-form__row-label-required">*</span>
                </label>
                <div class="crumblo__payment-form__bank-list">
                  '.$bankIconsString.'
                </div>
        ';

        if (empty($this->getSecretToken()) && $this->is_active_admin()) {
            $form .= '
                <div class="crumblo__payment-form__no-api-key-block">
                    Bank payments do not work without a valid API key!<br>
                    You need to subscribe <a href="https://selfservice.forpeeps.eu">here</a> to obtain it.
                </div>
            ';
        }

        $form .= '</div></div>';

        echo '
            <div class="crumblo-wc__payment-method__container">
                '.$form.'
            </div>
        ';
    }

    /**
     * @param string $country
     * @return array|mixed
     */
    private function fetchBankList($country)
    {
        $banksResponse = $this->makeForpeepsOpenApiRequest(
            "/v1/banks?active=1&country_code=$country",
            ['timeout' => 5]
        );
        if (is_wp_error($banksResponse) || !is_array($banksResponse)) {
            return null;
        }
        return is_array(isset($banksResponse['banks']) ? $banksResponse['banks'] : null)
            ? $banksResponse['banks']
            : null;
    }

    /**
     * @return array|mixed
     */
    private function fetchCountryList()
    {
        $countriesResponse = $this->makeForpeepsOpenApiRequest(
            "/v1/banks/countries",
            ['timeout' => 5]
        );
        if (is_wp_error($countriesResponse) || !is_array($countriesResponse)) {
            return null;
        }
        return is_array(isset($countriesResponse['countries']) ? $countriesResponse['countries'] : null)
            ? $countriesResponse['countries']
            : null;
    }

    /**
     * @param mixed $order_id
     * @return array|void
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order instanceof WC_Order) {
            return;
        }

        if (!$order->has_status(['pending', 'on-hold', 'failed'])) {
            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        }

        $forpeeps_method = isset($_POST['forpeeps_method']) ? sanitize_text_field($_POST['forpeeps_method']) : null;

        if (!$forpeeps_method) {
            wc_add_notice('Please select bank for payment.', 'error');
            return;
        }

        $order->update_meta_data(
            '_forpeeps_payment_method',
            'bank'
        );
        $order->save();

        return $this->initiate_bank_payment($order, $forpeeps_method);
    }

    /**
     * @param WC_Order $order
     * @param string $bic
     * @return array|void
     */
    private function initiate_bank_payment($order, $bic)
    {
        if (empty($this->getSecretToken()) && $this->is_active_admin()) {
            wc_add_notice('Bank account payments do not work at the moment.<br>You need to purchase a valid API key!', 'error');
            return;
        }

        $body = $this->makeForpeepsOpenApiRequest('/v1/payment', [
            'method' => 'POST',
            'body' => [
                'bic' => $bic,
                'amount' => $order->get_total(),
                'currency' => $order->get_currency(),
                'external_data' => $order->get_id(),
                'message' => 'Order #'.$order->get_id(),
                'success_redirect_url' => $this->get_return_url($order),
                'fail_redirect_url' => wc_get_checkout_url(),
                'cancel_redirect_url' => wc_get_checkout_url(),
                'webhook_url' => add_query_arg('wc-api', 'forpeeps_webhook', home_url('/')),
            ],
        ]);

        if (isset($body['link'])) {
            return [
                'result' => 'success',
                'redirect' => $body['link'],
            ];
        }

        if (isset($body['reason'])) {
            if ($this->is_active_admin() && $body['reason'] === 'E_UNAUTHORIZED') {
                wc_add_notice('Please check you secret token in plugin settings.', 'error');
                return;
            }
        }

        wc_add_notice('Bank account payments do not work at the moment.<br>Please contact the shop owner or use another payment method.', 'error');
    }

    /**
     *
     */
    public function webhook()
    {
        global $woocommerce;

        if (empty($_POST['data'])) {
            status_header(400);
            echo 'ERR_EMPTY_ORDER_ID';
            die;
        }

        $orderId = json_decode($_POST['data'], true);

        $order = wc_get_order($orderId);

        if (!$order instanceof WC_Abstract_Order) {
            status_header(404);
            echo 'ERR_NO_ORDER';
            die;
        }

        if (!$order->has_status(['pending', 'on-hold', 'failed'])) {
            status_header(200);
            echo 'OK';
            die;
        }

        if (!$order->payment_complete()) {
            status_header(500);
            echo 'ERR_PROCESSING';
            die;
        }

        try {
            $woocommerce->cart->empty_cart();
        } catch (Exception $e) {}

        status_header(200);
        echo 'OK';
        die;
    }

    /**
     *
     */
    public function payment_scripts()
    {
        if ('no' === $this->enabled) {
            return;
        }

        if (!is_cart() && !is_checkout()) {
            return;
        }

        // Checkout /  Place Order
        wp_register_style(
            'woocommerce_crumblo_checkout',
            plugins_url('public/css/crumblo-checkout.css', __DIR__),
            [],
            uniqid()
        );
        wp_enqueue_style('woocommerce_crumblo_checkout');
    }

    /**
     * @return string
     */
    private function getSecretToken()
    {
        return $this->api_key;
    }

    /**
     * @return string
     */
    private function getOpenApiUrl()
    {
        return self::OPENAPI_URL;
    }

    /**
     * @param string $url
     * @param array $args
     * @return array|WP_Error
     */
    private function makeRequest($url, $args = [])
    {
        return wp_remote_request($url, array_merge([
            'method' => 'GET',
            'timeout' => 30,
            'blocking' => true,
        ], $args));
    }

    /**
     * @param string $url
     * @param array $args
     * @return array|WP_Error|null
     */
    private function makeRequestParseJson($url, $args = [])
    {
        $response = $this->makeRequest($url, $args);
        return is_wp_error($response)
            ? $response
            : json_decode($response['body'], true);
    }

    /**
     * @param string $path
     * @param array $args
     * @return array|WP_Error|null
     */
    private function makeForpeepsOpenApiRequest($path, $args = [])
    {
        return $this->makeRequestParseJson(
            rtrim($this->getOpenApiUrl(), '/').'/'.ltrim($path, '/'),
            array_merge([
                'headers' => [
                    'Authorization' => 'Bearer '.$this->getSecretToken(),
                ],
            ], $args)
        );
    }

    private function is_active_admin() {
        return current_user_can('administrator');
    }
}
