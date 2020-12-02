<?php

declare(strict_types=1);

namespace Tests\Jorijn\Bitcoin\Dca\Service\Bl3p;

use Jorijn\Bitcoin\Dca\Client\Bl3pClientInterface;
use Jorijn\Bitcoin\Dca\Service\Bl3p\Bl3pWithdrawService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Jorijn\Bitcoin\Dca\Service\Bl3p\Bl3pWithdrawService
 * @covers ::__construct
 *
 * @internal
 */
final class Bl3pWithdrawServiceTest extends TestCase
{
    public const ADDRESS = 'address';
    public const API_CALL = 'apiCall';
    public const GENMKT_MONEY_INFO = 'GENMKT/money/info';

    /** @var Bl3pClientInterface|MockObject */
    private $client;
    /** @var LoggerInterface|MockObject */
    private $logger;
    private Bl3pWithdrawService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(Bl3pClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new Bl3pWithdrawService(
            $this->client,
            $this->logger
        );
    }

    /**
     * @covers ::getAvailableBalance
     *
     * @throws \Exception
     */
    public function testGetBalance(): void
    {
        $balance = (float)random_int(1000, 2000);

        $this->client
            ->expects(static::once())
            ->method(self::API_CALL)
            ->with(self::GENMKT_MONEY_INFO)
            ->willReturn($this->createBalanceStructure($balance))
        ;

        static::assertSame($balance, $this->service->getAvailableBalance());
    }

    /**
     * @covers ::withdraw
     *
     * @throws \Exception
     */
    public function testWithdraw(): void
    {
        $asset = 'BTC';
        $address = self::ADDRESS.random_int(1000, 2000);
        $amount = (float)random_int(100000, 300000);
        $netAmount = $amount - $this->service->getWithdrawFeeInSatoshis();
        $withdrawID = 'id'.random_int(1000, 2000);
        $apiResponse = ['data' => ['id' => $withdrawID]];

        $this->client
            ->expects(static::once())
            ->method(self::API_CALL)
            ->with(
                'GENMKT/money/withdraw',
                static::callback(function (array $parameters) use ($netAmount, $address) {
                    self::assertArrayHasKey('currency', $parameters);
                    self::assertSame('BTC', $parameters['currency']);
                    self::assertArrayHasKey(self::ADDRESS, $parameters);
                    self::assertSame($address, $parameters[self::ADDRESS]);
                    self::assertArrayHasKey('amount_int', $parameters);
                    self::assertSame($netAmount, $parameters['amount_int']);

                    return true;
                })
            )
            ->willReturn($apiResponse)
        ;

        $dto = $this->service->withdraw($asset, $amount, $address);
        static::assertSame($withdrawID, $dto->getId());
        static::assertSame($netAmount, $dto->getNetAmount());
        static::assertSame($address, $dto->getRecipientAddress());
    }

    /**
     * @covers ::supportsExchange
     */
    public function testSupportsExchange(): void
    {
        static::assertTrue($this->service->supportsExchange('bl3p'));
        static::assertFalse($this->service->supportsExchange('bl4p'));
    }

    /**
     * @covers ::getWithdrawFeeInSatoshis
     */
    public function testFeeCalculation(): void
    {
        static::assertSame(30000, $this->service->getWithdrawFeeInSatoshis());
    }

    private function createBalanceStructure(float $balance): array
    {
        return [
            'data' => [
                'wallets' => [
                    'BTC' => [
                        'available' => [
                            'value_int' => $balance,
                        ],
                    ],
                ],
            ],
        ];
    }
}
