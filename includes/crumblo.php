<?php

/**
 *
 */
class Crumblo
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $version;

    /**
     * @param string $name
     * @param string $version
     */
    public function __construct($name, $version)
    {
        $this->name = $name;
        $this->version = $version;
    }

    /**
     * @return void
     */
    public function run()
    {
        add_filter('woocommerce_payment_gateways', [$this, 'addGatewayClass']);
    }

    /**
     * @param array $methods
     * @return array
     */
    public function addGatewayClass($methods)
    {
        if ($this->loadGateway()) {
            $methods[] = 'CrumbloGateway';
        }
        return $methods;
    }

    /**
     * @return bool
     */
    private function loadGateway()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return false;
        }
        require_once plugin_dir_path(__DIR__) . 'includes/crumblo-gateway.php';
        return true;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }
}
