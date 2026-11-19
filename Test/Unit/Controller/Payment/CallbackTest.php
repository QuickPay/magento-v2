<?php

namespace QuickPay\Gateway\Test\Unit\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use QuickPay\Gateway\Controller\Payment\Callback;
use QuickPay\Gateway\Helper\Order as OrderHelper;
use QuickPay\Gateway\Model\Adapter\QuickPayAdapter;
use QuickPay\Gateway\Model\Ui\ConfigProvider;

class CallbackTest extends TestCase
{
    private const PRIVATE_KEY = 'private-key';
    private const QUICKPAY_PAYMENT_ID = 123456;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var string|null
     */
    private $requestedCaptureCase;

    /**
     * @var array|null
     */
    private $preparedInvoice;

    /**
     * @var int|null
     */
    private $invoiceTransactionId;

    /**
     * @var bool
     */
    private $invoiceSaved = false;

    public function testTrustlyAutocaptureRecordsInvoiceWithoutCapturingAgain(): void
    {
        $callback = $this->createCallback(
            ConfigProvider::CODE_TRUSTLY,
            'trustly',
            ['payment/quickpay_trustly/autocapture' => '1'],
            [(object)['type' => 'authorize']]
        );

        $callback->execute();

        $this->assertSame(Invoice::CAPTURE_OFFLINE, $this->requestedCaptureCase);
        $this->assertSame(self::QUICKPAY_PAYMENT_ID, $this->invoiceTransactionId);
        $this->assertTrue($this->invoiceSaved);
        $this->assertInvoiceCoversWholeOrder();
    }

    public function testTrustlyRecordsInvoiceWithoutCapturingAgainWhenGatewayAutocaptureIsEnabled(): void
    {
        $callback = $this->createCallback(
            ConfigProvider::CODE_TRUSTLY,
            'trustly',
            ['payment/quickpay_gateway/autocapture' => '1'],
            [(object)['type' => 'authorize'], (object)['type' => 'capture']]
        );

        $callback->execute();

        $this->assertSame(Invoice::CAPTURE_OFFLINE, $this->requestedCaptureCase);
        $this->assertSame(self::QUICKPAY_PAYMENT_ID, $this->invoiceTransactionId);
        $this->assertTrue($this->invoiceSaved);
        $this->assertInvoiceCoversWholeOrder();
    }

    public function testCardAutocaptureStillCapturesOnline(): void
    {
        $callback = $this->createCallback(
            ConfigProvider::CODE,
            'clearhaus',
            ['payment/quickpay_gateway/autocapture' => '1'],
            [(object)['type' => 'authorize']]
        );

        $callback->execute();

        $this->assertSame(Invoice::CAPTURE_ONLINE, $this->requestedCaptureCase);
        $this->assertNull($this->invoiceTransactionId);
        $this->assertTrue($this->invoiceSaved);
        $this->assertInvoiceCoversWholeOrder();
    }

    private function assertInvoiceCoversWholeOrder(): void
    {
        $this->assertSame([true, []], $this->preparedInvoice);
    }

    /**
     * @param string $methodCode
     * @param string $acquirer
     * @param array $config
     * @param array $operations
     * @return Callback
     */
    private function createCallback(
        string $methodCode,
        string $acquirer,
        array $config,
        array $operations
    ): Callback
    {
        $config[Callback::PRIVATE_KEY_XML_PATH] = self::PRIVATE_KEY;
        $body = json_encode([
            'id' => self::QUICKPAY_PAYMENT_ID,
            'order_id' => '000000042',
            'accepted' => true,
            'test_mode' => false,
            'acquirer' => $acquirer,
            'currency' => 'DKK',
            'operations' => $operations,
        ]);

        $request = $this->createStub(HttpRequest::class);
        $request->method('getContent')->willReturn($body);
        $request->method('getServer')->willReturnMap([
            ['HTTP_QUICKPAY_CHECKSUM_SHA256', null, hash_hmac('sha256', $body, self::PRIVATE_KEY)],
        ]);

        $methodInstance = $this->createStub(MethodInterface::class);
        $methodInstance->method('getCode')->willReturn($methodCode);

        $payment = $this->createStub(Payment::class);
        $payment->method('getMethodInstance')->willReturn($methodInstance);
        $payment->method('getLastTransId')->willReturn(null);

        $orderConfig = $this->createStub(Order\Config::class);
        $orderConfig->method('getStateDefaultStatus')->willReturn('processing');

        $order = $this->createStub(Order::class);
        $order->method('loadByIncrementId')->willReturnSelf();
        $order->method('getId')->willReturn(42);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getState')->willReturn(Order::STATE_NEW);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getConfig')->willReturn($orderConfig);
        $order->method('setState')->willReturnSelf();
        $order->method('setStatus')->willReturnSelf();
        $order->method('getEmailSent')->willReturn(true);

        $invoice = $this->createStub(Invoice::class);
        $invoice->method('getOrder')->willReturn($order);
        $invoice->method('setTransactionId')->willReturnCallback(
            function ($transactionId) use ($invoice) {
                $this->invoiceTransactionId = $transactionId;
                return $invoice;
            }
        );
        $invoice->method('__call')->willReturnCallback(
            function ($method, $args) use ($invoice) {
                if ($method === 'setRequestedCaptureCase') {
                    $this->requestedCaptureCase = $args[0];
                }
                return $invoice;
            }
        );

        $invoiceService = $this->createStub(InvoiceService::class);
        $invoiceService->method('prepareInvoice')->willReturnCallback(
            function ($preparedOrder, $qtys = []) use ($invoice, $order) {
                $this->preparedInvoice = [$preparedOrder === $order, $qtys];
                return $invoice;
            }
        );

        $transaction = $this->createStub(Transaction::class);
        $transaction->method('addObject')->willReturnSelf();
        $transaction->method('save')->willReturnCallback(function () use ($transaction) {
            $this->invoiceSaved = true;
            return $transaction;
        });

        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('create')->willReturnMap([[Transaction::class, [], $transaction]]);

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResponse')->willReturn($this->createStub(HttpResponse::class));
        $context->method('getObjectManager')->willReturn($objectManager);

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function ($path) use ($config) {
                return $config[$path] ?? null;
            }
        );
        $scopeConfig->method('isSetFlag')->willReturn(true);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->never())->method('critical');

        return new Callback(
            $context,
            $this->logger,
            $scopeConfig,
            $order,
            $this->createStub(OrderSender::class),
            $this->createStub(QuickPayAdapter::class),
            $invoiceService,
            $this->createStub(TransactionFactory::class),
            $this->createStub(InvoiceSender::class),
            $this->createStub(DirectoryList::class),
            $this->createStub(OrderHelper::class)
        );
    }
}
