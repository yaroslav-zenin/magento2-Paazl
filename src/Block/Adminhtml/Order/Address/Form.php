<?php
/**
 * Copyright (c) 2017 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Paazl\Shipping\Block\Adminhtml\Order\Address;

use Magento\Backend\Model\Session\Quote;
use Magento\Directory\Model\CountryHandlerInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\ScopeInterface;

class Form extends \Magento\Sales\Block\Adminhtml\Order\Address\Form
{
    /**
     * @var \Magento\Directory\Model\ResourceModel\Country\Collection
     */
    private $countriesCollection;

    /**
     * @var \Magento\Backend\Model\Session\Quote
     */
    private $backendQuoteSession;

    /**
     * Retrieve module name of block
     *
     * @return string
     */
    public function getModuleName()
    {
        return 'Magento_Sales';
    }

    /**
     * Define form attributes (id, method, action)
     *
     * @return $this
     */
    protected function _prepareForm()
    {
        $this->prepareForm();
        $this->_form->setId('edit_form');
        $this->_form->setMethod('post');
        $this->_form->setAction(
            $this->getUrl('sales/*/addressSave', ['address_id' => $this->_getAddress()->getId()])
        );
        $this->_form->setUseContainer(true);
        return $this;
    }

    /**
     * Prepare Form and add elements to form
     *
     * @return $this
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function prepareForm()
    {
        $fieldset = $this->_form->addFieldset('main', ['no_container' => true]);

        $addressForm = $this->_customerFormFactory->create('customer_address', 'adminhtml_customer_address');
        $attributes = $addressForm->getAttributes();
        // Remove old street from Admin -> order view -> edit address
        unset($attributes['street']);
        $this->_addAttributesToForm($attributes, $fieldset);

        $prefixElement = $this->_form->getElement('prefix');
        if ($prefixElement) {
            $prefixOptions = $this->options->getNamePrefixOptions($this->getStore());
            if (!empty($prefixOptions)) {
                $fieldset->removeField($prefixElement->getId());
                $prefixField = $fieldset->addField($prefixElement->getId(), 'select', $prefixElement->getData(), '^');
                $prefixField->setValues($prefixOptions);
                if ($this->getAddressId()) {
                    $prefixField->addElementValues($this->getAddress()->getPrefix());
                }
            }
        }

        $suffixElement = $this->_form->getElement('suffix');
        if ($suffixElement) {
            $suffixOptions = $this->options->getNameSuffixOptions($this->getStore());
            if (!empty($suffixOptions)) {
                $fieldset->removeField($suffixElement->getId());
                $suffixField = $fieldset->addField(
                    $suffixElement->getId(),
                    'select',
                    $suffixElement->getData(),
                    $this->_form->getElement('lastname')->getId()
                );
                $suffixField->setValues($suffixOptions);
                if ($this->getAddressId()) {
                    $suffixField->addElementValues($this->getAddress()->getSuffix());
                }
            }
        }

        $regionElement = $this->_form->getElement('region_id');
        if ($regionElement) {
            $regionElement->setNoDisplay(true);
        }

        $this->_form->setValues($this->getFormValues());

        if ($this->_form->getElement('country_id')->getValue()) {
            $countryId = $this->_form->getElement('country_id')->getValue();
            $this->_form->getElement('country_id')->setValue(null);
            foreach ($this->_form->getElement('country_id')->getValues() as $country) {
                if ($country['value'] == $countryId) {
                    $this->_form->getElement('country_id')->setValue($countryId);
                }
            }
        }
        if ($this->_form->getElement('country_id')->getValue() === null) {
            $this->_form->getElement('country_id')->setValue(
                $this->directoryHelper->getDefaultCountry($this->getStore())
            );
        }

        $this->processCountryOptions($this->_form->getElement('country_id'));
        // Set custom renderer for VAT field if needed
        $vatIdElement = $this->_form->getElement('vat_id');
        if ($vatIdElement && $this->getDisplayVatValidationButton() !== false) {
            $vatIdElement->setRenderer(
                $this->getLayout()->createBlock(
                    'Magento\Customer\Block\Adminhtml\Sales\Order\Address\Form\Renderer\Vat'
                )->setJsVariablePrefix(
                    $this->getJsVariablePrefix()
                )
            );
        }

        return $this;
    }

    /**
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $countryElement
     * @return void
     */
    protected function processCountryOptions(\Magento\Framework\Data\Form\Element\AbstractElement $countryElement)
    {
        $storeId = $this->getBackendQuoteSession()->getStoreId();
        $options = $this->getCountriesCollection()
            ->loadByStore($storeId)
            ->toOptionArray();

        $countryElement->setValues($options);
    }

    /**
     * Retrieve Directiry Countries collection
     * @deprecated
     * @return \Magento\Directory\Model\ResourceModel\Country\Collection
     */
    private function getCountriesCollection()
    {
        if (!$this->countriesCollection) {
            $this->countriesCollection = ObjectManager::getInstance()
                ->get(\Magento\Directory\Model\ResourceModel\Country\Collection::class);
        }

        return $this->countriesCollection;
    }

    /**
     * Retrieve Backend Quote Session
     * @deprecated
     * @return Quote
     */
    private function getBackendQuoteSession()
    {
        if (!$this->backendQuoteSession) {
            $this->backendQuoteSession = ObjectManager::getInstance()->get(Quote::class);
        }

        return $this->backendQuoteSession;
    }
}