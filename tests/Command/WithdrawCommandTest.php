<?php

declare(strict_types=1);

namespace Tests\Jorijn\Bl3pDca\Command;

use Jorijn\Bl3pDca\Command\WithdrawCommand;
use Jorijn\Bl3pDca\Service\WithdrawService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @coversDefaultClass \Jorijn\Bl3pDca\Command\WithdrawCommand
 *
 * @internal
 */
final class WithdrawCommandTest extends TestCase
{
    /** @var MockObject|WithdrawService */
    private $withdrawService;
    private WithdrawCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withdrawService = $this->createMock(WithdrawService::class);
        $this->command = new WithdrawCommand($this->withdrawService);
    }

    public function providerOfWithdrawScenarios(): array
    {
        return [
            'with tag, unattended' => ['tag'.random_int(1000, 2000), true, false, true, random_int(500000, 1000000)],
            'with tag, attended, confirms' => [
                'tag'.random_int(1000, 2000),
                true,
                true,
                true,
                random_int(500000, 1000000),
            ],
            'with tag, attended, declines' => [
                'tag'.random_int(1000, 2000),
                false,
                false,
                false,
                random_int(500000, 1000000),
            ],
            'without tag, unattended' => [null, true, false, true, random_int(500000, 1000000)],
            'without tag, attended' => [null, true, true, true, random_int(500000, 1000000)],
            'with tag, unattended, no balance available' => ['tag'.random_int(1000, 2000), true, false, false, 0],
            'with tag, attended, no balance available' => ['tag'.random_int(1000, 2000), false, true, false, 0],
            'without tag, unattended, no balance available' => [null, true, false, false, 0],
            'without tag, attended, no balance available' => [null, false, true, false, 0],
        ];
    }

    /**
     * @covers ::execute
     */
    public function testWithdrawSpecificAmount(): void
    {
        $this->withdrawService->expects(static::never())->method('getBalance');
        $this->withdrawService->expects(static::never())->method('getRecipientAddress');
        $this->withdrawService->expects(static::never())->method('withdraw');

        $commandTester = $this->createCommandTester();
        $commandTester->execute(['command' => $this->command->getName(), '--yes' => null]);

        static::assertSame(1, $commandTester->getStatusCode());
    }

    /**
     * @covers ::execute
     * @dataProvider providerOfWithdrawScenarios
     */
    public function testWithdraw(
        string $tag = null,
        bool $unattended = false,
        bool $attendedOK = false,
        bool $expectWithdraw = false,
        int $simulatedBalance = 0
    ): void {
        $address = 'address'.random_int(1000, 2000);

        $this->withdrawService
            ->expects(static::once())
            ->method('getBalance')
            ->with(true, $tag)
            ->willReturn($simulatedBalance)
        ;

        $this->withdrawService
            ->expects(static::once())
            ->method('getRecipientAddress')
            ->willReturn($address)
        ;

        $this->withdrawService
            ->expects($expectWithdraw ? static::once() : static::never())
            ->method('withdraw')
            ->with($simulatedBalance, $address, $tag)
        ;

        $commandTester = $this->createCommandTester();
        if (!$unattended) {
            $commandTester->setInputs([$attendedOK ? 'yes' : 'no']);
        }
        $commandTester->execute(
            ['command' => $this->command->getName(), '--all' => null]
            + ($unattended ? ['--yes' => null] : [])
            + (!empty($tag) ? ['--tag' => $tag] : [])
        );
    }

    protected function createCommandTester(): CommandTester
    {
        $application = new Application();
        $application->add($this->command->setName('withdraw'));

        return new CommandTester($this->command);
    }
}
