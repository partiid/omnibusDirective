<?php
/**
* 2007-2022 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductPresenter;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Sup_omnibus extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'sup_omnibus';
        $this->tab = 'others';
        $this->version = '1.0.0';
        $this->author = 'Marek Łysiak';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Product price history - Omnibus directive');
        $this->description = $this->l('This module displays the lowest price of the product in the last 30 days. Required for Omnibus directive');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module ?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') && 
            $this->registerHook('actionObjectProductUpdateAfter') &&
            $this->registerHook('displayProductAdditionalInfo');
    }

    public function uninstall()
    {
        

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitSup_omnibusModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSup_omnibusModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                   
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            //add js and css 
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->registerStyleSheet(
            'module-sup_omnibus_style',
            'modules/'.$this->name . '/views/css/front.css',
            [
                'media' => 'product',
                'priority' => 200
            ]
            );
    }

    public function hookActionObjectProductUpdateAfter($params)
    {
        //$product = new Product((int)$params->id_product_attribute);
        $product = new Product((int)$params['object']->id); 

        
        $db = $this->getDb(); 
       

        if(Validate::isLoadedObject($product)){
            $product_price_w_tax = trim(str_replace((string)$this->context->currency->symbol, "" ,str_replace(",", ".", Tools::displayPrice($product->getPrice(true, null, 6, null, false, false), $this->context->currency))), " "); 
            $query = "INSERT INTO "._DB_PREFIX_."sup_omnibus (id_product, date_upd, price) VALUES 
            (".$product->id.", now(), '".$product_price_w_tax."')";  
            $db->execute($query);       

        }

    }
    public function hookDisplayProductAdditionalInfo(array $params)
    {
        $db = $this->getDb(); 

        $product = $params['product'];
        

        $query = "SELECT  MIN(price) FROM " . _DB_PREFIX_ . "sup_omnibus WHERE id_product=" .$product->id_product . " AND date_upd BETWEEN  CURRENT_DATE - INTERVAL 30 DAY AND CURRENT_DATE"; 

        $result = $db->getValue($query); 
        $prod = new Product((int)$product->id_product); 
        

        //calculate product tax rate 
        $priceWithTax = Tools::displayPrice($prod->getPrice(true, null, 2, null, false, false), $this->context->currency);
        $priceWithoutTax = Tools::displayPrice($prod->getPrice(false, null, 2, null, false, false), $this->context->currency);


        //$tax = new TaxRule($product['id_tax_rules_group'], $this->context->language->id); 
        //var_dump($tax);
        $tax_value = $this->calculateTaxRate((float)$priceWithTax, (float)$priceWithoutTax); 
        
        if(empty($result)){
            $result = $priceWithTax;
        }
        
        $this->context->smarty->assign(array(
            'min_price' => $result

        ));

        return $this->display(__FILE__, 'price.tpl');
    }
    private function calculateTaxRate($a, $b)
    {
        
        $total = $a; 
        $pre_tax = $b; 
        
        $tax_value = $total - $pre_tax; //wartość podatku 

        return $tax_value; 
        
        
        

        

        
        
    }


    
    private function getDb()
    {
        return Db::getInstance(); 
    }

}
