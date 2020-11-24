<?php

declare(strict_types=1);

namespace Tests\Jorijn\Bitcoin\Dca\Service\Bitvavo;

use Jorijn\Bitcoin\Dca\Client\BitvavoClientInterface;
use Jorijn\Bitcoin\Dca\Exception\PendingBuyOrderException;
use Jorijn\Bitcoin\Dca\Service\Bitvavo\BitvavoBuyService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Jorijn\Bitcoin\Dca\Service\Bitvavo\BitvavoBuyService
 * @covers ::__construct
 *
 * @internal
 */
final class BitvavoBuyServiceTest extends TestCase
{
    private const DATA = 'data';
    private const FILLED_SATOSHIS = 'filledSatoshis';
    private const FILLED_QUOTE = 'filledQuote';
    private const FEE_PAID = 'feePaid';
    private const FEE_CURRENCY = 'feeCurrency';
    private const PRICE = 'price';
    private const MARKET = 'market';
    private const ORDER_ID = 'orderId';
    private const API_CALL = 'apiCall';
    private const ORDER = 'order';
    private const AMOUNT_QUOTE = 'amountQuote';
    private const DIVISOR = '100000000';

    /** @var BitvavoClientInterface|MockObject */
    private $client;
    private string $baseCurrency;
    private BitvavoBuyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(BitvavoClientInterface::class);
        $this->baseCurrency = (string) random_int(111, 999);
        $this->service = new BitvavoBuyService($this->client, $this->baseCurrency);
    }

    /**
     * @covers ::getAveragePrice
     * @covers ::getCompletedBuyOrderFromResponse
     * @covers ::initiateBuy
     */
    public function testInitiateBuySucceedsDirectly(): void
    {
        $amount = random_int(10, 20);
        $asset = "BTC";

        [
            self::DATA => $data,
            self::FILLED_SATOSHIS => $filledSatoshis,
            self::FILLED_QUOTE => $filledQuote,
            self::FEE_PAID => $feePaid,
            self::FEE_CURRENCY => $feeCurrency,
            self::PRICE => $price,
        ] = $this->getSimpleResponseStructure();

        $this->client
            ->expects(static::once())
            ->method(self::API_CALL)
            ->with(self::ORDER, 'POST', [], [
                self::MARKET => sprintf('BTC-'.$this->baseCurrency),
                'side' => 'buy',
                'orderType' => self::MARKET,
                self::AMOUNT_QUOTE => (string) $amount,
            ])
            ->willReturn($data)
        ;

        $responseDTO = $this->service->initiateBuy($amount, $asset);

        static::assertSame($filledSatoshis, $responseDTO->getAmountInSatoshis());
        static::assertSame($filledQuote.' '.$this->baseCurrency, $responseDTO->getDisplayAmountSpent());
        static::assertSame(0, $responseDTO->getFeesInSatoshis());
        static::assertSame($feePaid.' '.$feeCurrency, $responseDTO->getDisplayFeesSpent());
        static::assertSame($price.' '.$this->baseCurrency, $responseDTO->getDisplayAveragePrice());
    }

    /**
     * @covers ::initiateBuy
     */
    public function testInitiateBuyButIsStillPending(): void
    {
        $orderId = 'oid'.random_int(1000, 2000);

        $this->client
            ->expects(static::once())
            ->method(self::API_CALL)
            ->with(self::ORDER, 'POST', [], [
                self::MARKET => sprintf('BTC-'.$this->baseCurrency),
                'side' => 'buy',
                'orderType' => self::MARKET,
                self::AMOUNT_QUOTE => (string) 1,
            ])
            ->willReturn($this->getPendingResponseStructure($orderId))
        ;

        $this->expectExceptionObject(new PendingBuyOrderException($orderId));

        $this->service->initiateBuy(1, 'BTC');
    }

    /**
     * @covers ::checkIfOrderIsFilled
     * @covers ::getAveragePrice
     * @covers ::getCompletedBuyOrderFromResponse
     */
    public function testCheckIsFilled(): void
    {
        $orderId = 'oid'.random_int(1000, 2000);

        [
            self::DATA => $data,
            self::FILLED_SATOSHIS => $filledSatoshis,
            self::FILLED_QUOTE => $filledQuote,
            self::FEE_PAID => $feePaid,
            self::FEE_CURRENCY => $feeCurrency,
            self::PRICE => $price,
        ] = $this->getSimpleResponseStructure();

        $this->client
            ->expects(static::once())
            ->method(self::API_CALL)
            ->with(self::ORDER, 'GET', [
                self::MARKET => sprintf('BTC-'.$this->baseCurrency),
                self::ORDER_ID => $orderId,
            ])
            ->willReturn($data)
        ;

        $responseDTO = $this->service->checkIfOrderIsFilled($orderId);

        static::assertSame($filledSatoshis, $responseDTO->getAmountInSatoshis());
        static::assertSame($filledQuote.' '.$this->baseCurrency, $responseDTO->getDisplayAmountSpent());
        static::assertSame(0, $responseDTO->getFeesInSatoshis());
        static::assertSame($feePaid.' '.$feeCurrency, $responseDTO->getDisplayFeesSpent());
        static::assertSame($price.' '.$this->baseCurrency, $responseDTO->getDisplayAveragePrice());
    }

    /**
     * @covers ::checkIfOrderIsFilled
     */
    public function testCheckNotYetFilled(): void
    {
        $orderId = 'oid'.random_int(1000, 2000);

        $this->client
            ->expects(static::once())
            ->method(self::API_CALL)
            ->with(self::ORDER, 'GET', [
                self::MARKET => sprintf('BTC-'.$this->baseCurrency),
                self::ORDER_ID => $orderId,
            ])
            ->willReturn($this->getPendingResponseStructure($orderId))
        ;

        $this->expectExceptionObject(new PendingBuyOrderException($orderId));

        $this->service->checkIfOrderIsFilled($orderId);
    }

    /**
     * @covers ::cancelBuyOrder
     */
    public function testCancelOrder(): void
    {
        $orderId = 'oid'.random_int(1000, 2000);

        $this->client
            ->expects(static::once())
            ->method(self::API_CALL)
            ->with(self::ORDER, 'DELETE', [
                self::MARKET => 'BTC-'.$this->baseCurrency,
                self::ORDER_ID => $orderId,
            ])
        ;

        $this->service->cancelBuyOrder($orderId);
    }

    /**
     * @covers ::getAveragePrice
     * @covers ::getCompletedBuyOrderFromResponse
     * @covers ::initiateBuy
     */
    public function testFeesAreAccountedInBitcoin(): void
    {
        $amount = random_int(10, 20);
        $asset = 'BTC';

        [
            self::DATA => $data,
            self::FEE_PAID => $feePaid,
            self::FEE_CURRENCY => $feeCurrency,
        ] = $this->getSimpleResponseStructure('BTC');

        $this->client
            ->expects(static::once())
            ->method(self::API_CALL)
            ->willReturn($data)
        ;

        $responseDTO = $this->service->initiateBuy($amount, $asset);

        static::assertSame($feePaid * 100000000, $responseDTO->getFeesInSatoshis());
        static::assertSame($feePaid.' '.$feeCurrency, $responseDTO->getDisplayFeesSpent());
    }

    /**
     * @covers ::supportsExchange
     */
    public function testSupportsExchange(): void
    {
        static::assertTrue($this->service->supportsExchange('bitvavo'));
        static::assertFalse($this->service->supportsExchange('bitvivo'));
    }

    private function getSimpleResponseStructure($feeCurrency = 'EUR'): array
    {
        $price = random_int(9000, 11000);
        $filledSatoshis = random_int(10000, 20000);

        $data = [
            'filledAmount' => (bcdiv((string) $filledSatoshis, self::DIVISOR, 8)),
            'filledAmountQuote' => $filledQuote = (string) random_int(10, 20),
            'status' => 'filled',
            self::AMOUNT_QUOTE => $filledQuote,
            self::FEE_PAID => $feePaid = (string) random_int(1, 10),
            self::FEE_CURRENCY => $feeCurrency,
            'fills' => [
                $this->createFill(bcdiv((string) $filledSatoshis, self::DIVISOR, 8), 50, $price - 1000),
                $this->createFill(bcdiv((string) $filledSatoshis, self::DIVISOR, 8), 50, $price + 1000),
            ],
        ];

        return [
            self::DATA => $data,
            self::FILLED_SATOSHIS => $filledSatoshis,
            self::FILLED_QUOTE => $filledQuote,
            self::FEE_PAID => $feePaid,
            self::FEE_CURRENCY => $feeCurrency,
            self::PRICE => $price,
        ];
    }

    private function getPendingResponseStructure(string $orderId): array
    {
        return [
            self::ORDER_ID => $orderId,
            'status' => 'open',
        ];
    }

    private function createFill(string $totalAmount, int $percentage, int $price): array
    {
        return [
            'amount' => bcmul(bcdiv($totalAmount, '100', 8), (string) $percentage, 8),
            self::PRICE => $price,
        ];
    }
}
