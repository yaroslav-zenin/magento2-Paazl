<?php
/**
 * Copyright (c) 2017 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Paazl\Shipping\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommitOrderCommand extends Command
{
    /** Order ID */
    const ORDER_ID_ARGUMENT = 'id';

    /** Use real (entity_id) ID */
    const OPTION_REAL_ID = 'real_id';

    /** @var \Paazl\Shipping\Model\Api\RequestBuilder */
    protected $_requestBuilder;

    /** @var \Paazl\Shipping\Model\Api\RequestManager */
    protected $_requestManager;

    /** @var \Magento\Sales\Model\OrderFactory */
    protected $_orderFactory;

    /** @var \Magento\Sales\Model\ResourceModel\Order */
    protected $_orderResource;

    /** @var \Paazl\Shipping\Helper\Utility\Address */
    protected $_addressHelper;

    /**
     * @var \Paazl\Shipping\Model\PaazlManagement
     */
    protected $paazlManagement;

    /**
     * CommitOrderCommand constructor.
     * @param \Paazl\Shipping\Model\Api\RequestBuilder $requestBuilder
     * @param \Paazl\Shipping\Model\Api\RequestManager $requestManager
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Sales\Model\ResourceModel\Order $orderResource
     * @param \Paazl\Shipping\Helper\Utility\Address $addressHelper
     * @param \Paazl\Shipping\Model\PaazlManagement $paazlManagement
     * @param null $name
     */
    public function __construct(
        \Paazl\Shipping\Model\Api\RequestBuilder $requestBuilder,
        \Paazl\Shipping\Model\Api\RequestManager $requestManager,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\ResourceModel\Order $orderResource,
        \Paazl\Shipping\Helper\Utility\Address $addressHelper,
        \Paazl\Shipping\Model\PaazlManagement $paazlManagement,
        $name = null
    ) {
        $this->_requestBuilder = $requestBuilder;
        $this->_requestManager = $requestManager;
        $this->_orderFactory = $orderFactory;
        $this->_orderResource = $orderResource;
        $this->_addressHelper = $addressHelper;
        $this->paazlManagement = $paazlManagement;
        parent::__construct($name);
    }
    /**
     * Configures the current command.
     */
    public function configure()
    {
        $this->setName('paazl:order:commit');
        $this->setDescription(__('Call commitOrder on an order'))
            ->setDefinition([
                new InputArgument(
                    self::ORDER_ID_ARGUMENT,
                    InputArgument::REQUIRED,
                    'Order id (default is increment_id)'
                ),
                new InputOption(
                    self::OPTION_REAL_ID,
                    'r',
                    InputOption::VALUE_NONE,
                    'Use entity_id instead of increment_id'
                ),
            ]);
    }

    /**
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return null|int null or 0 if everything went fine, or an error code
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>CommitOrderCommand</info>');

        $orderId = $input->getArgument(self::ORDER_ID_ARGUMENT);
        $realId = ($input->getOption(self::OPTION_REAL_ID));

        $response = $this->commitOrder($orderId, $realId);
        $output->writeln('<info>' . print_r($response, true) . '</info>');

        $result = 0;
        return $result;
    }

    /**
     * @param $orderId
     * @param bool $realId
     * @return mixed
     */
    protected function commitOrder($orderId, $realId = false)
    {
        if ($realId) {
            $order = $this->_orderFactory->create()->load($orderId);
        } else {
            $order = $this->_orderFactory->create()->loadByIncrementId($orderId);
        }

        if (!$order->getEntityId()) {
            return 'Order not found';
        }

        $shippingMethod = $order->getShippingMethod(true);
        $shippingAddress = $order->getShippingAddress();

        $requestData = [
            'context' => $this->paazlManagement->getReferencePrefix() . $order->getQuoteId(),
            'body' => [
                'orderReference' => $this->paazlManagement->getReferencePrefix() . $order->getIncrementId(), // Final reference
                'pendingOrderReference' => $this->paazlManagement->getReferencePrefix() . $order->getQuoteId(), // Temporary reference
                'totalAmount' => $order->getBaseSubtotalInclTax() * 100, // In cents
                'customerEmail' => $order->getCustomerEmail(),
                'customerPhoneNumber' => $shippingAddress->getTelephone(),
                'shippingMethod' => [
                    'type' => 'delivery', //@todo Service points
                    'identifier' => null, //@todo Service points
                    'option' => $shippingMethod->getMethod(),
                    'orderWeight' => $this->paazlManagement->getConvertedWeight($order->getWeight()),
                    'maxLabels' => 1, //@todo Support for shipments having multiple packages
                    'description' => 'Delivery' //@todo Find out what description is expected
                ],
                'shippingAddress' => [
                    'customerName' => $shippingAddress->getName(),
                    'street' => $shippingAddress->getStreetLine(1),
                    'housenumber' => $shippingAddress->getStreetLine(2),
                    'addition' => $shippingAddress->getStreetLine(3),
                    'zipcode' => $shippingAddress->getPostcode(),
                    'city' => $shippingAddress->getCity(),
                    'province' => (strlen((string)$shippingAddress->getRegionCode()) < 3)
                        ? $shippingAddress->getRegionCode()
                        : null,
                    'country' => $shippingAddress->getCountryId()
                ]
            ]
        ];

        $orderCommitRequest = $this->_requestBuilder->build('PaazlOrderCommitRequest', $requestData);

        $response = $this->_requestManager->doRequest($orderCommitRequest)->getResponse();
        if (isset($response['success'])) {
            $order->setExtOrderId($order->getIncrementId());
            $this->_orderResource->save($order);
        }

        return $response;
    }
}
