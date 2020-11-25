<?php

declare(strict_types=1);

namespace Jorijn\Bitcoin\Dca\Command;

use Jorijn\Bitcoin\Dca\Service\BuyService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BuyCommand extends Command
{
    protected BuyService $buyService;
    protected string $baseCurrency;

    public function __construct(BuyService $buyService, string $baseCurrency)
    {
        parent::__construct(null);

        $this->buyService = $buyService;
        $this->baseCurrency = $baseCurrency;
    }

    public function configure(): void
    {
        $this
            ->addArgument('amount', InputArgument::REQUIRED, 'The amount of base currency to use for the BUY order')
            ->addArgument('asset', InputArgument::REQUIRED, 'The asset to be bought for the BUY order')
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                'If supplied, will not confirm the amount and place the order immediately'
            )
            ->addOption(
                'tag',
                't',
                InputOption::VALUE_REQUIRED,
                'If supplied, this will increase the balance in the database for this tag with the purchased amount of Bitcoin'
            )
            ->setDescription('Places a buy order on the exchange')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $amount = (string) $input->getArgument('amount');
        if (!preg_match('/^\d+$/', $amount)) {
            $io->error('Amount should be numeric, e.g. 10');

            return 1;
        }

        $assetToBuy = (string) $input->getArgument('asset');
        // TODO check if this a valid token which can be bought on the exchange

        if (ctype_upper($assetToBuy) === false) {
          $io->error('Asset string must be a uppercase string e.g. XBT for Bitcoin');
          return 1;
        }

        if (!$input->getOption('yes') && !$io->confirm(
            'Are you sure you want to place an order for '.$this->baseCurrency.' '.$amount.'?',
            false
        )) {
            return 0;
        }

        try {
            $orderInformation = $this->buyService->buy((int) $amount, $assetToBuy, $input->getOption('tag'));

            $io->success(sprintf(
                'Bought: %s, %s: %s, price: %s, spent fees: %s',
                $orderInformation->getDisplayAmountBought(),
                $this->baseCurrency,
                $orderInformation->getDisplayAmountSpent(),
                $orderInformation->getDisplayAveragePrice(),
                $orderInformation->getDisplayFeesSpent()
            ));

            return 0;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
        }

        return 1;
    }
}
