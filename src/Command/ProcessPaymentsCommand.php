<?php

namespace App\Command;

use App\Entity\Payment;
use App\Service\Payment\MTNMoMoService;
use App\Service\Payment\NotchPayService;
use App\Service\Payment\OrangeMoneyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'transcam:process-payments',
    description: 'Traiter les paiements en attente (vérifier statut)',
)]
class ProcessPaymentsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?MTNMoMoService $mtnService = null,
        private ?OrangeMoneyService $orangeService = null,
        private ?NotchPayService $notchPayService = null
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('TransCam - Traitement des paiements en attente');

        $pendingPayments = $this->entityManager->getRepository(Payment::class)->findPendingPayments();
        $updatedCount = 0;

        foreach ($pendingPayments as $payment) {
            $oldStatus = $payment->getStatus();
            $status = $this->resolvePaymentStatus($payment);

            if ($status === null || $status === $oldStatus) {
                continue;
            }

            $payment->setStatus($status);
            $updatedCount++;
            $io->writeln(sprintf(
                'Paiement #%d: %s → %s',
                $payment->getId(),
                $oldStatus,
                $status
            ));
        }

        if ($updatedCount > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            'Paiements traités: %d, Mis à jour: %d',
            count($pendingPayments),
            $updatedCount
        ));

        return Command::SUCCESS;
    }

    private function resolvePaymentStatus(Payment $payment): ?string
    {
        $method = $payment->getMethod();
        $providerReference = $payment->getProviderReference();

        if ($this->notchPayService !== null && $providerReference !== null && in_array($method, Payment::NOTCHPAY_METHODS, true)) {
            try {
                $response = $this->notchPayService->getPayment($providerReference);
                return $response['status'] ?? null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        return match ($method) {
            Payment::METHOD_MTN_MOMO => $this->mtnService?->checkPaymentStatus($payment->getTransactionId())['status'] ?? null,
            Payment::METHOD_ORANGE_MONEY => $this->orangeService?->checkPaymentStatus($payment->getTransactionId())['status'] ?? null,
            default => null,
        };
    }
}
