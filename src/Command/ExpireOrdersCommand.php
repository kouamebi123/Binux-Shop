<?php

namespace App\Command;

use App\Service\OrderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:orders:expire', description: 'Annule les commandes restées sans paiement et remet leurs articles en stock.')]
class ExpireOrdersCommand extends Command
{
    public function __construct(private readonly OrderService $orderService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('%d commande(s) non payée(s) annulée(s).', $this->orderService->expireUnpaidOrders()));

        return Command::SUCCESS;
    }
}
