<?php

declare(strict_types=1);

namespace Tests\Jorijn\Bitcoin\Dca\Model;

use Jorijn\Bitcoin\Dca\Model\CompletedWithdraw;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Jorijn\Bitcoin\Dca\Model\CompletedWithdraw
 *
 * @internal
 */
final class CompletedWithdrawTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::getId
     * @covers ::getNetAmount
     * @covers ::getRecipientAddress
     */
    public function testGetters()
    {
        $id = 'id'.random_int(5, 10);
        $recipientAddress = 'ra'.random_int(5, 10);
        $netAmount = random_int(5, 10);

        $dto = new CompletedWithdraw($recipientAddress, $netAmount, $id);

        static::assertSame($id, $dto->getId());
        static::assertSame($recipientAddress, $dto->getRecipientAddress());
        static::assertSame($netAmount, $dto->getNetAmount());
    }
}
